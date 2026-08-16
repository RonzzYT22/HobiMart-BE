<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    // ringkasan dashboard admin
    public function __invoke(): JsonResponse
    {
        $totalUsers = User::count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();

        // total pendapatan (dari order yang sudah dibayar/dummy)
        $totalRevenue = Order::sum('total') ?? 0;

        $recentUsers = User::orderByDesc('created_at')->limit(5)->get(['id', 'name', 'email', 'role', 'created_at']);
        $recentOrders = Order::with('user:id,name')->orderByDesc('created_at')->limit(5)->get();

        // produk per kategori
        $productsByCategory = Product::selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category');

        return response()->json([
            'stats' => [
                'totalUsers' => $totalUsers,
                'totalProducts' => $totalProducts,
                'totalOrders' => $totalOrders,
                'totalRevenue' => $totalRevenue,
            ],
            'productsByCategory' => $productsByCategory,
            'recentUsers' => $recentUsers,
            'recentOrders' => $recentOrders,
        ]);
    }
}