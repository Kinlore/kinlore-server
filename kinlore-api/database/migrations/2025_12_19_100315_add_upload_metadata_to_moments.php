<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds columns for tracking device uploads and duplicate detection
     */
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            // Upload tracking
            $table->string('device_asset_id')->nullable()->after('original_filename');
            $table->string('device_fingerprint')->nullable()->after('device_asset_id'); // hash for duplicate detection
            $table->string('uploaded_from_device')->nullable()->after('device_fingerprint'); // device info
            $table->ipAddress('uploaded_from_ip')->nullable()->after('uploaded_from_device');
            
            // Indexes for duplicate detection
            $table->index('device_asset_id');
            $table->index('device_fingerprint');
            $table->index(['file_size', 'original_filename']); // For filename-based detection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropIndex(['file_size', 'original_filename']);
            $table->dropIndex(['device_fingerprint']);
            $table->dropIndex(['device_asset_id']);
            
            $table->dropColumn([
                'device_asset_id',
                'device_fingerprint',
                'uploaded_from_device',
                'uploaded_from_ip',
            ]);
        });
    }
};
