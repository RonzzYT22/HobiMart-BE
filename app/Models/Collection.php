<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'condition',
        'grade',
        'purchase_price',
        'purchase_date',
        'notes',
        'images',
        'is_public',
    ];

    protected $casts = [
        'images' => 'array',
        'purchase_price' => 'integer',
        'purchase_date' => 'date',
        'is_public' => 'boolean',
    ];

    // relasi ke pemilik koleksi
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // relasi ke produk
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
