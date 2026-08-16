<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Laravel\Sanctum\Sanctum;

class WishlistTest extends TestCase
{
    protected User $user;
    protected Product $product1;
    protected Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $seller = User::factory()->create();

        $this->product1 = Product::create([
            'name' => 'Charizard Card',
            'category' => 'Trading Cards',
            'condition' => 'Near Mint',
            'price' => 100000,
            'stock' => 10,
            'seller_id' => $seller->id,
        ]);

        $this->product2 = Product::create([
            'name' => 'Gundam Kit',
            'category' => 'Gundam & Gunpla',
            'condition' => 'Mint',
            'price' => 500000,
            'stock' => 5,
            'seller_id' => $seller->id,
        ]);
    }

    // tambah produk ke wishlist
    public function test_tambah_produk_ke_wishlist(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/wishlist', [
                'product_id' => $this->product1->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['added' => true]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
        ]);
    }

    // tambah duplikat harus ditolak
    public function test_tambah_duplikat_ditolak(): void
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'added_at_price' => $this->product1->price,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/wishlist', [
                'product_id' => $this->product1->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_EXISTS');
    }

    // harus login untuk tambah wishlist
    public function test_tambah_wishlist_harus_login(): void
    {
        $response = $this->postJson('/api/wishlist', [
            'product_id' => $this->product1->id,
        ]);

        $response->assertStatus(401);
    }

    // lihat daftar wishlist
    public function test_lihat_daftar_wishlist(): void
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'added_at_price' => 150000, // harga awal lebih tinggi
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/wishlist');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'totalValue',
                'priceDropCount',
                'count',
            ])
            ->assertJsonPath('count', 1);
    }

    // price drop terdeteksi
    public function test_price_drop_terdeteksi(): void
    {
        // tambah wishlist dengan harga awal 150000, sekarang 100000
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'added_at_price' => 150000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/wishlist');

        $response->assertStatus(200)
            ->assertJsonPath('priceDropCount', 1)
            ->assertJsonPath('items.0.priceDropped', true)
            ->assertJsonPath('items.0.priceDropPercent', 33);
    }

    // hapus dari wishlist
    public function test_hapus_dari_wishlist(): void
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'added_at_price' => $this->product1->price,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/wishlist/' . $this->product1->id);

        $response->assertStatus(200)
            ->assertJson(['removed' => true]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
        ]);
    }

    // cek produk yang di-wishlist
    public function test_cek_produk_di_wishlist(): void
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'added_at_price' => $this->product1->price,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/wishlist/check?product_ids=' . $this->product1->id . ',' . $this->product2->id);

        $response->assertStatus(200)
            ->assertJsonPath('productIds.0', $this->product1->id)
            ->assertJsonCount(1, 'productIds');
    }

    // hapus produk yang tidak ada di wishlist
    public function test_hapus_produk_tidak_ada(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/wishlist/9999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // wishlist kosong
    public function test_wishlist_kosong(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/wishlist');

        $response->assertStatus(200)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('priceDropCount', 0);
    }
}