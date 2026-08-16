<?php

namespace App\Models;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar',
        'verified_collector', 'preferences',
        'rating', 'positive_rate', 'trades_count', 'total_sales', 'is_verified_seller',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'verified_collector' => 'boolean',
            'is_verified_seller' => 'boolean',
            'rating' => 'decimal:2',
            'positive_rate' => 'integer',
            'trades_count' => 'integer',
            'total_sales' => 'integer',
        ];
    }

    // produk yang dijual user ini
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    // pesanan user
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // wishlist user
    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // koleksi user (produk trade available)
    public function collection(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id')
            ->where('trade_available', true);
    }

    public function conversationsAsA(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_a_id');
    }

    public function conversationsAsB(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_b_id');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversations', 'user_a_id', 'user_b_id')
            ->withTimestamps();
    }

    // statistik user untuk dashboard
    public function getStatsAttribute(): array
    {
        return [
            'products_count' => $this->products()->count(),
            'orders_count' => $this->orders()->count(),
            'wishlist_count' => $this->wishlist()->count(),
            'collection_count' => $this->collection()->count(),
        ];
    }
}
