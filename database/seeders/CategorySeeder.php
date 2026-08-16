<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    // seed 5 kategori sesuai data frontend
    public function run(): void
    {
        foreach (Category::CATEGORIES as $data) {
            Category::updateOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}