<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // tambah kolom statistik seller ke tabel users
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->default(0.00)->after('verified_collector');
            $table->unsignedInteger('positive_rate')->default(0)->after('rating');
            $table->unsignedInteger('trades_count')->default(0)->after('positive_rate');
            $table->unsignedInteger('total_sales')->default(0)->after('trades_count');
            $table->boolean('is_verified_seller')->default(false)->after('total_sales');
        });
    }

    // hapus kolom saat rollback
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'rating',
                'positive_rate',
                'trades_count',
                'total_sales',
                'is_verified_seller',
            ]);
        });
    }
};
