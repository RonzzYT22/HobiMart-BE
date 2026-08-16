<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeIn extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'offer_description',
        'offer_item_name',
        'offer_item_condition',
        'offer_images',
        'status',
        'note',
    ];

    protected $casts = [
        'offer_images' => 'array',
    ];

    // relasi ke user yang menawar
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // relasi ke produk yang ditawar
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}