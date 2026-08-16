<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerResource extends JsonResource
{
    // format data seller sesuai kontrak frontend
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'rating' => (float) ($this->rating ?? 0),
            'totalSales' => (int) ($this->total_sales ?? 0),
            'trades' => (int) ($this->trades_count ?? 0),
            'positiveRate' => (int) ($this->positive_rate ?? 0),
            'verified' => (bool) ($this->is_verified_seller ?? false),
        ];
    }
}
