<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'subcategory',
        'brand',
        'series',
        'item_type',
        'language',
        'year',
        'condition',
        'verified',
        'stock',
        'price',
        'original_price',
        'discount',
        'rating',
        'review_count',
        'sold',
        'image',
        'images',
        'badges',
        'description',
        'trade_available',
        'condition_scores',
        'seller_id',
    ];

    protected $casts = [
        'images' => 'array',
        'badges' => 'array',
        'condition_scores' => 'array',
        'verified' => 'boolean',
        'trade_available' => 'boolean',
        'price' => 'integer',
        'original_price' => 'integer',
        'discount' => 'integer',
        'stock' => 'integer',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'sold' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}