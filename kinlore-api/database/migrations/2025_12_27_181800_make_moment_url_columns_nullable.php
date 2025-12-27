<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Makes photo_url, thumbnail_url, and medium_url nullable to support
     * video moments (where URLs are set after processing) and 
     * story/milestone moments (which don't have media files).
     */
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->string('photo_url')->nullable()->change();
            $table->string('thumbnail_url')->nullable()->change();
            $table->string('medium_url')->nullable()->change();
            $table->bigInteger('file_size')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reversing this could fail if there are moments with null values
        Schema::table('moments', function (Blueprint $table) {
            $table->string('photo_url')->nullable(false)->change();
            $table->string('thumbnail_url')->nullable(false)->change();
            $table->string('medium_url')->nullable(false)->change();
            $table->bigInteger('file_size')->unsigned()->nullable(false)->change();
        });
    }
};
