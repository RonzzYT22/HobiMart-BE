<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // daftar notifikasi user
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $type = $request->query('type');
        $unreadOnly = $request->boolean('unread');

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($type) {
            $query->where('type', $type);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->take(50)->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'description' => $n->description,
                'payload' => $n->payload,
                'readAt' => $n->read_at?->toISOString(),
                'createdAt' => $n->created_at?->toISOString(),
            ];
        });

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    // tandai satu notifikasi sudah dibaca
    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Notifikasi tidak ditemukan.',
                ],
            ], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['read' => true]);
    }

    // tandai semua notifikasi sudah dibaca
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['read' => true]);
    }
}