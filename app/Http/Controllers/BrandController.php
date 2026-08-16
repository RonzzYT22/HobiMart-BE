<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    // daftar brand dengan count produk masing-masing
    public function index(): JsonResponse
    {
        // ambil count produk per brand langsung dari tabel produk
        $counts = Product::query()
            ->whereNotNull('brand')
            ->groupBy('brand')
            ->selectRaw('brand, COUNT(*) as total')
            ->pluck('total', 'brand');

        // brand yang ada di produk tapi belum terdaftar ikut dimunculkan
        $names = collect($counts->keys())
            ->merge(collect(\App\Models\Brand::BRANDS))
            ->unique()
            ->sort()
            ->values();

        $brands = $names->map(function ($name) use ($counts) {
            return [
                'name' => $name,
                'count' => (int) ($counts[$name] ?? 0),
            ];
        });

        return response()->json($brands->values());
    }
}