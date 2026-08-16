<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    // dashboard seller
    public function stats(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;

        $totalProducts = Product::where('seller_id', $sellerId)->count();
        $totalSold = Order::where('payment_status', 'Paid')
            ->whereHas('items', fn($q) => $q->whereIn('product_id', Product::where('seller_id', $sellerId)->pluck('id')))
            ->count();

        $revenue = Order::where('payment_status', 'Paid')
            ->whereHas('items', fn($q) => $q->whereIn('product_id', Product::where('seller_id', $sellerId)->pluck('id')))
            ->sum('total');

        $products = Product::where('seller_id', $sellerId)
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => $p->stock,
                'sold' => $p->sold,
                'createdAt' => $p->created_at?->toISOString(),
            ]);

        return response()->json([
            'totalProducts' => $totalProducts,
            'totalSold' => $totalSold,
            'revenue' => (int) $revenue,
            'products' => $products,
        ]);
    }
}