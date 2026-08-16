<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    // kolom yang bisa diisi
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'text',
        'attachment',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // relasi ke percakapan
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // relasi ke pengirim pesan
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}