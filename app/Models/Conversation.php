<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    // kolom yang bisa diisi
    protected $fillable = [
        'user_a_id',
        'user_b_id',
    ];

    // relasi ke user pertama
    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    // relasi ke user kedua
    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    // relasi ke semua pesan di percakapan ini
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}