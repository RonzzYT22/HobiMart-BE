<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class OrderTest extends TestCase
{
    protected User $user;
    protected Product $product1;
    protected Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
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

    // buat pesanan baru
    public function test_buat_pesanan_baru(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity' => 2],
                    ['product_id' => $this->product2->id, 'quantity' => 1],
                ],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '08123456789',
                    'address1' => 'Jl. Merdeka 123',
                    'address2' => '',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'postalCode' => '12345',
                ],
                'delivery' => 'regular',
                'payment_method' => 'qris',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'order' => ['id', 'orderNumber', 'status', 'subtotal', 'shipping', 'total'],
                'payment' => ['method', 'status', 'amount'],
            ])
            ->assertJsonPath('order.status', 'Placed')
            ->assertJsonPath('order.total', 720000)
            ->assertJsonPath('order.paymentStatus', 'Unpaid');
    }

    // harus login untuk buat pesanan
    public function test_buat_pesanan_harus_login(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'shipping_address' => [
                'fullName' => 'Budi',
                'phone' => '08123456789',
                'address1' => 'Jl. Merdeka',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'postalCode' => '12345',
            ],
            'delivery' => 'regular',
            'payment_method' => 'qris',
        ]);

        $response->assertStatus(401);
    }

    // order number format benar
    public function test_order_number_format(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'regular',
                'payment_method' => 'qris',
            ]);

        $response->assertStatus(201);
        $orderNumber = $response->json('order.orderNumber');
        $this->assertStringStartsWith('HM-ORD-', $orderNumber);
    }

    // daftar pesanan user
    public function test_daftar_pesanan(): void
    {
        // buat pesanan dulu
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'regular',
                'payment_method' => 'qris',
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure(['items'])
            ->assertJsonCount(1, 'items');
    }

    // detail pesanan
    public function test_detail_pesanan(): void
    {
        $order = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'express',
                'payment_method' => 'bank',
            ]);

        $orderNumber = $order->json('order.orderNumber');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/orders/' . $orderNumber);

        $response->assertStatus(200)
            ->assertJsonPath('orderNumber', $orderNumber)
            ->assertJsonPath('delivery', 'express')
            ->assertJsonPath('paymentMethod', 'bank');
    }

    // pembayaran dummy
    public function test_bayar_pesanan(): void
    {
        $order = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'regular',
                'payment_method' => 'qris',
            ]);

        $orderNumber = $order->json('order.orderNumber');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/' . $orderNumber . '/pay');

        $response->assertStatus(200)
            ->assertJsonPath('paid', true)
            ->assertJsonPath('paymentStatus', 'Paid');
    }

    // tidak bisa bayar 2x
    public function test_tidak_bisa_bayar_dua_kali(): void
    {
        $order = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'regular',
                'payment_method' => 'qris',
            ]);

        $orderNumber = $order->json('order.orderNumber');

        // bayar pertama
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/' . $orderNumber . '/pay');

        // bayar kedua
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/' . $orderNumber . '/pay');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_PAID');
    }

    // tracking pesanan (publik)
    public function test_tracking_pesanan(): void
    {
        $order = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'sameday',
                'payment_method' => 'qris',
            ]);

        $orderNumber = $order->json('order.orderNumber');

        // tracking bisa diakses tanpa login
        $response = $this->getJson('/api/orders/tracking/' . $orderNumber);

        $response->assertStatus(200)
            ->assertJsonPath('orderNumber', $orderNumber)
            ->assertJsonPath('delivery', 'sameday');
    }

    // shipping cost correct
    public function test_ongkir_regular(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'regular',
                'payment_method' => 'qris',
            ]);

        $response->assertJsonPath('order.shipping', 20000);
        $response->assertJsonPath('order.subtotal', 100000);
        $response->assertJsonPath('order.total', 120000);
    }

    public function test_ongkir_express(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'shipping_address' => [
                    'fullName' => 'Budi',
                    'phone' => '0812',
                    'address1' => 'Jl. A',
                    'city' => 'JKT',
                    'province' => 'DKI',
                    'postalCode' => '11111',
                ],
                'delivery' => 'express',
                'payment_method' => 'qris',
            ]);

        $response->assertJsonPath('order.shipping', 45000);
        $response->assertJsonPath('order.total', 145000);
    }
}