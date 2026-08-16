<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    // daftar wishlist user yang sedang login
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sort = $request->query('sort', 'newest');
        $allowedSorts = ['newest', 'price-asc', 'price-desc', 'rating', 'name'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        $query = Wishlist::with('product.seller')
            ->where('user_id', $user->id)
            ->whereHas('product');

        // sorting
        match ($sort) {
            'price-asc' => $query->join('products', 'wishlists.product_id', '=', 'products.id')
                ->orderBy('products.price'),
            'price-desc' => $query->join('products', 'wishlists.product_id', '=', 'products.id')
                ->orderByDesc('products.price'),
            'rating' => $query->join('products', 'wishlists.product_id', '=', 'products.id')
                ->orderByDesc('products.rating'),
            'name' => $query->join('products', 'wishlists.product_id', '=', 'products.id')
                ->orderBy('products.name'),
            default => $query->orderByDesc('wishlists.created_at'),
        };

        $wishlistItems = $query->get();

        // hitung total harga
        $totalValue = $wishlistItems->sum(function ($item) {
            return $item->product ? $item->product->price : 0;
        });

        // cek price drop: bandingkan harga sekarang dengan added_at_price
        $priceDropCount = 0;
        $items = $wishlistItems->map(function ($item) use (&$priceDropCount) {
            $product = $item->product;
            $data = $product ? (new ProductResource($product))->resolve() : null;

            if ($data) {
                $data['addedAtPrice'] = (int) $item->added_at_price;
                $data['currentPrice'] = (int) $product->price;

                // cek apakah harga turun dari harga saat ditambahkan
                if ($item->added_at_price > 0 && $product->price < $item->added_at_price) {
                    $data['priceDropped'] = true;
                    $data['priceDropPercent'] = (int) round(
                        (($item->added_at_price - $product->price) / $item->added_at_price) * 100
                    );
                    $data['savings'] = (int) ($item->added_at_price - $product->price);
                    $priceDropCount++;
                } else {
                    $data['priceDropped'] = false;
                    $data['priceDropPercent'] = 0;
                    $data['savings'] = 0;
                }
            }

            return $data;
        })->filter();

        return response()->json([
            'items' => $items->values(),
            'totalValue' => $totalValue,
            'priceDropCount' => $priceDropCount,
            'count' => $items->count(),
        ]);
    }

    // tambah produk ke wishlist
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $user = $request->user();

        // cek duplikasi
        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_EXISTS',
                    'message' => 'Produk sudah ada di wishlist.',
                ],
            ], 422);
        }

        $product = Product::findOrFail($request->product_id);

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at_price' => $product->price,
        ]);

        return response()->json(['added' => true], 201);
    }

    // hapus produk dari wishlist (pakai id integer produk)
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Produk tidak ada di wishlist.',
                ],
            ], 404);
        }

        return response()->json(['removed' => true]);
    }

    // cek apakah produk sudah di wishlist (untuk toggle heart)
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => ['required', 'string'],
        ]);

        $user = $request->user();
        $ids = array_map('intval', explode(',', $request->product_ids));

        $wishlisted = Wishlist::where('user_id', $user->id)
            ->whereIn('product_id', $ids)
            ->pluck('product_id')
            ->toArray();

        return response()->json([
            'productIds' => array_map(function ($id) {
                return (int) $id;
            }, $wishlisted),
        ]);
    }
}