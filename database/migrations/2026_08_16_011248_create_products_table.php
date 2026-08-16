<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->string('brand')->nullable();
            $table->string('series')->nullable();
            $table->string('item_type')->nullable();
            $table->string('language')->nullable();
            $table->string('year')->nullable();
            $table->enum('condition', ['Mint', 'Near Mint', 'Excellent', 'Good', 'Played', 'Damaged']);
            $table->boolean('verified')->default(false);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('original_price')->nullable();
            $table->unsignedInteger('discount')->default(0);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('sold')->default(0);
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->json('badges')->nullable();
            $table->longText('description')->nullable();
            $table->boolean('trade_available')->default(false);
            $table->json('condition_scores')->nullable();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['category', 'condition', 'trade_available']);
            $table->index('brand');
            $table->index('price');
            $table->index('rating');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};