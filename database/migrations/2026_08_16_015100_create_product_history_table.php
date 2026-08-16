<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('previous_price')->nullable();
            $table->unsignedInteger('discount')->default(0);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_history');
    }
};