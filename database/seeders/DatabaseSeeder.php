<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    // seed data awal untuk testing
    public function run(): void
    {
        // seller dengan data lengkap
        User::factory()->create([
            'name' => 'HobiMart Official',
            'email' => 'admin@hobimart.com',
            'is_verified_seller' => true,
            'verified_collector' => true,
            'rating' => 4.85,
            'positive_rate' => 98,
            'trades_count' => 150,
            'total_sales' => 2500,
        ]);

        // beberapa seller lain
        User::factory(5)->create([
            'is_verified_seller' => true,
            'rating' => fake()->randomFloat(2, 3.5, 5.0),
            'positive_rate' => fake()->numberBetween(70, 100),
            'trades_count' => fake()->numberBetween(5, 100),
            'total_sales' => fake()->numberBetween(20, 500),
        ]);

        // user biasa
        User::factory(10)->create();

        // produk dummy
        Product::factory(50)->create();
    }
}
