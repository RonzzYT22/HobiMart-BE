<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    // generate label pengiriman (dummy)
    public function generateLabel(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Pesanan tidak ditemukan.']], 404);
        }

        if ($order->payment_status !== 'Paid') {
            return response()->json(['error' => ['code' => 'NOT_PAID', 'message' => 'Pesanan belum dibayar.']], 422);
        }

        // generate dummy tracking number
        if (! $order->tracking_number) {
            $order->update([
                'tracking_number' => 'HM-' . now()->format('Ymd') . '-' . strtoupper(substr(md5($order->id), 0, 6)),
                'status' => 'Shipped',
                'timeline' => array_merge($order->timeline ?? [], [[
                    'status' => 'Shipped',
                    'label' => 'Pesanan dikirim',
                    'time' => now()->toISOString(),
                ]]),
            ]);
        }

        return response()->json([
            'orderNumber' => $order->order_number,
            'trackingNumber' => $order->tracking_number,
            'courier' => $order->courier,
            'shippingAddress' => $order->shipping_address,
            'labelUrl' => null, // dummy, no real PDF
        ]);
    }
}