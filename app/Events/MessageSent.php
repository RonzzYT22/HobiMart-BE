<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('sender:id,name,avatar');
    }

    // broadcast ke channel percakapan
    public function broadcastOn(): array
    {
        return [
            new Channel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id' => $this->message->id,
            'conversationId' => $this->message->conversation_id,
            'senderId' => $this->message->sender_id,
            'senderName' => $sender->name ?? 'Unknown',
            'senderAvatar' => $sender->avatar ?? null,
            'text' => $this->message->text,
            'attachment' => $this->message->attachment,
            'createdAt' => $this->message->created_at?->toISOString(),
        ];
    }
}