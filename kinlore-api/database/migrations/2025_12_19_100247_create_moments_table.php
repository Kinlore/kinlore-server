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
        Schema::create('moments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kid_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            
            // Photo URLs (MinIO paths)
            $table->string('photo_url'); // Original (~3MB)
            $table->string('thumbnail_url'); // 300x300 (~30KB)
            $table->string('medium_url'); // 1200px (~200KB)
            
            // File metadata
            $table->bigInteger('file_size')->unsigned(); // Bytes
            $table->string('mime_type')->default('image/jpeg');
            $table->string('original_filename')->nullable();
            
            // EXIF data
            $table->dateTime('exif_date')->nullable(); // Date photo was taken
            $table->decimal('latitude', 10, 8)->nullable(); // GPS latitude
            $table->decimal('longitude', 11, 8)->nullable(); // GPS longitude
            $table->integer('orientation')->nullable(); // EXIF orientation
            $table->string('camera_make')->nullable(); // Camera manufacturer
            $table->string('camera_model')->nullable(); // Camera model
            
            // Tags (JSON array)
            $table->json('tags')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes(); // For sync support
            
            // Foreign key
            $table->foreign('kid_id')
                  ->references('id')
                  ->on('kids')
                  ->onDelete('cascade');
            
            // Indexes for performance
            $table->index('kid_id');
            $table->index('created_at');
            $table->index('exif_date');
            $table->index(['kid_id', 'exif_date']); // Composite for timeline queries
            $table->index(['kid_id', 'created_at']); // Composite for timeline queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moments');
    }
};
