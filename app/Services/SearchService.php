<?php

namespace App\Services;

class SearchService
{
    // singkatan yang sering dicari user
    protected static array $synonyms = [
        'tcg' => 'trading cards',
        'gunpla' => 'gundam',
        'figma' => 'figure',
        'mg' => 'master grade',
        'rg' => 'real grade',
        'hg' => 'high grade',
        'pg' => 'perfect grade',
        'sd' => 'super deformed',
        'ygo' => 'yu-gi-oh',
        'poke' => 'pokémon',
        'db' => 'dragon ball',
        'gsc' => 'good smile company',
        'limited' => 'limited edition',
        'mint' => 'near mint',
    ];

    // istilah populer yang sering dicari
    protected static array $popular = [
        'Charizard',
        'Gundam MG',
        'One Piece Card',
        'Marvel Figure',
        'Pokémon Card',
        'Rare',
        'Limited Edition',
        'Nendoroid',
        'Figma',
        'Gunpla',
        'Trading Cards',
        'Bandai',
    ];

    // ubah query pencarian dengan menambahkan sinonim
    public static function expand(string $query): string
    {
        $query = trim($query);
        $words = explode(' ', strtolower($query));

        $expanded = [];
        foreach ($words as $word) {
            $expanded[] = $word;
            // tambah sinonim kalau ada
            if (isset(static::$synonyms[$word])) {
                $expanded[] = static::$synonyms[$word];
            }
        }

        return implode(' ', array_unique($expanded));
    }

    // daftar istilah populer untuk suggestions
    public static function popular(): array
    {
        return static::$popular;
    }
}