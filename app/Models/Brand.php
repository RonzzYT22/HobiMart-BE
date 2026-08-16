<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    // daftar brand yang dipakai frontend
    public const BRANDS = [
        'Bandai',
        'Pokémon',
        'Yu-Gi-Oh!',
        'One Piece Card Game',
        'Hot Toys',
        'Megahouse',
        'Tamashii Nations',
        'Good Smile Company',
        'Kotobukiya',
        'Funko',
        'Banpresto',
        'Wizards of the Coast',
    ];
}