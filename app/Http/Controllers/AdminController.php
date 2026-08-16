<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    // dashboard stats
    public function stats(): JsonResponse
    {
        return response()->json([
            'totalUsers' => User::count(),
            'totalProducts' => Product::count(),
            'totalOrders' => Order::count(),
            'totalRevenue' => (int) Order::where('payment_status', 'Paid')->sum('total'),
            'pendingOrders' => Order::where('status', 'Placed')->count(),
            'recentOrders' => Order::with('user:id,name')->orderByDesc('created_at')->take(5)->get()->map(fn($o) => [
                'id' => $o->id,
                'orderNumber' => $o->order_number,
                'customer' => $o->user->name ?? null,
                'total' => (int) $o->total,
                'status' => $o->status,
                'createdAt' => $o->created_at?->toISOString(),
            ]),
        ]);
    }

    // verifikasi seller
    public function verifySeller(int $userId): JsonResponse
    {
        $user = User::find($userId);
        if (! $user) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User tidak ditemukan.']], 404);
        }

        $user->update(['is_verified_seller' => true]);

        return response()->json(['verified' => true]);
    }

    // verifikasi produk
    public function verifyProduct(int $productId): JsonResponse
    {
        $product = Product::find($productId);
        if (! $product) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Produk tidak ditemukan.']], 404);
        }

        $product->update(['verified' => true]);

        return response()->json(['verified' => true]);
    }
}