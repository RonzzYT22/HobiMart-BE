<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class GeoController extends Controller
{
    // daftar provinsi (statis)
    public function provinces(): JsonResponse
    {
        return response()->json([
            ['id' => 1, 'name' => 'DKI Jakarta'],
            ['id' => 2, 'name' => 'Jawa Barat'],
            ['id' => 3, 'name' => 'Jawa Tengah'],
            ['id' => 4, 'name' => 'Jawa Timur'],
            ['id' => 5, 'name' => 'Banten'],
            ['id' => 6, 'name' => 'DI Yogyakarta'],
            ['id' => 7, 'name' => 'Bali'],
            ['id' => 8, 'name' => 'Sumatera Utara'],
            ['id' => 9, 'name' => 'Sumatera Barat'],
            ['id' => 10, 'name' => 'Kalimantan Timur'],
        ]);
    }

    // daftar kota per provinsi (statis)
    public function cities(int $provinceId): JsonResponse
    {
        $cities = match ($provinceId) {
            1 => ['Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Timur', 'Jakarta Barat', 'Jakarta Utara'],
            2 => ['Bandung', 'Bekasi', 'Bogor', 'Depok', 'Cimahi'],
            3 => ['Semarang', 'Surakarta', 'Magelang', 'Pekalongan', 'Tegal'],
            4 => ['Surabaya', 'Malang', 'Kediri', 'Blitar', 'Madiun'],
            5 => ['Tangerang', 'Serang', 'Cilegon', 'Tangerang Selatan'],
            6 => ['Yogyakarta', 'Sleman', 'Bantul', 'Gunung Kidul', 'Kulon Progo'],
            7 => ['Denpasar', 'Badung', 'Gianyar', 'Tabanan', 'Singaraja'],
            default => ['Kota belum tersedia'],
        };

        return response()->json(array_map(fn($c, $i) => [
            'id' => ($provinceId * 100) + $i + 1,
            'name' => $c,
        ], $cities, array_keys($cities)));
    }
}