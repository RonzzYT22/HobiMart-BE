<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // riwayat transaksi gabungan (order + trade)
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type'); // 'buy' | 'sell' | 'trade' | 'all'
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // ambil orders
        $orderQuery = Order::with('items.product:id,name,image')
            ->where('user_id', $user->id);

        if ($status) {
            $orderQuery->where('status', $status);
        }

        if ($dateFrom) {
            $orderQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $orderQuery->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $orderQuery->orderByDesc('created_at')
            ->get()
            ->map(function ($o) {
                $isBuyer = true; // order selalu user sebagai buyer
                return [
                    'id' => 'order_' . $o->id,
                    'type' => 'buy',
                    'role' => 'buyer',
                    'orderNumber' => $o->order_number,
                    'status' => $o->status,
                    'paymentStatus' => $o->payment_status,
                    'total' => (int) $o->total,
                    'items' => $o->items->map(fn($i) => [
                        'id' => $i->id,
                        'productId' => $i->product_id,
                        'name' => $i->name,
                        'image' => $i->image,
                        'quantity' => $i->quantity,
                        'price' => (int) $i->price,
                    ]),
                    'shippingAddress' => $o->shipping_address,
                    'delivery' => $o->courier,
                    'trackingNumber' => $o->tracking_number,
                    'estArrival' => $o->est_arrival?->format('Y-m-d'),
                    'timeline' => $o->timeline,
                    'createdAt' => $o->created_at?->toISOString(),
                ];
            });

        // ambil trades
        $tradeQuery = Trade::with([
            'initiator:id,name,avatar',
            'receiver:id,name,avatar',
            'initiatorCollection.product:id,name,image',
            'receiverCollection.product:id,name,image',
        ])->where(function ($q) use ($user) {
            $q->where('initiator_id', $user->id)
              ->orWhere('receiver_id', $user->id);
        });

        if ($type === 'trade' || $type === 'all' || ! $type) {
            if ($status) {
                $tradeQuery->where('status', $status);
            }

            if ($dateFrom) {
                $tradeQuery->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $tradeQuery->whereDate('created_at', '<=', $dateTo);
            }

            $trades = $tradeQuery->orderByDesc('created_at')
                ->get()
                ->map(function ($t) use ($user) {
                    $isInitiator = $t->isInitiator($user->id);
                    return [
                        'id' => 'trade_' . $t->id,
                        'type' => 'trade',
                        'role' => $isInitiator ? 'initiator' : 'receiver',
                        'tradeId' => $t->id,
                        'status' => $t->status,
                        'cashDifference' => (int) $t->cash_difference,
                        'counterparty' => $isInitiator ? [
                            'id' => $t->receiver->id,
                            'name' => $t->receiver->name,
                            'avatar' => $t->receiver->avatar,
                        ] : [
                            'id' => $t->initiator->id,
                            'name' => $t->initiator->name,
                            'avatar' => $t->initiator->avatar,
                        ],
                        'myItem' => $isInitiator ? [
                            'id' => $t->initiatorCollection?->id,
                            'product' => $t->initiatorCollection?->product ? [
                                'id' => $t->initiatorCollection->product->id,
                                'name' => $t->initiatorCollection->product->name,
                                'image' => $t->initiatorCollection->product->image,
                                'price' => (int) $t->initiatorCollection->product->price,
                            ] : null,
                        ] : [
                            'id' => $t->receiverCollection?->id,
                            'product' => $t->receiverCollection?->product ? [
                                'id' => $t->receiverCollection->product->id,
                                'name' => $t->receiverCollection->product->name,
                                'image' => $t->receiverCollection->product->image,
                                'price' => (int) $t->receiverCollection->product->price,
                            ] : null,
                        ],
                        'theirItem' => $isInitiator ? [
                            'id' => $t->receiverCollection?->id,
                            'product' => $t->receiverCollection?->product ? [
                                'id' => $t->receiverCollection->product->id,
                                'name' => $t->receiverCollection->product->name,
                                'image' => $t->receiverCollection->product->image,
                                'price' => (int) $t->receiverCollection->product->price,
                            ] : null,
                        ] : [
                            'id' => $t->initiatorCollection?->id,
                            'product' => $t->initiatorCollection?->product ? [
                                'id' => $t->initiatorCollection->product->id,
                                'name' => $t->initiatorCollection->product->name,
                                'image' => $t->initiatorCollection->product->image,
                                'price' => (int) $t->initiatorCollection->product->price,
                            ] : null,
                        ],
                        'initiatorShippedAt' => $t->initiator_shipped_at?->toISOString(),
                        'receiverShippedAt' => $t->receiver_shipped_at?->toISOString(),
                        'initiatorTracking' => $t->initiator_tracking,
                        'receiverTracking' => $t->receiver_tracking,
                        'completedAt' => $t->completed_at?->toISOString(),
                        'createdAt' => $t->created_at?->toISOString(),
                    ];
                });
        } else {
            $trades = collect();
        }

        // gabungkan & sort by createdAt desc
        $all = $orders->concat($trades)->sortByDesc('createdAt')->values();

        // paginasi manual
        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $all->count();
        $hasMore = ($page * $limit) < $total;
        $items = $all->forPage($page, $limit)->values();

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
        ]);
    }

    // detail transaksi (order atau trade)
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (str_starts_with($id, 'order_')) {
            $orderId = (int) str_replace('order_', '', $id);
            $order = Order::with('items.product')->where('user_id', $user->id)->find($orderId);

            if (! $order) {
                return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Transaksi tidak ditemukan.']], 404);
            }

            return response()->json([
                'id' => 'order_' . $order->id,
                'type' => 'buy',
                'role' => 'buyer',
                'orderNumber' => $order->order_number,
                'status' => $order->status,
                'paymentStatus' => $order->payment_status,
                'items' => $order->items,
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

        if (str_starts_with($id, 'trade_')) {
            $tradeId = (int) str_replace('trade_', '', $id);
            $trade = Trade::with([
                'initiator:id,name,avatar,rating,positive_rate,phone',
                'receiver:id,name,avatar,rating,positive_rate,phone',
                'initiatorCollection.product',
                'receiverCollection.product',
            ])->find($tradeId);

            if (! $trade || ! $trade->involvesUser($user->id)) {
                return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Transaksi tidak ditemukan.']], 404);
            }

            $isInitiator = $trade->isInitiator($user->id);

            return response()->json([
                'id' => 'trade_' . $trade->id,
                'type' => 'trade',
                'role' => $isInitiator ? 'initiator' : 'receiver',
                'tradeId' => $trade->id,
                'status' => $trade->status,
                'cashDifference' => (int) $trade->cash_difference,
                'initiator' => [
                    'id' => $trade->initiator->id,
                    'name' => $trade->initiator->name,
                    'avatar' => $trade->initiator->avatar,
                    'rating' => $trade->initiator->rating,
                    'positiveRate' => $trade->initiator->positive_rate,
                    'phone' => $trade->initiator->phone,
                ],
                'receiver' => [
                    'id' => $trade->receiver->id,
                    'name' => $trade->receiver->name,
                    'avatar' => $trade->receiver->avatar,
                    'rating' => $trade->receiver->rating,
                    'positiveRate' => $trade->receiver->positive_rate,
                    'phone' => $trade->receiver->phone,
                ],
                'initiatorItem' => $trade->initiatorCollection ? [
                    'id' => $trade->initiatorCollection->id,
                    'product' => $trade->initiatorCollection->product,
                    'condition' => $trade->initiatorCollection->condition,
                    'grade' => $trade->initiatorCollection->grade,
                    'purchasePrice' => (int) $trade->initiatorCollection->purchase_price,
                    'images' => $trade->initiatorCollection->images,
                ] : null,
                'receiverItem' => $trade->receiverCollection ? [
                    'id' => $trade->receiverCollection->id,
                    'product' => $trade->receiverCollection->product,
                    'condition' => $trade->receiverCollection->condition,
                    'grade' => $trade->receiverCollection->grade,
                    'purchasePrice' => (int) $trade->receiverCollection->purchase_price,
                    'images' => $trade->receiverCollection->images,
                ] : null,
                'initiatorShippedAt' => $trade->initiator_shipped_at?->toISOString(),
                'receiverShippedAt' => $trade->receiver_shipped_at?->toISOString(),
                'initiatorTracking' => $trade->initiator_tracking,
                'receiverTracking' => $trade->receiver_tracking,
                'completedAt' => $trade->completed_at?->toISOString(),
                'disputeReason' => $trade->dispute_reason,
                'createdAt' => $trade->created_at?->toISOString(),
            ]);
        }

        return response()->json(['error' => ['code' => 'INVALID_ID', 'message' => 'Format ID tidak valid.']], 422);
    }

    // export CSV
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type', 'all');

        // Reuse index logic but get all (no pagination)
        $items = $this->index(new Request(array_merge($request->all(), ['limit' => 10000, 'page' => 1])))->getData(true)['items'];

        // Build CSV
        $headers = [
            'Type', 'Role', 'ID', 'Status', 'Total/Value', 'Counterparty',
            'My Item', 'Their Item', 'Date'
        ];

        $rows = $items->map(function ($item) {
            if ($item['type'] === 'buy') {
                return [
                    'Order (Beli)',
                    'Buyer',
                    $item['orderNumber'],
                    $item['status'],
                    'Rp' . number_format($item['total'], 0, ',', '.'),
                    '-',
                    $item['items'][0]['name'] ?? '-',
                    '-',
                    $item['createdAt'],
                ];
            }

            $roleLabel = $item['role'] === 'initiator' ? 'Initiator' : 'Receiver';
            $myItem = $item['myItem']['product']['name'] ?? '-';
            $theirItem = $item['theirItem']['product']['name'] ?? '-';
            $counterparty = $item['counterparty']['name'] ?? '-';
            $value = $item['cashDifference'] !== 0
                ? 'Rp' . number_format(abs($item['cashDifference']), 0, ',', '.') . ($item['cashDifference'] < 0 ? ' (terima)' : ' (bayar)')
                : 'Trade murni';

            return [
                'Trade',
                $roleLabel,
                'TRD-' . str_pad($item['tradeId'], 6, '0', STR_PAD_LEFT),
                $item['status'],
                $value,
                $counterparty,
                $myItem,
                $theirItem,
                $item['createdAt'],
            ];
        });

        $csv = implode(',', array_map(fn($h) => '"' . $h . '"', $headers)) . "\n";
        $csv .= $rows->map(fn($r) => implode(',', array_map(fn($c) => '"' . $c . '"', $r)))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="riwayat-transaksi-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
