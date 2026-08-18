<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    // list produk dengan filter
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $verified = $request->query('verified');
        $tradeAvailable = $request->query('trade_available');
        $sellerId = $request->query('seller_id');

        $query = Product::with('seller:id,name,avatar');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($verified !== null) {
            $query->where('verified', $verified === 'true');
        }

        if ($tradeAvailable !== null) {
            $query->where('trade_available', $tradeAvailable === 'true');
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $query->orderByDesc('created_at');

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category,
                'subcategory' => $p->subcategory,
                'brand' => $p->brand,
                'price' => (int) $p->price,
                'stock' => $p->stock,
                'sold' => $p->sold,
                'condition' => $p->condition,
                'verified' => $p->verified,
                'tradeAvailable' => $p->trade_available,
                'image' => $p->image,
                'seller' => $p->seller ? [
                    'id' => $p->seller->id,
                    'name' => $p->seller->name,
                    'avatar' => $p->seller->avatar,
                ] : null,
                'tradeCount' => Trade::where('initiator_collection_id', $p->id)
                    ->orWhere('receiver_collection_id', $p->id)
                    ->count(),
                'createdAt' => $p->created_at?->toISOString(),
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

    // detail produk + history
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['seller:id,name,avatar,rating', 'priceHistory'])->find($id);

        if (! $product) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Produk tidak ditemukan.']], 404);
        }

        $tradeCount = Trade::where('initiator_collection_id', $product->id)
            ->orWhere('receiver_collection_id', $product->id)
            ->count();

        return response()->json([
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'subcategory' => $product->subcategory,
            'brand' => $product->brand,
            'series' => $product->series,
            'itemType' => $product->item_type,
            'language' => $product->language,
            'year' => $product->year,
            'price' => (int) $product->price,
            'originalPrice' => $product->original_price,
            'discount' => $product->discount,
            'stock' => $product->stock,
            'sold' => $product->sold,
            'condition' => $product->condition,
            'verified' => $product->verified,
            'tradeAvailable' => $product->trade_available,
            'badges' => $product->badges,
            'images' => $product->images,
            'description' => $product->description,
            'seller' => $product->seller ? [
                'id' => $product->seller->id,
                'name' => $product->seller->name,
                'avatar' => $product->seller->avatar,
                'rating' => $product->seller->rating,
            ] : null,
            'tradeCount' => $tradeCount,
            'priceHistory' => $product->priceHistory->map(fn($h) => [
                'price' => (int) $h->price,
                'previousPrice' => $h->previous_price,
                'discount' => $h->discount,
                'recordedAt' => $h->recorded_at?->toISOString(),
            ]),
            'createdAt' => $product->created_at?->toISOString(),
            'updatedAt' => $product->updated_at?->toISOString(),
        ]);
    }

    // update produk (verify, toggle trade_available, hide)
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Produk tidak ditemukan.']], 404);
        }

        $request->validate([
            'verified' => ['boolean'],
            'trade_available' => ['boolean'],
            'is_hidden' => ['boolean'], // soft delete flag
        ]);

        $product->update($request->only(['verified', 'trade_available']));

        // handle hide (soft delete)
        if ($request->boolean('is_hidden')) {
            $product->delete();
        }

        return response()->json(['message' => 'Produk diperbarui.']);
    }

    // bulk verifikasi
    public function bulkVerify(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        Product::whereIn('id', $request->ids)->update(['verified' => true]);

        return response()->json(['verified' => count($request->ids)]);
    }

    // hapus produk
    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Produk tidak ditemukan.']], 404);
        }

        $product->delete();

        return response()->json(['deleted' => true]);
    }
}
