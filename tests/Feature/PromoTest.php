<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\PromoCode;

class PromoTest extends TestCase
{
    // validasi kode promo persentase
    public function test_validasi_promo_persentase(): void
    {
        PromoCode::create([
            'code' => 'WELCOME10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_purchase' => 50000,
            'max_uses' => 100,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'active' => true,
        ]);

        $response = $this->postJson('/api/promo/validate', [
            'code' => 'WELCOME10',
            'total' => 100000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('discount', 10000)
            ->assertJsonPath('totalAfterDiscount', 90000);
    }

    // validasi kode promo fixed
    public function test_validasi_promo_fixed(): void
    {
        PromoCode::create([
            'code' => 'FLAT50K',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'min_purchase' => 200000,
            'max_uses' => 50,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'active' => true,
        ]);

        $response = $this->postJson('/api/promo/validate', [
            'code' => 'FLAT50K',
            'total' => 300000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('discount', 50000)
            ->assertJsonPath('totalAfterDiscount', 250000);
    }

    // kode promo tidak ditemukan
    public function test_kode_promo_tidak_ditemukan(): void
    {
        $response = $this->postJson('/api/promo/validate', [
            'code' => 'GAKADA',
            'total' => 100000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');
    }

    // kode promo expired
    public function test_kode_promo_expired(): void
    {
        PromoCode::create([
            'code' => 'EXPIRED20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'min_purchase' => 0,
            'valid_from' => now()->subDays(60),
            'valid_until' => now()->subDays(10),
            'active' => true,
        ]);

        $response = $this->postJson('/api/promo/validate', [
            'code' => 'EXPIRED20',
            'total' => 100000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'EXPIRED');
    }

    // minimal pembelian tidak terpenuhi
    public function test_minimal_pembelian(): void
    {
        PromoCode::create([
            'code' => 'WELCOME10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_purchase' => 50000,
            'active' => true,
        ]);

        $response = $this->postJson('/api/promo/validate', [
            'code' => 'WELCOME10',
            'total' => 30000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'MIN_PURCHASE');
    }

    // kode promo case-insensitive
    public function test_kode_promo_case_insensitive(): void
    {
        PromoCode::create([
            'code' => 'HELLO',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'active' => true,
        ]);

        $response = $this->postJson('/api/promo/validate', [
            'code' => 'hello',
            'total' => 50000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('valid', true);
    }
}