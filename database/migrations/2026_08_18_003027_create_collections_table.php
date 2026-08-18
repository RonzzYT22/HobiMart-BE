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
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('condition', ['Mint', 'Near Mint', 'Excellent', 'Good', 'Played', 'Damaged'])->default('Good');
            $table->string('grade')->nullable(); // PSA 10, BGS 9.5, CGC 9, etc
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->date('purchase_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('product_id');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
