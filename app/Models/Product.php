<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    public const ALLOWED_BADGES = ['RARE', 'LIMITED', 'VERIFIED', 'SALE', 'NEW', 'BEST SELLER'];
    public const CONDITIONS = ['Mint', 'Near Mint', 'Excellent', 'Good', 'Played', 'Damaged'];
    public const SKU_PREFIX = 'HM-';

    protected $fillable = [
        'sku', 'name', 'category', 'subcategory', 'brand', 'series',
        'item_type', 'language', 'year', 'condition', 'verified',
        'stock', 'price', 'original_price', 'discount', 'rating',
        'review_count', 'sold', 'image', 'images', 'badges',
        'description', 'trade_available', 'condition_scores', 'seller_id',
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

    // buat SKU otomatis saat produk baru dibuat
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $last = static::withTrashed()->latest('id')->first();
                $num = $last ? ((int) substr($last->sku, 3)) + 1 : 1;
                $product->sku = self::SKU_PREFIX . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // nama index TNTSearch
    public function searchableAs(): string
    {
        return 'products';
    }

    // field yang di-index untuk pencarian
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'brand' => $this->brand ?? '',
            'category' => $this->category,
            'subcategory' => $this->subcategory ?? '',
            'description' => $this->description ?? '',
            'series' => $this->series ?? '',
            'item_type' => $this->item_type ?? '',
        ];
    }

    // relasi ke seller
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    // riwayat harga untuk tracking price drop
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductHistory::class);
    }
}