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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiator_collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('receiver_collection_id')->constrained('collections')->cascadeOnDelete();
            $table->bigInteger('cash_difference')->default(0); // negatif = receiver terima uang
            $table->enum('status', [
                'pending', 'negotiating', 'agreed',
                'shipped_initiator', 'shipped_receiver',
                'completed', 'cancelled', 'disputed'
            ])->default('pending');
            $table->timestamp('initiator_shipped_at')->nullable();
            $table->timestamp('receiver_shipped_at')->nullable();
            $table->string('initiator_tracking')->nullable();
            $table->string('receiver_tracking')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->timestamps();

            $table->index('initiator_id');
            $table->index('receiver_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
