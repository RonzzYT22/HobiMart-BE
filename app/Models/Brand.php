<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'logo',
    ];

    // daftar semua brand untuk filter sidebar
    public static function getList(): Collection
    {
        return self::orderBy('name')->get()->map(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo' => $brand->logo,
            ];
        });
    }
}
