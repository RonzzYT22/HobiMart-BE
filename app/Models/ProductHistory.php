<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductHistory extends Model
{
    // nama tabelnya singular biar sama dengan migration
    protected $table = 'product_history';

    // kolom yang bisa diisi
    protected $fillable = [
        'product_id',
        'price',
        'previous_price',
        'discount',
        'recorded_at',
    ];

    protected $casts = [
        'price' => 'integer',
        'previous_price' => 'integer',
        'discount' => 'integer',
        'recorded_at' => 'datetime',
    ];

    // relasi ke produk
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}