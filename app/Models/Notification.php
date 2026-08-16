<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'payload',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    // relasi ke user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // cek apakah sudah dibaca
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}