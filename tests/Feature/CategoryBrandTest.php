<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\Brand;

class CategoryBrandTest extends TestCase
{
    // bikin produk untuk testing kategori/brand
    protected function buatProdukTest(string $category = 'Trading Cards', string $subcategory = 'Pokémon', string $brand = 'Bandai'): Product
    {
        $user = User::factory()->create();
        return Product::create([
            'name' => 'Produk Test '.fake()->word(),
            'category' => $category,
            'subcategory' => $subcategory,
            'brand' => $brand,
            'condition' => 'Near Mint',
            'price' => 100000,
            'stock' => 5,
            'seller_id' => $user->id,
        ]);
    }

    // ========================================
    // GET /categories
    // ========================================

    // harus balikin 5 kategori dengan icon, color, dan count live dari produk
    public function test_kategori_menampilkan_5_kategori_dengan_icon_color_dan_count(): void
    {
        // seed 5 kategori
        foreach (Category::CATEGORIES as $data) {
            Category::create($data);
        }

        // bikin produk di 3 kategori berbeda
        $this->buatProdukTest('Trading Cards', 'Pokémon', 'Bandai');
        $this->buatProdukTest('Trading Cards', 'One Piece', 'Bandai');
        $this->buatProdukTest('Trading Cards', 'Yu-Gi-Oh!', 'Konami');
        $this->buatProdukTest('Gundam & Gunpla', 'Master Grade', 'Bandai');
        $this->buatProdukTest('Gundam & Gunpla', 'High Grade', 'Bandai');
        $this->buatProdukTest('Figures', 'Nendoroid', 'Good Smile Company');

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(5)
            ->assertJsonStructure([
                '*' => ['name', 'icon', 'color', 'count'],
            ]);

        $data = $response->json();

        // cari kategori Trading Cards dan pastikan count = 3
        $tc = collect($data)->firstWhere('name', 'Trading Cards');
        $this->assertNotNull($tc, 'Kategori Trading Cards harus ada');
        $this->assertEquals('🃏', $tc['icon']);
        $this->assertEquals('from-orange-500 to-red-500', $tc['color']);
        $this->assertEquals(3, $tc['count'], 'Count Trading Cards harus 3 dari produk live');

        // Gundam = 2
        $gg = collect($data)->firstWhere('name', 'Gundam & Gunpla');
        $this->assertEquals(2, $gg['count']);

        // Figures = 1
        $fig = collect($data)->firstWhere('name', 'Figures');
        $this->assertEquals(1, $fig['count']);

        // Collectibles & Accessories = 0 (tidak ada produk)
        $col = collect($data)->firstWhere('name', 'Collectibles');
        $this->assertEquals(0, $col['count']);
        $acc = collect($data)->firstWhere('name', 'Accessories');
        $this->assertEquals(0, $acc['count']);
    }

    // ========================================
    // GET /categories/{name}/subcategories
    // ========================================

    // harus balikin daftar subcategory unik dari produk di kategori tsb
    public function test_subcategories_mengembalikan_daftar_unik(): void
    {
        // seed kategori
        foreach (Category::CATEGORIES as $data) {
            Category::create($data);
        }

        // produk Trading Cards dengan berbagai subcategory
        $this->buatProdukTest('Trading Cards', 'Pokémon', 'Bandai');
        $this->buatProdukTest('Trading Cards', 'Pokémon', 'Konami');
        $this->buatProdukTest('Trading Cards', 'One Piece', 'Bandai');
        $this->buatProdukTest('Trading Cards', 'Yu-Gi-Oh!', 'Konami');

        $response = $this->getJson('/api/categories/Trading%20Cards/subcategories');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonFragment(['Pokémon'])
            ->assertJsonFragment(['One Piece'])
            ->assertJsonFragment(['Yu-Gi-Oh!']);
    }

    // kategori tanpa produk harus balikin array kosong
    public function test_subcategories_kategori_tanpa_produk(): void
    {
        foreach (Category::CATEGORIES as $data) {
            Category::create($data);
        }

        $response = $this->getJson('/api/categories/Accessories/subcategories');

        $response->assertStatus(200)
            ->assertJsonCount(0);
    }

    // kategori tidak dikenal harus 404
    public function test_subcategories_kategori_tidak_dikenal_404(): void
    {
        foreach (Category::CATEGORIES as $data) {
            Category::create($data);
        }

        $response = $this->getJson('/api/categories/Unknown%20Category/subcategories');

        $response->assertStatus(404)
            ->assertJsonStructure(['error']);
    }

    // ========================================
    // GET /brands
    // ========================================

    // harus balikin daftar brand dengan count produk masing-masing
    public function test_brands_dengan_count_produk(): void
    {
        // seed brand
        foreach (Brand::BRANDS as $name) {
            Brand::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)]);
        }

        // produk dengan brand berbeda
        $this->buatProdukTest('Trading Cards', 'Pokémon', 'Bandai');
        $this->buatProdukTest('Trading Cards', 'One Piece', 'Bandai');
        $this->buatProdukTest('Trading Cards', 'Yu-Gi-Oh!', 'Konami');
        $this->buatProdukTest('Gundam & Gunpla', 'Master Grade', 'Bandai');
        $this->buatProdukTest('Figures', 'Nendoroid', 'Good Smile Company');

        $response = $this->getJson('/api/brands');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['name', 'count'],
            ]);

        $data = $response->json();

        // Bandai = 3 produk
        $bandai = collect($data)->firstWhere('name', 'Bandai');
        $this->assertNotNull($bandai);
        $this->assertEquals(3, $bandai['count']);

        // Konami = 1
        $konami = collect($data)->firstWhere('name', 'Konami');
        $this->assertEquals(1, $konami['count']);

        // Good Smile Company = 1
        $gsc = collect($data)->firstWhere('name', 'Good Smile Company');
        $this->assertEquals(1, $gsc['count']);
    }

    // brand tanpa produk tetap muncul dengan count 0
    public function test_brands_menampilkan_brand_tanpa_produk(): void
    {
        foreach (Brand::BRANDS as $name) {
            Brand::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)]);
        }

        $response = $this->getJson('/api/brands');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertGreaterThanOrEqual(12, count($data), 'Harus ada minimal 12 brand');

        // semua count harus 0
        foreach ($data as $brand) {
            $this->assertEquals(0, $brand['count'], "Brand '{$brand['name']}' harus count 0");
        }
    }
}