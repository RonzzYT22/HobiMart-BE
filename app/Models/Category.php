<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'color',
        'count',
    ];

    // 5 kategori utama sesuai data frontend
    public const CATEGORIES = [
        ['name' => 'Trading Cards',   'icon' => '🃏', 'color' => 'from-orange-500 to-red-500',     'count' => 0],
        ['name' => 'Gundam & Gunpla', 'icon' => '🤖', 'color' => 'from-blue-500 to-indigo-500',    'count' => 0],
        ['name' => 'Figures',         'icon' => '🧸', 'color' => 'from-purple-500 to-pink-500',    'count' => 0],
        ['name' => 'Collectibles',    'icon' => '💎', 'color' => 'from-amber-500 to-yellow-500',   'count' => 0],
        ['name' => 'Accessories',     'icon' => '🛠️', 'color' => 'from-green-500 to-emerald-500',  'count' => 0],
    ];

    // banyak produk di kategori ini
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category', 'name');
    }
}