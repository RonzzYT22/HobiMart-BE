<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use App\Events\MessageSent;

class ConversationTest extends TestCase
{
    protected User $user;
    protected User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    // buat percakapan baru
    public function test_buat_percakapan(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/conversations', [
                'userId' => $this->other->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id']);
    }

    // daftar percakapan
    public function test_daftar_percakapan(): void
    {
        Conversation::create([
            'user_a_id' => min($this->user->id, $this->other->id),
            'user_b_id' => max($this->user->id, $this->other->id),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure(['items'])
            ->assertJsonCount(1, 'items');
    }

    // kirim pesan
    public function test_kirim_pesan(): void
    {
        Event::fake([MessageSent::class]);

        $conv = Conversation::create([
            'user_a_id' => min($this->user->id, $this->other->id),
            'user_b_id' => max($this->user->id, $this->other->id),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/conversations/' . $conv->id . '/messages', [
                'text' => 'Halo, ini pesan test',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'senderId', 'text', 'createdAt'])
            ->assertJsonPath('text', 'Halo, ini pesan test');

        Event::assertDispatched(MessageSent::class);
    }

    // lihat pesan
    public function test_lihat_pesan(): void
    {
        $conv = Conversation::create([
            'user_a_id' => min($this->user->id, $this->other->id),
            'user_b_id' => max($this->user->id, $this->other->id),
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->other->id,
            'text' => 'Halo dari other',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->user->id,
            'text' => 'Halo balasan',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/conversations/' . $conv->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'other', 'messages'])
            ->assertJsonCount(2, 'messages');
    }

    // unread count
    public function test_unread_count(): void
    {
        $conv = Conversation::create([
            'user_a_id' => min($this->user->id, $this->other->id),
            'user_b_id' => max($this->user->id, $this->other->id),
        ]);

        // other kirim 3 pesan
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $this->other->id, 'text' => '1']);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $this->other->id, 'text' => '2']);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $this->other->id, 'text' => '3']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/conversations');

        $response->assertJsonPath('items.0.unread', 3);
    }

    // harus login
    public function test_conversation_harus_login(): void
    {
        $response = $this->getJson('/api/conversations');
        $response->assertStatus(401);
    }

    // tidak bisa chat dengan diri sendiri
    public function test_tidak_bisa_chat_diri_sendiri(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/conversations', [
                'userId' => $this->user->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SAME_USER');
    }
}