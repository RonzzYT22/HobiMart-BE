<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // daftar sort yang diizinkan
    protected array $allowedSorts = ['relevance', 'newest', 'price-asc', 'price-desc', 'rating', 'popular'];

    // list produk dengan filter, sort, paginasi, dan pencarian TNTSearch
    public function index(Request $request): ProductCollection|JsonResponse
    {
        $query = Product::query()->with('seller');

        // pencarian full-text pakai TNTSearch (ada toleransi typo)
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $ids = Product::search($search)
                ->query(fn ($builder) => $builder->select('id'))
                ->take(1000)
                ->keys();

            if ($ids->isEmpty()) {
                // fallback ke LIKE kalau TNTSearch tidak nemu hasil
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('brand', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            } else {
                $query->whereIn('id', $ids->all());
            }
        }

        $this->applyFilters($query, $request);
        $this->applySort($query, $request, $search !== '');

        // paginasi
        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get();

        return response()->json([
            'items' => ProductResource::collection($items)->resolve(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
        ]);
    }

    // detail satu produk (id di sini adalah SKU)
    public function show(string $sku): JsonResponse
    {
        $product = Product::with('seller')->where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound();
        }
        return response()->json((new ProductResource($product))->resolve());
    }

    // produk terkait (kategori/subkategori sama, kecuali produk ini)
    public function related(string $sku, Request $request): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound();
        }

        $limit = min(max((int) $request->query('limit', 8), 1), 50);

        $related = Product::with('seller')
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('subcategory', $product->subcategory)
                    ->orWhere('category', $product->category);
            })
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => ProductResource::collection($related)->resolve(),
        ]);
    }

    // produk unggulan: trending, new arrivals, rare finds, rekomendasi
    public function featured(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 12), 1), 50);

        $trending = Product::with('seller')
            ->orderByDesc(DB::raw('rating * sold'))
            ->limit($limit)
            ->get();

        $newArrivals = Product::with('seller')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $rareFinds = Product::with('seller')
            ->whereNotNull('badges')
            ->whereJsonContains('badges', 'RARE')
            ->orderByDesc('price')
            ->limit(6)
            ->get();

        $recommendations = Product::with('seller')
            ->orderByDesc('sold')
            ->orderByDesc('rating')
            ->limit(8)
            ->get();

        return response()->json([
            'trending' => ProductResource::collection($trending)->resolve(),
            'newArrivals' => ProductResource::collection($newArrivals)->resolve(),
            'rareFinds' => ProductResource::collection($rareFinds)->resolve(),
            'recommendations' => ProductResource::collection($recommendations)->resolve(),
        ]);
    }

    // flash deals: diskon 10-19% dengan stok tersedia
    public function flashDeals(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 1), 1);
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        $query = Product::with('seller')
            ->where('discount', '>=', 10)
            ->where('discount', '<', 20)
            ->where('stock', '>', 0)
            ->orderByDesc('discount');

        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get();

        // deadline flash deal (12 jam dari sekarang)
        $dealDeadline = now()->addHours(12)->toISOString();

        return response()->json([
            'items' => ProductResource::collection($items)->resolve(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
            'deadline' => $dealDeadline,
        ]);
    }

    // price drops: harga sekarang lebih rendah dari snapshot sebelumnya
    public function priceDrops(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 1), 1);
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        // ambil ID produk yang harga terbarunya turun
        $droppedIds = ProductHistory::query()
            ->whereNotNull('previous_price')
            ->whereColumn('price', '<', 'previous_price')
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('product_history')
                    ->whereNotNull('previous_price')
                    ->whereColumn('price', '<', 'previous_price')
                    ->groupBy('product_id');
            })
            ->pluck('product_id');

        $query = Product::with('seller')->whereIn('id', $droppedIds);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->orderByDesc('updated_at')->forPage($page, $limit)->get();

        // tambah info penghematan ke setiap produk
        $result = $items->map(function (Product $product) {
            $history = $product->priceHistory()
                ->whereNotNull('previous_price')
                ->whereColumn('price', '<', 'previous_price')
                ->latest('recorded_at')
                ->first();

            $resource = (new ProductResource($product))->resolve();

            if ($history) {
                $resource['previousPrice'] = (int) $history->previous_price;
                $resource['savings'] = (int) ($history->previous_price - $history->price);
                $resource['savingsPercent'] = $history->previous_price > 0
                    ? round((($history->previous_price - $history->price) / $history->previous_price) * 100, 1)
                    : 0.0;
            }

            return $resource;
        });

        return response()->json([
            'items' => $result,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
        ]);
    }

    // create produk baru
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['seller_id'] = $data['seller_id'] ?? $request->user()?->id ?? \App\Models\User::first()->id;

        // hitung diskon otomatis kalau original_price > price
        if (empty($data['original_price']) && isset($data['price'])) {
            $data['original_price'] = $data['price'];
        }
        if (empty($data['discount']) || !isset($data['discount'])) {
            $data['discount'] = 0;
        }
        if (!empty($data['original_price']) && !empty($data['price'])) {
            if ($data['original_price'] > $data['price']) {
                $data['discount'] = (int) round((($data['original_price'] - $data['price']) / $data['original_price']) * 100);
            }
        }

        $product = Product::create($data);

        // catat snapshot harga awal
        ProductHistory::create([
            'product_id' => $product->id,
            'price' => $product->price,
            'previous_price' => $product->original_price !== $product->price ? $product->original_price : null,
            'discount' => $product->discount,
            'recorded_at' => now(),
        ]);

        return response()->json((new ProductResource($product->load('seller')))->resolve(), 201);
    }

    // update produk (id di sini adalah SKU)
    public function update(UpdateProductRequest $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound();
        }

        $oldPrice = $product->price;
        $data = $request->validated();

        // catat history kalau harga berubah
        if (array_key_exists('price', $data) && $data['price'] !== $oldPrice) {
            ProductHistory::create([
                'product_id' => $product->id,
                'price' => $data['price'],
                'previous_price' => $oldPrice,
                'discount' => $data['discount'] ?? $product->discount,
                'recorded_at' => now(),
            ]);
        }

        $product->update($data);
        return response()->json((new ProductResource($product->load('seller')))->resolve());
    }

    // hapus produk (soft delete, id di sini adalah SKU)
    public function destroy(string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return $this->notFound();
        }

        $product->delete();
        return response()->json(['deleted' => true]);
    }

    // pasang filter dari query parameter
    protected function applyFilters($query, Request $request): void
    {
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($subcategory = $request->query('subcategory')) {
            $query->where('subcategory', $subcategory);
        }
        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }
        if ($conditions = $request->query('condition')) {
            $conditions = is_array($conditions) ? $conditions : [$conditions];
            $query->whereIn('condition', $conditions);
        }
        if ($badges = $request->query('badges')) {
            $badges = is_array($badges) ? $badges : [$badges];
            foreach ($badges as $badge) {
                $query->whereJsonContains('badges', $badge);
            }
        }
        if ($minRating = $request->query('minRating')) {
            $query->where('rating', '>=', (float) $minRating);
        }
        if ($request->boolean('inStock')) {
            $query->where('stock', '>', 0);
        }
        if ($priceMin = $request->query('priceMin')) {
            $query->where('price', '>=', (int) $priceMin);
        }
        if ($priceMax = $request->query('priceMax')) {
            $query->where('price', '<=', (int) $priceMax);
        }
        if ($request->boolean('tradeAvailable')) {
            $query->where('trade_available', true);
        }
        if ($request->boolean('verified')) {
            $query->where('verified', true);
        }
    }

    // pasang sorting
    protected function applySort($query, Request $request, bool $isSearch = false): void
    {
        $sort = $request->query('sort', $isSearch ? 'relevance' : 'newest');

        if (!in_array($sort, $this->allowedSorts, true)) {
            $sort = 'newest';
        }

        match ($sort) {
            'newest' => $query->orderByDesc('created_at'),
            'price-asc' => $query->orderBy('price'),
            'price-desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating'),
            'popular' => $query->orderByDesc('sold')->orderByDesc('rating'),
            default => $isSearch ? $query->orderByDesc('sold') : $query->orderByDesc('created_at'),
        };
    }

    // response 404 standar
    protected function notFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Produk tidak ditemukan.',
            ],
        ], 404);
    }
}
