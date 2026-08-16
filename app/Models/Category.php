<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'color',
        'count',
    ];

    // banyak produk di kategori ini
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // daftar semua kategori untuk filter sidebar
    public static function getList(): Collection
    {
        return self::orderBy('name')->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon,
                'color' => $cat->color,
                'count' => (int) $cat->count,
            ];
        });
    }
}
