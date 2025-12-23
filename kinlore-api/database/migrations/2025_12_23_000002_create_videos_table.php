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
        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // File metadata
            $table->string('original_filename');
            $table->bigInteger('file_size')->unsigned(); // in bytes
            $table->string('mime_type');
            $table->float('duration')->nullable(); // in seconds, populated after processing
            
            // Lifecycle status
            $table->enum('status', ['uploading', 'processing', 'completed', 'failed'])
                  ->default('uploading');
            
            // Storage paths
            $table->string('storage_path'); // path to original file in S3
            $table->string('hls_url')->nullable(); // HLS manifest URL
            $table->string('thumbnail_url')->nullable(); // thumbnail image URL
            
            // Error tracking
            $table->text('error_message')->nullable();
            
            // Processing timestamps for analytics
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
