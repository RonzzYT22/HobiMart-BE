<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\TradeIn;
use App\Models\User;

class TradeInTest extends TestCase
{
    protected User $user;
    protected User $seller;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seller = User::factory()->create(['is_verified_seller' => true]);

        $this->product = Product::create([
            'name' => 'Charizard Card',
            'category' => 'Trading Cards',
            'condition' => 'Near Mint',
            'price' => 100000,
            'stock' => 5,
            'seller_id' => $this->seller->id,
            'trade_available' => true,
        ]);
    }

    // ajukan trade-in
    public function test_ajukan_trade_in(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/trade-ins', [
                'product_id' => $this->product->id,
                'offer_item_name' => 'Gundam RX-78',
                'offer_item_condition' => 'Mint',
                'offer_description' => 'Mau tuker Gundam MG sama Charizard, minat?',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'status'])
            ->assertJsonPath('status', 'pending');
    }

    // tidak bisa trade produk sendiri
    public function test_tidak_bisa_trade_produk_sendiri(): void
    {
        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/trade-ins', [
                'product_id' => $this->product->id,
                'offer_item_name' => 'Test',
                'offer_item_condition' => 'Mint',
                'offer_description' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'OWN_PRODUCT');
    }

    // tidak bisa trade produk non-tradeable
    public function test_tidak_bisa_trade_non_tradeable(): void
    {
        $product = Product::create([
            'name' => 'No Trade',
            'category' => 'Figures',
            'condition' => 'Good',
            'price' => 50000,
            'stock' => 1,
            'seller_id' => $this->seller->id,
            'trade_available' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/trade-ins', [
                'product_id' => $product->id,
                'offer_item_name' => 'Test',
                'offer_item_condition' => 'Mint',
                'offer_description' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_TRADEABLE');
    }

    // daftar trade-in
    public function test_daftar_trade_in(): void
    {
        TradeIn::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'offer_item_name' => 'Test',
            'offer_item_condition' => 'Mint',
            'offer_description' => 'Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/trade-ins');

        $response->assertStatus(200)
            ->assertJsonStructure(['items'])
            ->assertJsonCount(1, 'items');
    }

    // terima trade-in
    public function test_terima_trade_in(): void
    {
        $tradeIn = TradeIn::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'offer_item_name' => 'Test',
            'offer_item_condition' => 'Mint',
            'offer_description' => 'Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->seller, 'sanctum')
            ->patchJson('/api/trade-ins/' . $tradeIn->id . '/accept');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'accepted');
    }

    // tolak trade-in
    public function test_tolak_trade_in(): void
    {
        $tradeIn = TradeIn::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'offer_item_name' => 'Test',
            'offer_item_condition' => 'Mint',
            'offer_description' => 'Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->seller, 'sanctum')
            ->patchJson('/api/trade-ins/' . $tradeIn->id . '/reject');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'rejected');
    }

    // harus login
    public function test_trade_in_harus_login(): void
    {
        $response = $this->postJson('/api/trade-ins', [
            'product_id' => 1,
            'offer_item_name' => 'Test',
            'offer_item_condition' => 'Mint',
            'offer_description' => 'Test',
        ]);

        $response->assertStatus(401);
    }
}