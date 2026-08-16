<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    // daftar 5 kategori dengan count live dari produk
    public function index(): JsonResponse
    {
        // ambil count produk per kategori langsung dari tabel produk
        $counts = Product::query()
            ->whereIn('category', array_column(Category::CATEGORIES, 'name'))
            ->groupBy('category')
            ->selectRaw('category, COUNT(*) as total')
            ->pluck('total', 'category');

        $categories = collect(Category::CATEGORIES)->map(function ($cat) use ($counts) {
            return [
                'name' => $cat['name'],
                'icon' => $cat['icon'],
                'color' => $cat['color'],
                'count' => (int) ($counts[$cat['name']] ?? 0),
            ];
        });

        return response()->json($categories->values());
    }

    // daftar subcategory unik dari produk di kategori tertentu
    public function subcategories(string $name): JsonResponse
    {
        // cek dulu kategori ini dikenal
        $names = array_column(Category::CATEGORIES, 'name');
        if (! in_array($name, $names, true)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Kategori tidak ditemukan.',
                ],
            ], 404);
        }

        // ambil subcategory yang unik dan tidak kosong
        $subcategories = Product::query()
            ->where('category', $name)
            ->whereNotNull('subcategory')
            ->distinct()
            ->pluck('subcategory')
            ->filter()
            ->sort()
            ->values();

        return response()->json($subcategories);
    }
}