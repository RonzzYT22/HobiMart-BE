<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class AuthControllerTest extends TestCase
{
    public function test_register_returns_access_and_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'avatar',
                    'verified_collector',
                    'stats'
                ]
            ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_with_phone(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'refresh_token', 'user']);
    }

    public function test_register_validation_errors(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'invalid-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'message', 'fields']]);
    }

    public function test_login_returns_access_and_refresh_token(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user' => ['id', 'name', 'email']
            ]);
    }

    public function test_login_with_phone(): void
    {
        User::factory()->create([
            'phone' => '081234567890',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '081234567890',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token', 'user']);
    }

    public function test_login_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_refresh_with_valid_refresh_token(): void
    {
        $user = User::factory()->create();
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $refreshToken,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_refresh_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_refresh_with_access_token_fails(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_logout_invalidates_refresh_token(): void
    {
        $user = User::factory()->create();
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $refreshToken,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200);

        // Try to use the same refresh token again
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $refreshToken,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user_with_stats(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'phone',
                'avatar',
                'verified_collector',
                'stats' => [
                    'products_count',
                    'orders_count',
                    'wishlist_count',
                    'collection_count'
                ]
            ]);
    }

    public function test_me_requires_auth(): void
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);
    }

    public function test_patch_me_updates_profile(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->patchJson('/api/me', [
            'name' => 'Updated Name',
            'avatar' => 'https://example.com/avatar.jpg',
            'preferences' => ['theme' => 'dark', 'notifications' => true],
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('avatar', 'https://example.com/avatar.jpg')
            ->assertJsonPath('preferences.theme', 'dark');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }
}