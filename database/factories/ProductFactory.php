<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    // data dummy untuk testing
    public function definition(): array
    {
        $categories = ['Trading Cards', 'Gundam & Gunpla', 'Figures', 'Collectibles', 'Accessories'];
        $subcategories = [
            'Trading Cards' => ['Pokémon', 'One Piece', 'Yu-Gi-Oh!', 'Dragon Ball', 'Weiss Schwarz'],
            'Gundam & Gunpla' => ['Master Grade', 'Real Grade', 'High Grade', 'Perfect Grade', 'SD'],
            'Figures' => ['Nendoroid', 'Figma', 'Scale Figure', 'Prize Figure', 'Action Figure'],
            'Collectibles' => ['Limited Edition', 'Signed', 'Vintage', 'Promo', 'Event Exclusive'],
            'Accessories' => ['Sleeves', 'Deck Box', 'Playmat', 'Binder', 'Toploader'],
        ];
        $brands = ['Bandai', 'Tamashii Nations', 'Good Smile Company', 'Kotobukiya', 'Hasbro', 'Funko', 'Banpresto', 'Wizards of the Coast'];

        $category = fake()->randomElement($categories);
        $subcategory = fake()->randomElement($subcategories[$category] ?? $subcategories['Trading Cards']);
        $condition = fake()->randomElement(Product::CONDITIONS);
        $price = fake()->numberBetween(10000, 5000000);
        $originalPrice = fake()->boolean(30) ? $price + fake()->numberBetween(5000, 500000) : $price;
        $discount = $originalPrice > $price ? (int) round((($originalPrice - $price) / $originalPrice) * 100) : 0;

        $badgeOptions = Product::ALLOWED_BADGES;
        $badges = fake()->boolean(40) ? fake()->randomElements($badgeOptions, fake()->numberBetween(1, 3)) : [];

        $imageLabels = ['Front', 'Back', 'Corner', 'Surface', 'Packaging'];
        $images = [];
        $imgCount = fake()->numberBetween(1, 4);
        for ($i = 0; $i < $imgCount; $i++) {
            $images[] = [
                'url' => 'https://picsum.photos/seed/'.fake()->uuid().'/400/400',
                'label' => $imageLabels[$i] ?? 'Image '.($i + 1),
            ];
        }

        return [
                    'sku' => 'HM-' . str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                    'name' => fake()->words(fake()->numberBetween(2, 5), true),
            'category' => $category,
            'subcategory' => $subcategory,
            'brand' => fake()->randomElement($brands),
            'series' => fake()->boolean(60) ? fake()->word() . ' Series' : null,
            'item_type' => fake()->boolean(40) ? fake()->word() : null,
            'language' => fake()->randomElement(['Japanese', 'English', 'Korean', 'Chinese', 'Indonesian']),
            'year' => (int) fake()->year(),
            'condition' => $condition,
            'verified' => fake()->boolean(70),
            'stock' => fake()->numberBetween(0, 50),
            'price' => $price,
            'original_price' => $originalPrice,
            'discount' => $discount,
            'rating' => fake()->randomFloat(2, 3.0, 5.0),
            'review_count' => fake()->numberBetween(0, 500),
            'sold' => fake()->numberBetween(0, 1000),
            'image' => 'https://picsum.photos/seed/'.fake()->uuid().'/600/600',
            'images' => $images,
            'badges' => $badges,
            'description' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
            'trade_available' => fake()->boolean(30),
            'condition_scores' => [
                'corners' => fake()->numberBetween(1, 10),
                'surface' => fake()->numberBetween(1, 10),
                'edges' => fake()->numberBetween(1, 10),
                'centering' => fake()->numberBetween(1, 10),
            ],
            'seller_id' => User::factory(),
        ];
    }
}
