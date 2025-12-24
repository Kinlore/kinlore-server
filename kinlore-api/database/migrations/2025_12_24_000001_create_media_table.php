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
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kid_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Media type
            $table->enum('type', ['photo', 'video']);
            
            // File metadata
            $table->string('original_filename');
            $table->string('file_path'); // path to original in storage
            $table->string('mime_type');
            $table->bigInteger('file_size')->unsigned();
            
            // Photo-specific: EXIF data stored as JSON
            // Contains: orientation, camera_make, camera_model, datetime_original,
            // iso, aperture, focal_length, gps_latitude, gps_longitude, etc.
            $table->json('exif_data')->nullable();
            
            // Video-specific: metadata stored as JSON
            // Contains: duration, fps, orientation, width, height, codec, bitrate
            $table->json('video_metadata')->nullable();
            
            // Processed URLs
            $table->string('thumbnail_url')->nullable();
            $table->string('medium_url')->nullable(); // for photos
            $table->string('hls_url')->nullable(); // for videos
            
            // Processing status
            $table->enum('status', ['uploading', 'processing', 'completed', 'failed'])
                  ->default('uploading');
            $table->text('processing_error')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            
            // Device tracking for duplicate detection
            $table->string('device_asset_id')->nullable();
            $table->string('device_fingerprint')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('kid_id')
                  ->references('id')
                  ->on('kids')
                  ->cascadeOnDelete();

            // Indexes for efficient querying
            $table->index(['kid_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('created_at');
            $table->index('device_asset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
