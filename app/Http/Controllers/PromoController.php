<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    // validasi kode promo dan hitung diskon
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'total' => ['required', 'integer', 'min:0'],
        ]);

        $promo = PromoCode::where('code', strtoupper($request->code))->first();

        if (! $promo) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CODE',
                    'message' => 'Kode promo tidak ditemukan.',
                ],
            ], 422);
        }

        if (! $promo->isValid()) {
            return response()->json([
                'error' => [
                    'code' => 'EXPIRED',
                    'message' => 'Kode promo sudah tidak berlaku atau sudah habis.',
                ],
            ], 422);
        }

        $total = $request->integer('total');

        if ($total < $promo->min_purchase) {
            return response()->json([
                'error' => [
                    'code' => 'MIN_PURCHASE',
                    'message' => 'Minimal pembelian Rp ' . number_format($promo->min_purchase, 0, ',', '.') . ' untuk kode promo ini.',
                ],
            ], 422);
        }

        $discount = $promo->calculateDiscount($total);

        return response()->json([
            'valid' => true,
            'code' => $promo->code,
            'discountType' => $promo->discount_type,
            'discountValue' => $promo->discount_value,
            'discount' => $discount,
            'totalAfterDiscount' => $total - $discount,
        ]);
    }
}