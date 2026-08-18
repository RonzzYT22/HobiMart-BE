<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    // list order dengan filter
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $paymentStatus = $request->query('payment_status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Order::with('user:id,name,email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $query->orderByDesc('created_at');

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get()->map(function ($o) {
            return [
                'id' => $o->id,
                'orderNumber' => $o->order_number,
                'customer' => $o->user ? [
                    'id' => $o->user->id,
                    'name' => $o->user->name,
                    'email' => $o->user->email,
                ] : null,
                'status' => $o->status,
                'paymentStatus' => $o->payment_status,
                'total' => (int) $o->total,
                'itemCount' => count($o->items ?? []),
                'delivery' => $o->courier,
                'trackingNumber' => $o->tracking_number,
                'createdAt' => $o->created_at?->toISOString(),
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

    // detail order
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['user:id,name,email,phone', 'items.product:id,name,image'])->find($id);

        if (! $order) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Order tidak ditemukan.']], 404);
        }

        return response()->json([
            'id' => $order->id,
            'orderNumber' => $order->order_number,
            'customer' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
            ] : null,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'items' => $order->items->map(fn($i) => [
                'id' => $i->id,
                'productId' => $i->product_id,
                'name' => $i->name,
                'image' => $i->image,
                'quantity' => $i->quantity,
                'price' => (int) $i->price,
            ]),
            'subtotal' => (int) $order->subtotal,
            'shipping' => (int) $order->shipping,
            'total' => (int) $order->total,
            'shippingAddress' => $order->shipping_address,
            'paymentMethod' => $order->payment_method,
            'delivery' => $order->courier,
            'trackingNumber' => $order->tracking_number,
            'estArrival' => $order->est_arrival?->format('Y-m-d'),
            'timeline' => $order->timeline,
            'createdAt' => $order->created_at?->toISOString(),
        ]);
    }

    // update status order
    public function update(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Order tidak ditemukan.']], 404);
        }

        $request->validate([
            'status' => ['nullable', 'string', 'in:Placed,Processing,Shipped,Delivered,Cancelled,Refunded'],
            'payment_status' => ['nullable', 'string', 'in:Unpaid,Paid,Refunded'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $updates = $request->only(['status', 'payment_status', 'tracking_number']);

        if ($request->has('tracking_number') && $request->tracking_number) {
            $updates['status'] = $updates['status'] ?? 'Shipped';
        }

        $order->update($updates);

        return response()->json(['message' => 'Order diperbarui.']);
    }

    // refund order
    public function refund(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Order tidak ditemukan.']], 404);
        }

        if ($order->payment_status !== 'Paid') {
            return response()->json(['error' => ['code' => 'INVALID_STATE', 'message' => 'Order belum dibayar.']], 422);
        }

        $timeline = $order->timeline ?? [];
        $timeline[] = [
            'status' => 'Refunded',
            'label' => 'Dana dikembalikan',
            'time' => now()->toISOString(),
        ];

        $order->update([
            'status' => 'Refunded',
            'payment_status' => 'Refunded',
            'timeline' => $timeline,
        ]);

        return response()->json(['message' => 'Refund diproses.']);
    }

    // export CSV
    public function export(Request $request): JsonResponse
    {
        $items = $this->index(new Request(array_merge($request->all(), ['limit' => 10000])))->getData(true)['items'];

        $headers = ['Order Number', 'Customer', 'Status', 'Payment', 'Total', 'Items', 'Delivery', 'Tracking', 'Date'];
        $rows = $items->map(fn($o) => [
            $o['orderNumber'],
            $o['customer']['name'] ?? '-',
            $o['status'],
            $o['paymentStatus'],
            'Rp' . number_format($o['total'], 0, ',', '.'),
            $o['itemCount'],
            $o['delivery'],
            $o['trackingNumber'] ?? '-',
            $o['createdAt'],
        ]);

        $csv = implode(',', array_map(fn($h) => '"' . $h . '"', $headers)) . "\n";
        $csv .= $rows->map(fn($r) => implode(',', array_map(fn($c) => '"' . $c . '"', $r)))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
