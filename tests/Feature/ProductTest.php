<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProductTest extends TestCase
{
    // bikin satu produk contoh untuk testing
    protected function buatProdukContoh(): Product
    {
        $user = User::factory()->create();

        return Product::create([
            'name' => 'Charizard EX',
            'category' => 'Trading Cards',
            'subcategory' => 'Pokémon',
            'brand' => 'Pokémon',
            'series' => 'Scarlet & Violet',
            'item_type' => 'Trading Card',
            'language' => 'Japanese',
            'year' => 2025,
            'condition' => 'Near Mint',
            'verified' => true,
            'stock' => 12,
            'price' => 1250000,
            'original_price' => 1500000,
            'discount' => 17,
            'rating' => 4.9,
            'review_count' => 128,
            'sold' => 234,
            'image' => 'https://example.com/charizard.jpg',
            'images' => [['url' => 'https://example.com/front.jpg', 'label' => 'Front']],
            'badges' => ['RARE', 'VERIFIED'],
            'description' => 'Charizard EX dari seri Scarlet & Violet.',
            'trade_available' => true,
            'condition_scores' => ['overall' => 90],
            'seller_id' => $user->id,
        ]);
    }

    // daftar produk dengan paginasi
    public function test_index_menampilkan_produk_dengan_paginasi(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products?page=1&limit=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'total',
                'page',
                'limit',
                'hasMore',
            ])
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('hasMore', false);
    }

    // filter berdasarkan kategori
    public function test_index_dengan_filter_kategori(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products?category=Trading+Cards');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'items');
    }

    // sort harga termurah
    public function test_index_dengan_sort_harga_asc(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products?sort=price-asc');

        $response->assertStatus(200)
            ->assertJsonFragment(['price' => 1250000]);
    }

    // pencarian produk
    public function test_pencarian_produk(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products?q=charizard');

        $response->assertStatus(200);
    }

    // detail produk pakai SKU
    public function test_show_menampilkan_satu_produk(): void
    {
        $product = $this->buatProdukContoh();

        $response = $this->getJson('/api/products/'.$product->sku);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'category',
                'seller',
            ])
            ->assertJsonPath('id', $product->sku)
            ->assertJsonPath('name', 'Charizard EX');
    }

    // produk tidak ditemukan
    public function test_show_404_untuk_produk_tidak_ada(): void
    {
        $response = $this->getJson('/api/products/HM-9999');

        $response->assertStatus(404)
            ->assertJsonStructure(['error']);
    }

    // buat produk baru
    public function test_buat_produk_baru(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('access-token')->plainTextToken;

        $data = [
            'name' => 'Gundam Kit Baru',
            'category' => 'Gundam & Gunpla',
            'subcategory' => 'High Grade',
            'brand' => 'Bandai',
            'price' => 500000,
            'stock' => 10,
            'condition' => 'Mint',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/products', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'price']);
    }

    // update harga produk
    public function test_update_produk(): void
    {
        $product = Product::factory()->create();
        $token = $product->seller->createToken('access-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->patchJson('/api/products/'.$product->sku, [
            'price' => 750000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('price', 750000);
    }

    // hapus produk (soft delete)
    public function test_hapus_produk(): void
    {
        $product = Product::factory()->create();
        $token = $product->seller->createToken('access-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/api/products/'.$product->sku);

        $response->assertStatus(200);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // endpoint produk unggulan
    public function test_endpoint_featured(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products/featured');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'trending',
                'newArrivals',
                'rareFinds',
                'recommendations',
            ]);
    }

    // endpoint flash deals
    public function test_endpoint_flash_deals(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products/flash-deals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'total',
                'deadline',
            ]);
    }

    // endpoint price drops
    public function test_endpoint_price_drops(): void
    {
        $this->buatProdukContoh();

        $response = $this->getJson('/api/products/price-drops');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'total',
            ]);
    }
}