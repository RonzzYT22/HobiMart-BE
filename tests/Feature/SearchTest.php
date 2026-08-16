<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use App\Services\SearchService;

class SearchTest extends TestCase
{
    // bikin produk contoh untuk testing
    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        // produk trading cards
        Product::create([
            'name' => 'Charizard GX',
            'category' => 'Trading Cards',
            'subcategory' => 'Pokémon',
            'brand' => 'Pokémon',
            'condition' => 'Near Mint',
            'price' => 100000,
            'stock' => 10,
            'seller_id' => $user->id,
        ]);

        // produk gundam
        Product::create([
            'name' => 'Gundam RX-78-2',
            'category' => 'Gundam & Gunpla',
            'subcategory' => 'Master Grade',
            'brand' => 'Bandai',
            'condition' => 'Mint',
            'price' => 500000,
            'stock' => 5,
            'seller_id' => $user->id,
        ]);

        // produk figure
        Product::create([
            'name' => 'Nendoroid Hatsune Miku',
            'category' => 'Figures',
            'subcategory' => 'Nendoroid',
            'brand' => 'Good Smile Company',
            'condition' => 'Excellent',
            'price' => 350000,
            'stock' => 3,
            'seller_id' => $user->id,
        ]);
    }

    // pencarian dengan kata kunci langsung
    public function test_pencarian_dengan_kata_kunci(): void
    {
        $response = $this->getJson('/api/products?q=charizard');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Charizard GX');
    }

    // pencarian dengan typo (toleransi kesalahan ketik)
    public function test_pencarian_dengan_typo(): void
    {
        // "charzard" typo dari "charizard"
        $response = $this->getJson('/api/products?q=charzard');

        $response->assertStatus(200);
    }

    // pencarian dengan sinonim (singkatan)
    public function test_pencarian_dengan_sinonim(): void
    {
        // "mg" => "master grade"
        $response = $this->getJson('/api/products?q=mg');

        $response->assertStatus(200);
    }

    // sinonim "gunpla" => "gundam"
    public function test_sinonim_gunpla(): void
    {
        $response = $this->getJson('/api/products?q=gunpla');

        $response->assertStatus(200);
    }

    // sinonim "tcg" => "trading cards"
    public function test_sinonim_tcg(): void
    {
        $response = $this->getJson('/api/products?q=tcg');

        $response->assertStatus(200);
    }

    // sinonim "gsc" => "good smile company"
    public function test_sinonim_gsc(): void
    {
        $response = $this->getJson('/api/products?q=gsc');

        $response->assertStatus(200);
    }

    // pencarian kosong harus balikin semua produk
    public function test_pencarian_kosong(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'items');
    }

    // pencarian tidak ditemukan
    public function test_pencarian_tidak_ketemu(): void
    {
        $response = $this->getJson('/api/products?q=xyzabc123');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'items');
    }

    // endpoint istilah populer
    public function test_search_popular(): void
    {
        $response = $this->getJson('/api/search/popular');

        $response->assertStatus(200)
            ->assertJsonCount(12);
    }

    // test service sinonim langsung
    public function test_sinonim_expand_tcg(): void
    {
        $result = SearchService::expand('tcg');
        $this->assertStringContainsString('trading cards', $result);
    }

    public function test_sinonim_expand_gunpla(): void
    {
        $result = SearchService::expand('gunpla');
        $this->assertStringContainsString('gundam', $result);
    }

    public function test_sinonim_expand_multiple(): void
    {
        $result = SearchService::expand('tcg gunpla');
        $this->assertStringContainsString('trading cards', $result);
        $this->assertStringContainsString('gundam', $result);
    }

    public function test_sinonim_tanpa_duplikasi(): void
    {
        // "gundam" tidak ada di sinonim, jadi tidak ada ekspansi
        $result = SearchService::expand('gundam');
        $this->assertEquals('gundam', $result);
    }
}