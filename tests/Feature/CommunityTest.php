<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Post;
use App\Models\User;

class CommunityTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_daftar_postingan(): void
    {
        Post::create([
            'user_id' => $this->user->id,
            'title' => 'Halo Komunitas',
            'content' => 'Test konten',
            'category' => 'Diskusi',
        ]);

        $response = $this->getJson('/api/community/posts');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_detail_postingan(): void
    {
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Test',
            'content' => 'Konten',
            'category' => 'Tips',
        ]);

        $response = $this->getJson('/api/community/posts/' . $post->id);
        $response->assertStatus(200)->assertJsonPath('title', 'Test');
    }

    public function test_buat_postingan(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/community/posts', [
                'title' => 'Post Baru',
                'content' => 'Isi konten postingan',
                'category' => 'Showcase',
            ]);

        $response->assertStatus(201)->assertJsonStructure(['id']);
    }

    public function test_tambah_komentar(): void
    {
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Test',
            'content' => 'Konten',
            'category' => 'Diskusi',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/community/posts/' . $post->id . '/comments', [
                'content' => 'Komentar test',
            ]);

        $response->assertStatus(201)->assertJsonPath('content', 'Komentar test');
    }

    public function test_like_postingan(): void
    {
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Test',
            'content' => 'Konten',
            'category' => 'Diskusi',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/community/posts/' . $post->id . '/like');

        $response->assertStatus(200)->assertJsonPath('likes', 1);
    }
}