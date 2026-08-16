<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // validasi untuk create produk
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'series' => ['nullable', 'string', 'max:100'],
            'item_type' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'condition' => ['required', 'string', 'in:'.implode(',', Product::CONDITIONS)],
            'verified' => ['sometimes', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'integer', 'min:0'],
            'original_price' => ['nullable', 'integer', 'min:0'],
            'discount' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['sometimes', 'integer', 'min:0'],
            'sold' => ['sometimes', 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'images' => ['sometimes', 'array'],
            'images.*.url' => ['required_with:images', 'string', 'max:500'],
            'images.*.label' => ['nullable', 'string', 'max:50'],
            'badges' => ['sometimes', 'array'],
            'badges.*' => ['string', 'in:'.implode(',', Product::ALLOWED_BADGES)],
            'description' => ['nullable', 'string'],
            'trade_available' => ['sometimes', 'boolean'],
            'condition_scores' => ['sometimes', 'array'],
            'seller_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
