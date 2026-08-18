<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTradeController extends Controller
{
    // list trade dengan filter
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Trade::with([
            'initiator:id,name,avatar',
            'receiver:id,name,avatar',
            'initiatorCollection.product:id,name,image',
            'receiverCollection.product:id,name,image',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('initiator', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('receiver', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('initiatorCollection.product', fn($pq) => $pq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('receiverCollection.product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
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
        $items = $query->forPage($page, $limit)->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'status' => $t->status,
                'cashDifference' => (int) $t->cash_difference,
                'initiator' => [
                    'id' => $t->initiator->id,
                    'name' => $t->initiator->name,
                    'avatar' => $t->initiator->avatar,
                ],
                'receiver' => [
                    'id' => $t->receiver->id,
                    'name' => $t->receiver->name,
                    'avatar' => $t->receiver->avatar,
                ],
                'initiatorItem' => $t->initiatorCollection ? [
                    'id' => $t->initiatorCollection->id,
                    'product' => $t->initiatorCollection->product ? [
                        'id' => $t->initiatorCollection->product->id,
                        'name' => $t->initiatorCollection->product->name,
                        'image' => $t->initiatorCollection->product->image,
                    ] : null,
                ] : null,
                'receiverItem' => $t->receiverCollection ? [
                    'id' => $t->receiverCollection->id,
                    'product' => $t->receiverCollection->product ? [
                        'id' => $t->receiverCollection->product->id,
                        'name' => $t->receiverCollection->product->name,
                        'image' => $t->receiverCollection->product->image,
                    ] : null,
                ] : null,
                'initiatorShippedAt' => $t->initiator_shipped_at?->toISOString(),
                'receiverShippedAt' => $t->receiver_shipped_at?->toISOString(),
                'initiatorTracking' => $t->initiator_tracking,
                'receiverTracking' => $t->receiver_tracking,
                'completedAt' => $t->completed_at?->toISOString(),
                'disputeReason' => $t->dispute_reason,
                'createdAt' => $t->created_at?->toISOString(),
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

    // detail trade
    public function show(int $id): JsonResponse
    {
        $trade = Trade::with([
            'initiator:id,name,avatar,phone,email',
            'receiver:id,name,avatar,phone,email',
            'initiatorCollection.product',
            'receiverCollection.product',
        ])->find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        return response()->json([
            'id' => $trade->id,
            'status' => $trade->status,
            'cashDifference' => (int) $trade->cash_difference,
            'initiator' => [
                'id' => $trade->initiator->id,
                'name' => $trade->initiator->name,
                'avatar' => $trade->initiator->avatar,
                'phone' => $trade->initiator->phone,
                'email' => $trade->initiator->email,
            ],
            'receiver' => [
                'id' => $trade->receiver->id,
                'name' => $trade->receiver->name,
                'avatar' => $trade->receiver->avatar,
                'phone' => $trade->receiver->phone,
                'email' => $trade->receiver->email,
            ],
            'initiatorItem' => $trade->initiatorCollection ? [
                'id' => $trade->initiatorCollection->id,
                'product' => $trade->initiatorCollection->product,
                'condition' => $trade->initiatorCollection->condition,
                'grade' => $trade->initiatorCollection->grade,
                'images' => $trade->initiatorCollection->images,
            ] : null,
            'receiverItem' => $trade->receiverCollection ? [
                'id' => $trade->receiverCollection->id,
                'product' => $trade->receiverCollection->product,
                'condition' => $trade->receiverCollection->condition,
                'grade' => $trade->receiverCollection->grade,
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

    // admin intervene: force complete
    public function forceComplete(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        if (! $trade->canTransitionTo('completed')) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Trade tidak bisa diselesaikan.']], 422);
        }

        $trade->transitionTo('completed');
        $trade->initiator->increment('trades_count');
        $trade->receiver->increment('trades_count');

        return response()->json(['message' => 'Trade dipaksa selesai.']);
    }

    // admin intervene: force cancel
    public function forceCancel(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        if (! $trade->canTransitionTo('cancelled')) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Trade tidak bisa dibatalkan.']], 422);
        }

        $trade->transitionTo('cancelled');

        return response()->json(['message' => 'Trade dipaksa dibatalkan.']);
    }

    // resolve dispute
    public function resolveDispute(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        if ($trade->status !== 'disputed') {
            return response()->json(['error' => ['code' => 'NOT_DISPUTED', 'message' => 'Trade tidak dalam status dispute.']], 422);
        }

        $request->validate([
            'resolution' => ['required', 'string', 'in:complete,cancel'],
            'note' => ['nullable', 'string'],
        ]);

        $newStatus = $request->resolution === 'complete' ? 'completed' : 'cancelled';

        if (! $trade->canTransitionTo($newStatus)) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Resolusi tidak valid.']], 422);
        }

        $trade->transitionTo($newStatus);

        if ($newStatus === 'completed') {
            $trade->initiator->increment('trades_count');
            $trade->receiver->increment('trades_count');
        }

        return response()->json(['message' => "Dispute diselesaikan: {$newStatus}.", 'status' => $newStatus]);
    }

    // export CSV
    public function export(Request $request): JsonResponse
    {
        $items = $this->index(new Request(array_merge($request->all(), ['limit' => 10000])))->getData(true)['items'];

        $headers = ['ID', 'Status', 'Initiator', 'Receiver', 'Cash Diff', 'My Item', 'Their Item', 'Date'];
        $rows = $items->map(fn($t) => [
            'TRD-' . str_pad($t['id'], 6, '0', STR_PAD_LEFT),
            $t['status'],
            $t['initiator']['name'],
            $t['receiver']['name'],
            'Rp' . number_format(abs($t['cashDifference']), 0, ',', '.') . ($t['cashDifference'] < 0 ? ' (receiver)' : ' (initiator)'),
            $t['initiatorItem']['product']['name'] ?? '-',
            $t['receiverItem']['product']['name'] ?? '-',
            $t['createdAt'],
        ]);

        $csv = implode(',', array_map(fn($h) => '"' . $h . '"', $headers)) . "\n";
        $csv .= $rows->map(fn($r) => implode(',', array_map(fn($c) => '"' . $c . '"', $r)))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="trades-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
