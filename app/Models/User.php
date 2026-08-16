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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'verified_collector',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'verified_collector' => 'boolean',
        ];
    }

    /**
     * Get the products where the user is the seller.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    /**
     * Get the orders for the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user's wishlist.
     */
    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the user's collection (products they own that are trade available).
     */
    public function collection(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id')
            ->where('trade_available', true);
    }

    /**
     * Get the conversations where the user is participant A.
     */
    public function conversationsAsA(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_a_id');
    }

    /**
     * Get the conversations where the user is participant B.
     */
    public function conversationsAsB(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_b_id');
    }

    /**
     * Get all conversations for the user.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversations', 'user_a_id', 'user_b_id')
            ->withTimestamps();
    }

    /**
     * Get the user's stats.
     */
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