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
            // Add media_id to link unified media to moments
            $table->uuid('media_id')->nullable()->after('video_id');
            
            // Update moment_type enum to include story and milestone
            // Note: MySQL doesn't easily support altering enums, so we use a workaround
            // For now, we'll add 'story' and 'milestone' types via a raw statement later
            
            // Foreign key
            $table->foreign('media_id')
                  ->references('id')
                  ->on('media')
                  ->nullOnDelete();

            // Index
            $table->index('media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropIndex(['media_id']);
            $table->dropColumn('media_id');
        });
    }
};
