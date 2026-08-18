<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeController extends Controller
{
    // daftar trade user (sebagai initiator/receiver)
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');
        $role = $request->query('role'); // 'initiator' | 'receiver' | 'all'

        $query = Trade::with([
            'initiator:id,name,avatar,rating,positive_rate',
            'receiver:id,name,avatar,rating,positive_rate',
            'initiatorCollection.product:id,name,image,category,price,condition',
            'receiverCollection.product:id,name,image,category,price,condition',
        ])->where(function ($q) use ($user) {
            $q->where('initiator_id', $user->id)
              ->orWhere('receiver_id', $user->id);
        });

        if ($role === 'initiator') {
            $query->where('initiator_id', $user->id);
        } elseif ($role === 'receiver') {
            $query->where('receiver_id', $user->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $query->orderByDesc('created_at');

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get()->map(function ($t) use ($user) {
            $isInitiator = $t->isInitiator($user->id);
            return [
                'id' => $t->id,
                'status' => $t->status,
                'cashDifference' => (int) $t->cash_difference,
                'myRole' => $isInitiator ? 'initiator' : 'receiver',
                'initiator' => [
                    'id' => $t->initiator->id,
                    'name' => $t->initiator->name,
                    'avatar' => $t->initiator->avatar,
                    'rating' => $t->initiator->rating,
                    'positiveRate' => $t->initiator->positive_rate,
                ],
                'receiver' => [
                    'id' => $t->receiver->id,
                    'name' => $t->receiver->name,
                    'avatar' => $t->receiver->avatar,
                    'rating' => $t->receiver->rating,
                    'positiveRate' => $t->receiver->positive_rate,
                ],
                'initiatorItem' => $t->initiatorCollection ? [
                    'id' => $t->initiatorCollection->id,
                    'product' => $t->initiatorCollection->product ? [
                        'id' => $t->initiatorCollection->product->id,
                        'name' => $t->initiatorCollection->product->name,
                        'image' => $t->initiatorCollection->product->image,
                        'category' => $t->initiatorCollection->product->category,
                        'price' => (int) $t->initiatorCollection->product->price,
                        'condition' => $t->initiatorCollection->product->condition,
                    ] : null,
                    'condition' => $t->initiatorCollection->condition,
                    'grade' => $t->initiatorCollection->grade,
                ] : null,
                'receiverItem' => $t->receiverCollection ? [
                    'id' => $t->receiverCollection->id,
                    'product' => $t->receiverCollection->product ? [
                        'id' => $t->receiverCollection->product->id,
                        'name' => $t->receiverCollection->product->name,
                        'image' => $t->receiverCollection->product->image,
                        'category' => $t->receiverCollection->product->category,
                        'price' => (int) $t->receiverCollection->product->price,
                        'condition' => $t->receiverCollection->product->condition,
                    ] : null,
                    'condition' => $t->receiverCollection->condition,
                    'grade' => $t->receiverCollection->grade,
                ] : null,
                'initiatorShippedAt' => $t->initiator_shipped_at?->toISOString(),
                'receiverShippedAt' => $t->receiver_shipped_at?->toISOString(),
                'initiatorTracking' => $t->initiator_tracking,
                'receiverTracking' => $t->receiver_tracking,
                'completedAt' => $t->completed_at?->toISOString(),
                'disputeReason' => $t->dispute_reason,
                'createdAt' => $t->created_at?->toISOString(),
                'updatedAt' => $t->updated_at?->toISOString(),
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

    // buat trade offer
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'initiator_collection_id' => ['required', 'integer', 'exists:collections,id'],
            'receiver_collection_id' => ['nullable', 'integer', 'exists:collections,id'],
            'receiver_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'cash_difference' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        if ($request->receiver_id === $user->id) {
            return response()->json([
                'error' => ['code' => 'SELF_TRADE', 'message' => 'Tidak bisa trade dengan diri sendiri.'],
            ], 422);
        }

        $initiatorCollection = Collection::with('product')->findOrFail($request->initiator_collection_id);

        if ($initiatorCollection->user_id !== $user->id) {
            return response()->json([
                'error' => ['code' => 'NOT_YOUR_ITEM', 'message' => 'Item yang Anda tawarkan bukan milik Anda.'],
            ], 422);
        }

        // receiver_collection_id atau receiver_product_id — setidaknya satu harus ada
        $receiverCollectionId = null;
        if ($request->receiver_collection_id) {
            $receiverCollection = Collection::with('product')->findOrFail($request->receiver_collection_id);
            if ($receiverCollection->user_id !== $request->receiver_id) {
                return response()->json([
                    'error' => ['code' => 'NOT_THEIR_ITEM', 'message' => 'Item yang diminta bukan milik penerima.'],
                ], 422);
            }
            $receiverCollectionId = $receiverCollection->id;
        } elseif ($request->receiver_product_id) {
            // cari koleksi receiver yang punya produk itu
            $receiverCollection = Collection::where('user_id', $request->receiver_id)
                ->where('product_id', $request->receiver_product_id)
                ->first();
            if ($receiverCollection) {
                $receiverCollectionId = $receiverCollection->id;
            }
            // kalau receiver belum punya di koleksi, tetap lanjut — receiver bisa add nanti
        }

        if (! $receiverCollectionId && ! $request->receiver_product_id) {
            return response()->json([
                'error' => ['code' => 'NO_TARGET', 'message' => 'Harus menentukan item receiver (collection_id atau product_id).'],
            ], 422);
        }

        // cek item initiator tidak dalam trade aktif
        $activeTrade = Trade::where(function ($q) use ($initiatorCollection) {
            $q->where('initiator_collection_id', $initiatorCollection->id)
              ->orWhere('receiver_collection_id', $initiatorCollection->id);
        })->whereIn('status', ['pending', 'negotiating', 'agreed'])->exists();

        if ($activeTrade) {
            return response()->json([
                'error' => ['code' => 'ITEM_IN_ACTIVE_TRADE', 'message' => 'Item Anda sudah dalam trade aktif.'],
            ], 422);
        }

        $trade = Trade::create([
            'initiator_id' => $user->id,
            'receiver_id' => $request->receiver_id,
            'initiator_collection_id' => $initiatorCollection->id,
            'receiver_collection_id' => $receiverCollectionId,
            'cash_difference' => $request->cash_difference ?? 0,
            'status' => 'pending',
        ]);

        return response()->json([
            'id' => $trade->id,
            'status' => $trade->status,
            'message' => 'Trade offer dikirim.',
        ], 201);
    }

    // detail trade
    public function show(Request $request, int $id): JsonResponse
    {
        $trade = Trade::with([
            'initiator:id,name,avatar,rating,positive_rate,phone',
            'receiver:id,name,avatar,rating,positive_rate,phone',
            'initiatorCollection.product:id,name,image,category,price,condition,description,brand,series',
            'receiverCollection.product:id,name,image,category,price,condition,description,brand,series',
        ])->find($id);

        if (! $trade) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.'],
            ], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.'],
            ], 403);
        }

        return response()->json([
            'id' => $trade->id,
            'status' => $trade->status,
            'cashDifference' => (int) $trade->cash_difference,
            'myRole' => $trade->isInitiator($user->id) ? 'initiator' : 'receiver',
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
            'canTransitionTo' => array_keys($trade->allowedTransitions[$trade->status] ?? []),
            'createdAt' => $trade->created_at?->toISOString(),
            'updatedAt' => $trade->updated_at?->toISOString(),
        ]);
    }

    // negosiasi (counter offer - ubah cash_difference)
    public function negotiate(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        if (! $trade->canTransitionTo('negotiating')) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Trade tidak bisa dinegosiasi saat ini.']], 422);
        }

        $request->validate([
            'cash_difference' => ['required', 'integer'],
        ]);

        $trade->cash_difference = $request->cash_difference;
        $trade->transitionTo('negotiating');

        // TODO: notifikasi ke lawan

        return response()->json(['status' => $trade->status, 'cashDifference' => (int) $trade->cash_difference]);
    }

    // setuju (dari receiver atau initiator setelah negosiasi)
    public function agree(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        if (! $trade->canTransitionTo('agreed')) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Trade tidak bisa disetujui saat ini.']], 422);
        }

        $trade->transitionTo('agreed');

        // TODO: notifikasi ke lawan, kirim instruksi pengiriman

        return response()->json(['status' => $trade->status, 'message' => 'Trade disetujui. Siapkan pengiriman.']);
    }

    // kirim barang (upload tracking)
    public function ship(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        $isInitiator = $trade->isInitiator($user->id);
        $targetStatus = $isInitiator ? 'shipped_initiator' : 'shipped_receiver';

        if (! $trade->canTransitionTo($targetStatus)) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Tidak bisa kirim barang saat ini.']], 422);
        }

        $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        if ($isInitiator) {
            $trade->initiator_tracking = $request->tracking_number;
            $trade->transitionTo('shipped_initiator');
        } else {
            $trade->receiver_tracking = $request->tracking_number;
            $trade->transitionTo('shipped_receiver');
        }

        // cek apakah kedua pihak sudah kirim -> auto complete setelah konfirmasi
        if ($trade->fresh()->bothShipped()) {
            // status tetap shipped_* sampai konfirmasi terima
        }

        // TODO: notifikasi ke lawan

        return response()->json([
            'status' => $trade->status,
            'message' => 'Nomor resi dikirim. Menunggu konfirmasi penerima.',
        ]);
    }

    // konfirmasi terima barang
    public function confirmReceived(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        // hanya bisa confirm received jika lawan sudah shipped
        $isInitiator = $trade->isInitiator($user->id);
        $otherShipped = $isInitiator ? $trade->receiver_shipped_at : $trade->initiator_shipped_at;

        if (! $otherShipped) {
            return response()->json(['error' => ['code' => 'NOT_SHIPPED', 'message' => 'Lawan belum mengirim barang.']], 422);
        }

        // jika kedua pihak sudah confirm (implisit via bothShipped + ini), complete
        if ($trade->bothShipped() && $trade->canTransitionTo('completed')) {
            $trade->transitionTo('completed');

            // increment trades_count (hanya sekali, saat complete)
            $trade->initiator()->increment('trades_count');
            $trade->receiver()->increment('trades_count');
        }

        return response()->json([
            'status' => $trade->status,
            'message' => $trade->status === 'completed' ? 'Trade selesai!' : 'Konfirmasi terima dicatat.',
        ]);
    }

    // batalkan trade
    public function cancel(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        if (! $trade->canTransitionTo('cancelled')) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Trade tidak bisa dibatalkan saat ini.']], 422);
        }

        $trade->transitionTo('cancelled');

        // TODO: notifikasi ke lawan

        return response()->json(['status' => $trade->status, 'message' => 'Trade dibatalkan.']);
    }

    // buka dispute
    public function dispute(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        if (! $trade->canTransitionTo('disputed')) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION', 'message' => 'Trade tidak bisa didisputasikan saat ini.']], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $trade->dispute_reason = $request->reason;
        $trade->transitionTo('disputed');

        // TODO: notifikasi admin

        return response()->json(['status' => $trade->status, 'message' => 'Dispute dibuka. Admin akan meninjau.']);
    }

    // rating pasca-trade
    public function rate(Request $request, int $id): JsonResponse
    {
        $trade = Trade::find($id);

        if (! $trade) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Trade tidak ditemukan.']], 404);
        }

        if ($trade->status !== 'completed') {
            return response()->json(['error' => ['code' => 'NOT_COMPLETED', 'message' => 'Trade belum selesai.']], 422);
        }

        $user = $request->user();
        if (! $trade->involvesUser($user->id)) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak memiliki akses.']], 403);
        }

        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ]);

        $isInitiator = $trade->isInitiator($user->id);
        $target = $isInitiator ? $trade->receiver : $trade->initiator;

        // update rating target user (weighted average, trades_count sudah di-increment di confirmReceived)
        $oldCount = $target->trades_count - 1; // sebelum increment
        $newCount = $target->trades_count;
        $newRating = round((($target->rating * $oldCount) + $request->rating) / max($newCount, 1), 2);
        $positive = $request->rating >= 4 ? 1 : 0;
        $newPositiveRate = $oldCount > 0
            ? round((($target->positive_rate / 100 * $oldCount) + $positive) / $newCount * 100)
            : ($positive ? 100 : 0);

        $target->update([
            'trades_count' => $newCount,
            'rating' => $newRating,
            'positive_rate' => $newPositiveRate,
        ]);

        // TODO: simpan review ke tabel terpisah jika perlu

        return response()->json([
            'message' => 'Rating dikirim.',
            'targetRating' => $newRating,
        ]);
    }
}
