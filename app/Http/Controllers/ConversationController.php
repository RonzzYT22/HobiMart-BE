<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // daftar percakapan user
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where('user_a_id', $user->id)
            ->orWhere('user_b_id', $user->id)
            ->with(['userA', 'userB', 'messages' => function ($q) {
                $q->latest()->take(1);
            }])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($conv) use ($user) {
                // tentukan siapa lawan bicara
                $other = $conv->user_a_id === $user->id ? $conv->userB : $conv->userA;
                $lastMsg = $conv->messages->first();

                // hitung unread
                $unread = Message::where('conversation_id', $conv->id)
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->count();

                return [
                    'id' => $conv->id,
                    'other' => [
                        'id' => $other->id,
                        'name' => $other->name,
                        'avatar' => $other->avatar,
                    ],
                    'lastMessage' => $lastMsg ? [
                        'text' => $lastMsg->text,
                        'createdAt' => $lastMsg->created_at?->toISOString(),
                    ] : null,
                    'unread' => $unread,
                    'updatedAt' => $conv->updated_at?->toISOString(),
                ];
            });

        return response()->json(['items' => $conversations]);
    }

    // buat atau cari percakapan
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $otherId = $request->integer('userId');

        if ($user->id === $otherId) {
            return response()->json([
                'error' => ['code' => 'SAME_USER', 'message' => 'Tidak bisa chat dengan diri sendiri.'],
            ], 422);
        }

        // cari atau buat
        $a = min($user->id, $otherId);
        $b = max($user->id, $otherId);

        $conversation = Conversation::firstOrCreate([
            'user_a_id' => $a,
            'user_b_id' => $b,
        ]);

        return response()->json([
            'id' => $conversation->id,
        ], 201);
    }

    // lihat pesan di percakapan
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where(function ($q) use ($user) {
            $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id);
        })->with(['userA', 'userB'])->find($id);

        if (! $conversation) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Percakapan tidak ditemukan.'],
            ], 404);
        }

        $other = $conversation->user_a_id === $user->id ? $conversation->userB : $conversation->userA;

        $messages = Message::where('conversation_id', $id)
            ->with('sender:id,name,avatar')
            ->orderBy('created_at')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'senderId' => $msg->sender_id,
                    'senderName' => $msg->sender->name ?? 'Unknown',
                    'senderAvatar' => $msg->sender->avatar ?? null,
                    'text' => $msg->text,
                    'attachment' => $msg->attachment,
                    'readAt' => $msg->read_at?->toISOString(),
                    'createdAt' => $msg->created_at?->toISOString(),
                ];
            });

        // tandai semua pesan dari lawan sebagai dibaca
        Message::where('conversation_id', $id)
            ->where('sender_id', $other->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'id' => $conversation->id,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'avatar' => $other->avatar,
            ],
            'messages' => $messages,
        ]);
    }

    // kirim pesan
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'text' => ['required_without:attachment', 'string', 'max:2000'],
            'attachment' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        $conversation = Conversation::where(function ($q) use ($user) {
            $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id);
        })->find($id);

        if (! $conversation) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Percakapan tidak ditemukan.'],
            ], 404);
        }

        $message = Message::create([
            'conversation_id' => $id,
            'sender_id' => $user->id,
            'text' => $request->input('text'),
            'attachment' => $request->input('attachment'),
        ]);

        // update timestamp percakapan
        $conversation->touch();

        // broadcast via Reverb
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'id' => $message->id,
            'senderId' => $user->id,
            'senderName' => $user->name,
            'senderAvatar' => $user->avatar,
            'text' => $message->text,
            'attachment' => $message->attachment,
            'createdAt' => $message->created_at?->toISOString(),
        ], 201);
    }
}