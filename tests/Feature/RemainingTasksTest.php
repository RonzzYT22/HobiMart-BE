<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class RemainingTasksTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // === Task 15: Community ===
    public function test_community_list_posts(): void
    {
        $response = $this->getJson('/api/community/posts');
        $response->assertStatus(200);
    }

    public function test_community_create_post(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/community/posts', [
                'title' => 'Test Post',
                'content' => 'Ini konten postingan test.',
                'category' => 'Diskusi',
            ]);

        $response->assertStatus(201)->assertJsonStructure(['id']);
    }

    // === Task 16: Admin Dashboard ===
    public function test_admin_stats(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'totalUsers', 'totalProducts', 'totalOrders',
                'totalRevenue', 'pendingOrders', 'recentOrders',
            ]);
    }

    public function test_admin_verify_seller(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/admin/verify-seller/' . $seller->id);

        $response->assertStatus(200)->assertJson(['verified' => true]);
    }

    // === Task 17: Seller Dashboard ===
    public function test_seller_stats(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/seller/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['totalProducts', 'totalSold', 'revenue', 'products']);
    }

    // === Task 18: Shipping Labels ===
    public function test_shipping_label_not_found(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/INVALID/shipping-label');

        $response->assertStatus(404);
    }

    // === Task 20: Geo ===
    public function test_geo_provinces(): void
    {
        $response = $this->getJson('/api/geo/provinces');
        $response->assertStatus(200)->assertJsonCount(10);
    }

    public function test_geo_cities(): void
    {
        $response = $this->getJson('/api/geo/provinces/1/cities');
        $response->assertStatus(200)->assertJsonCount(5);
    }
}