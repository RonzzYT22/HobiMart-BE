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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled']);
            $table->json('items');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping');
            $table->unsignedBigInteger('total');
            $table->json('shipping_address');
            $table->enum('payment_method', ['qris', 'bank', 'ewallet', 'cc']);
            $table->enum('payment_status', ['Paid', 'Unpaid', 'Refunded']);
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->date('est_arrival')->nullable();
            $table->json('timeline')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};