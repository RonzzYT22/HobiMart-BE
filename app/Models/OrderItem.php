<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    // kolom yang bisa diisi
    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'quantity',
        'price',
        'image',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
    ];

    // relasi ke pesanan
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // relasi ke produk
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}