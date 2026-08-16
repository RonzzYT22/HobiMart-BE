<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'active',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'min_purchase' => 'integer',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    // cek apakah promo masih valid
    public function isValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->max_uses > 0 && $this->used_count >= $this->max_uses) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until->endOfDay())) {
            return false;
        }

        return true;
    }

    // hitung diskon
    public function calculateDiscount(int $total): int
    {
        if ($total < $this->min_purchase) {
            return 0;
        }

        if ($this->discount_type === 'percent') {
            return (int) min(round($total * $this->discount_value / 100), $total);
        }

        return min($this->discount_value, $total);
    }
}