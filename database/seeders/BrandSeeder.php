<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    use WithoutModelEvents;

    // seed brand sesuai data frontend
    public function run(): void
    {
        foreach (Brand::BRANDS as $name) {
            Brand::updateOrCreate(
                ['name' => $name],
                ['name' => $name, 'slug' => Str::slug($name)],
            );
        }
    }
}