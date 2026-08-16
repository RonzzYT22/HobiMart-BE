<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    // format data produk sesuai kontrak data.Product frontend
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'brand' => $this->brand,
            'series' => $this->series,
            'itemType' => $this->item_type,
            'language' => $this->language,
            'year' => $this->year,
            'condition' => $this->condition,
            'verified' => (bool) $this->verified,
            'stock' => (int) $this->stock,
            'price' => (int) $this->price,
            'originalPrice' => (int) ($this->original_price ?? 0),
            'discount' => (int) $this->discount,
            'rating' => (float) $this->rating,
            'reviewCount' => (int) $this->review_count,
            'sold' => (int) $this->sold,
            'image' => $this->image,
            'images' => $this->images ?? [],
            'badges' => $this->badges ?? [],
            'description' => $this->description,
            'tradeAvailable' => (bool) $this->trade_available,
            'conditionScores' => $this->condition_scores,
            'seller' => new SellerResource($this->whenLoaded('seller')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}