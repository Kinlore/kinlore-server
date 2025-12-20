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
        Schema::create('albums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kid_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_photo_url')->nullable();
            $table->integer('photos_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('kid_id')
                  ->references('id')
                  ->on('kids')
                  ->onDelete('cascade');
            
            $table->index('kid_id');
        });

        // Pivot table for moment-album relationship
        Schema::create('album_moment', function (Blueprint $table) {
            $table->uuid('album_id');
            $table->uuid('moment_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->foreign('album_id')
                  ->references('id')
                  ->on('albums')
                  ->onDelete('cascade');
            
            $table->foreign('moment_id')
                  ->references('id')
                  ->on('moments')
                  ->onDelete('cascade');
            
            $table->primary(['album_id', 'moment_id']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_moment');
        Schema::dropIfExists('albums');
    }
};