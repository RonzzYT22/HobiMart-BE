<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create();
    }

    // upload satu gambar
    public function test_upload_satu_gambar(): void
    {
        $file = UploadedFile::fake()->image('produk.jpg', 800, 600);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'url', 'originalName', 'size', 'width', 'height'])
            ->assertJsonPath('originalName', 'produk.jpg');
    }

    // upload multiple gambar
    public function test_upload_multiple_gambar(): void
    {
        $files = [
            UploadedFile::fake()->image('foto1.jpg'),
            UploadedFile::fake()->image('foto2.jpg'),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/upload/multiple', [
                'files' => $files,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['files'])
            ->assertJsonCount(2, 'files');
    }

    // harus login untuk upload
    public function test_upload_harus_login(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    // file bukan gambar ditolak
    public function test_file_bukan_gambar_ditolak(): void
    {
        $file = UploadedFile::fake()->create('dokumen.pdf', 500);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    // file terlalu besar
    public function test_file_terlalu_besar_ditolak(): void
    {
        $file = UploadedFile::fake()->image('besar.jpg')->size(20000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    // upload tanpa file
    public function test_upload_tanpa_file(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/upload', []);

        $response->assertStatus(422);
    }
}