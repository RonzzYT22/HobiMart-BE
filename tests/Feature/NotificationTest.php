<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Notification;
use App\Models\User;

class NotificationTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // daftar notifikasi
    public function test_daftar_notifikasi(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'order',
            'title' => 'Pesanan dikirim',
            'description' => 'Pesanan HM-ORD-001 sudah dikirim',
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'price',
            'title' => 'Harga turun',
            'description' => 'Charizard turun 15%',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure(['items', 'unreadCount'])
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('unreadCount', 2);
    }

    // filter berdasarkan type
    public function test_filter_berdasarkan_type(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'order',
            'title' => 'Order',
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'price',
            'title' => 'Price',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications?type=price');

        $response->assertJsonCount(1, 'items');
    }

    // filter unread only
    public function test_filter_unread(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'order',
            'title' => 'Order',
            'read_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'price',
            'title' => 'Price',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications?unread=1');

        $response->assertJsonCount(1, 'items')
            ->assertJsonPath('unreadCount', 1);
    }

    // tandai satu notifikasi dibaca
    public function test_tandai_satu_dibaca(): void
    {
        $n = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'order',
            'title' => 'Order',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/notifications/' . $n->id . '/read');

        $response->assertStatus(200)
            ->assertJson(['read' => true]);

        $this->assertNotNull(Notification::find($n->id)->read_at);
    }

    // tandai semua dibaca
    public function test_tandai_semua_dibaca(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'order', 'title' => 'A']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'order', 'title' => 'B']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson(['read' => true]);

        $this->assertEquals(0, Notification::whereNull('read_at')->count());
    }

    // harus login untuk akses notifikasi
    public function test_notifikasi_harus_login(): void
    {
        $response = $this->getJson('/api/notifications');
        $response->assertStatus(401);
    }
}