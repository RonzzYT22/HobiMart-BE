<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    // daftar koleksi user login
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $category = $request->query('category');
        $search = $request->query('search');
        $sort = $request->query('sort', 'newest');

        $query = Collection::with(['product.seller:id,name,avatar', 'product.category'])
            ->where('user_id', $user->id);

        if ($category) {
            $query->whereHas('product', fn($q) => $q->where('category', $category));
        }

        if ($search) {
            $query->whereHas('product', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }

        match ($sort) {
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'price-high' => $query->orderByDesc('purchase_price'),
            'price-low' => $query->orderBy('purchase_price'),
            'name-asc' => $query->whereHas('product', fn($q) => $q->orderBy('name')),
            default => $query->orderByDesc('created_at'),
        };

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'productId' => $c->product_id,
                'product' => $c->product ? [
                    'id' => $c->product->id,
                    'name' => $c->product->name,
                    'category' => $c->product->category,
                    'subcategory' => $c->product->subcategory,
                    'image' => $c->product->image,
                    'price' => (int) $c->product->price,
                    'condition' => $c->product->condition,
                    'verified' => $c->product->verified,
                    'seller' => $c->product->seller ? [
                        'id' => $c->product->seller->id,
                        'name' => $c->product->seller->name,
                        'avatar' => $c->product->seller->avatar,
                    ] : null,
                ] : null,
                'condition' => $c->condition,
                'grade' => $c->grade,
                'purchasePrice' => (int) $c->purchase_price,
                'purchaseDate' => $c->purchase_date?->format('Y-m-d'),
                'notes' => $c->notes,
                'images' => $c->images,
                'isPublic' => $c->is_public,
                'createdAt' => $c->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
        ]);
    }

    // tambah item ke koleksi
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'condition' => ['nullable', 'string', 'in:Mint,Near Mint,Excellent,Very Good,Good,Fair,Poor'],
            'grade' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'is_public' => ['boolean'],
        ]);

        $user = $request->user();
        $product = Product::findOrFail($request->product_id);

        // cek apakah sudah ada di koleksi
        $exists = Collection::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_IN_COLLECTION',
                    'message' => 'Produk ini sudah ada di koleksi Anda.',
                ],
            ], 422);
        }

        $collection = Collection::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'condition' => $request->condition ?? $product->condition,
            'grade' => $request->grade,
            'purchase_price' => $request->purchase_price,
            'purchase_date' => $request->purchase_date,
            'notes' => $request->notes,
            'images' => $request->images,
            'is_public' => $request->boolean('is_public', true),
        ]);

        return response()->json([
            'id' => $collection->id,
            'message' => 'Berhasil ditambahkan ke koleksi.',
        ], 201);
    }

    // detail item koleksi
    public function show(Request $request, int $id): JsonResponse
    {
        $collection = Collection::with(['product.seller:id,name,avatar'])->find($id);

        if (! $collection) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Item koleksi tidak ditemukan.'],
            ], 404);
        }

        // hanya pemilik yang bisa lihat detail penuh (private items)
        if ($collection->user_id !== $request->user()->id && ! $collection->is_public) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Item ini bersifat privat.'],
            ], 403);
        }

        return response()->json([
            'id' => $collection->id,
            'productId' => $collection->product_id,
            'product' => $collection->product ? [
                'id' => $collection->product->id,
                'name' => $collection->product->name,
                'category' => $collection->product->category,
                'subcategory' => $collection->product->subcategory,
                'brand' => $collection->product->brand,
                'series' => $collection->product->series,
                'itemType' => $collection->product->item_type,
                'image' => $collection->product->image,
                'images' => $collection->product->images,
                'price' => (int) $collection->product->price,
                'originalPrice' => $collection->product->original_price,
                'condition' => $collection->product->condition,
                'verified' => $collection->product->verified,
                'badges' => $collection->product->badges,
                'description' => $collection->product->description,
                'seller' => $collection->product->seller ? [
                    'id' => $collection->product->seller->id,
                    'name' => $collection->product->seller->name,
                    'avatar' => $collection->product->seller->avatar,
                    'rating' => $collection->product->seller->rating,
                    'positiveRate' => $collection->product->seller->positive_rate,
                ] : null,
            ] : null,
            'condition' => $collection->condition,
            'grade' => $collection->grade,
            'purchasePrice' => (int) $collection->purchase_price,
            'purchaseDate' => $collection->purchase_date?->format('Y-m-d'),
            'notes' => $collection->notes,
            'images' => $collection->images,
            'isPublic' => $collection->is_public,
            'createdAt' => $collection->created_at?->toISOString(),
            'updatedAt' => $collection->updated_at?->toISOString(),
        ]);
    }

    // update item koleksi
    public function update(Request $request, int $id): JsonResponse
    {
        $collection = Collection::find($id);

        if (! $collection) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Item koleksi tidak ditemukan.'],
            ], 404);
        }

        if ($collection->user_id !== $request->user()->id) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.'],
            ], 403);
        }

        $request->validate([
            'condition' => ['nullable', 'string', 'in:Mint,Near Mint,Excellent,Very Good,Good,Fair,Poor'],
            'grade' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'is_public' => ['boolean'],
        ]);

        $collection->update($request->only([
            'condition', 'grade', 'purchase_price', 'purchase_date', 'notes', 'images', 'is_public'
        ]));

        return response()->json(['message' => 'Koleksi diperbarui.']);
    }

    // hapus item dari koleksi
    public function destroy(Request $request, int $id): JsonResponse
    {
        $collection = Collection::find($id);

        if (! $collection) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Item koleksi tidak ditemukan.'],
            ], 404);
        }

        if ($collection->user_id !== $request->user()->id) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.'],
            ], 403);
        }

        $collection->delete();

        return response()->json(['deleted' => true]);
    }

    // koleksi publik user lain (untuk profil kolektor)
    public function publicIndex(Request $request, int $userId): JsonResponse
    {
        $category = $request->query('category');
        $sort = $request->query('sort', 'newest');

        $query = Collection::with(['product.seller:id,name,avatar'])
            ->where('user_id', $userId)
            ->where('is_public', true);

        if ($category) {
            $query->whereHas('product', fn($q) => $q->where('category', $category));
        }

        match ($sort) {
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'price-high' => $query->orderByDesc('purchase_price'),
            'price-low' => $query->orderBy('purchase_price'),
            default => $query->orderByDesc('created_at'),
        };

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'productId' => $c->product_id,
                'product' => $c->product ? [
                    'id' => $c->product->id,
                    'name' => $c->product->name,
                    'category' => $c->product->category,
                    'subcategory' => $c->product->subcategory,
                    'image' => $c->product->image,
                    'price' => (int) $c->product->price,
                    'condition' => $c->product->condition,
                    'verified' => $c->product->verified,
                ] : null,
                'condition' => $c->condition,
                'grade' => $c->grade,
                'purchasePrice' => (int) $c->purchase_price,
                'purchaseDate' => $c->purchase_date?->format('Y-m-d'),
                'notes' => $c->notes,
                'images' => $c->images,
                'createdAt' => $c->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
        ]);
    }
}
