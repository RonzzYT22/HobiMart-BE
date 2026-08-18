<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // list user dengan filter & search
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $role = $request->query('role');
        $verified = $request->query('verified');
        $banned = $request->query('banned');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($verified !== null) {
            $query->where('is_verified_seller', $verified === 'true');
        }

        if ($banned !== null) {
            $query->where('is_banned', $banned === 'true');
        }

        $query->orderByDesc('created_at');

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $total = $query->count();
        $hasMore = ($page * $limit) < $total;
        $items = $query->forPage($page, $limit)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'avatar' => $u->avatar,
                'role' => $u->role,
                'isVerifiedSeller' => $u->is_verified_seller,
                'isBanned' => $u->is_banned ?? false,
                'rating' => $u->rating,
                'tradesCount' => $u->trades_count,
                'positiveRate' => $u->positive_rate,
                'createdAt' => $u->created_at?->toISOString(),
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

    // detail user + stats
    public function show(int $id): JsonResponse
    {
        $user = User::withCount(['products', 'orders', 'collections', 'tradesAsInitiator', 'tradesAsReceiver'])->find($id);

        if (! $user) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User tidak ditemukan.']], 404);
        }

        $revenue = Order::where('payment_status', 'Paid')
            ->whereHas('items', fn($q) => $q->whereIn('product_id', $user->products()->pluck('id')))
            ->sum('total');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $user->role,
            'isVerifiedSeller' => $user->is_verified_seller,
            'isBanned' => $user->is_banned ?? false,
            'rating' => $user->rating,
            'tradesCount' => $user->trades_count,
            'positiveRate' => $user->positive_rate,
            'stats' => [
                'products' => $user->products_count,
                'orders' => $user->orders_count,
                'collections' => $user->collections_count,
                'trades' => $user->trades_as_initiator_count + $user->trades_as_receiver_count,
                'revenue' => (int) $revenue,
            ],
            'createdAt' => $user->created_at?->toISOString(),
        ]);
    }

    // update user (ban, role, verify)
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User tidak ditemukan.']], 404);
        }

        $request->validate([
            'role' => ['nullable', 'string', 'in:user,admin'],
            'is_verified_seller' => ['boolean'],
            'is_banned' => ['boolean'],
        ]);

        $user->update($request->only(['role', 'is_verified_seller', 'is_banned']));

        return response()->json(['message' => 'User diperbarui.']);
    }

    // hapus user (soft delete)
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User tidak ditemukan.']], 404);
        }

        // cegah hapus admin
        if ($user->role === 'admin') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Tidak bisa menghapus admin.']], 422);
        }

        $user->delete();

        return response()->json(['deleted' => true]);
    }
}
