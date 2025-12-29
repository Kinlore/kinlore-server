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
        Schema::table('moments', function (Blueprint $table) {
            // HLS stream URL for video moments
            if (!Schema::hasColumn('moments', 'hls_url')) {
                $table->string('hls_url')->nullable()->after('medium_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            if (Schema::hasColumn('moments', 'hls_url')) {
                $table->dropColumn('hls_url');
            }
        });
    }
};
