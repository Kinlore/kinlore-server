<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds columns needed for WatermelonDB synchronization
     */
    public function up(): void
    {
        // Add sync timestamp to kids table
        Schema::table('kids', function (Blueprint $table) {
            $table->bigInteger('synced_at')->unsigned()->nullable()->after('deleted_at');
            $table->index('synced_at');
            $table->index(['user_id', 'updated_at']); // For sync pull queries
        });

        // Add sync timestamp to moments table
        Schema::table('moments', function (Blueprint $table) {
            $table->bigInteger('synced_at')->unsigned()->nullable()->after('deleted_at');
            $table->index('synced_at');
            $table->index(['kid_id', 'updated_at']); // For sync pull queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kids', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropIndex(['synced_at']);
            $table->dropColumn('synced_at');
        });

        Schema::table('moments', function (Blueprint $table) {
            $table->dropIndex(['kid_id', 'updated_at']);
            $table->dropIndex(['synced_at']);
            $table->dropColumn('synced_at');
        });
    }
};
