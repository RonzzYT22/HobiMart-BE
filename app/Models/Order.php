<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    // kolom yang bisa diisi
    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'items',
        'subtotal',
        'shipping',
        'total',
        'shipping_address',
        'payment_method',
        'payment_status',
        'courier',
        'tracking_number',
        'est_arrival',
        'timeline',
    ];

    // kolom json yang otomatis diubah jadi array
    protected $casts = [
        'items' => 'array',
        'shipping_address' => 'array',
        'timeline' => 'array',
        'subtotal' => 'integer',
        'shipping' => 'integer',
        'total' => 'integer',
        'est_arrival' => 'date',
    ];

    // relasi ke user yang pesan
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // relasi ke detail item pesanan
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}