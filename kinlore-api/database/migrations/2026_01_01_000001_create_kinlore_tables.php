<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->date('due_date')->nullable();
            $table->string('avatar_color', 7)->default('#FF6B6B');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('created_at');
            $table->index(['user_id', 'updated_at']);
        });

        // Ids come from database/seeders/milestones.json (e.g. "first_smile").
        Schema::create('milestones', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('stage_key');
            $table->string('stage_label');
            $table->integer('age_min_months')->nullable();
            $table->integer('age_max_months')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('stage_key');
            $table->index('sort_order');
            $table->index('age_min_months');
            $table->index('age_max_months');
        });

        // One uploaded file and its processing state; the moment points at it.
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['photo', 'video']);
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->json('exif_data')->nullable();
            $table->json('video_metadata')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('medium_url')->nullable();
            $table->string('hls_url')->nullable();
            $table->enum('status', ['uploading', 'processing', 'completed', 'failed'])->default('uploading');
            $table->text('processing_error')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->string('device_asset_id')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kid_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('created_at');
            $table->index('device_asset_id');
        });

        Schema::create('moments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->enum('moment_type', ['photo', 'video', 'story', 'milestone', 'growth'])->default('photo');
            $table->string('milestone_id')->nullable();
            $table->foreign('milestone_id')->references('id')->on('milestones')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // Storage paths; the model turns them into signed URLs.
            $table->string('photo_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('medium_url')->nullable();
            $table->string('hls_url')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->default('image/jpeg');
            $table->string('original_filename')->nullable();
            $table->string('device_asset_id')->nullable();
            $table->string('device_fingerprint')->nullable();

            $table->dateTime('exif_date')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('orientation')->nullable();
            $table->string('camera_make')->nullable();
            $table->string('camera_model')->nullable();

            $table->decimal('duration', 10, 3)->nullable();
            $table->string('video_status', 16)->nullable();

            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('weight_kg', 6, 3)->nullable();
            $table->decimal('head_circumference_cm', 6, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('kid_id');
            $table->index('created_at');
            $table->index('exif_date');
            $table->index('moment_type');
            $table->index('media_id');
            $table->index('milestone_id');
            $table->index(['kid_id', 'exif_date']);
            $table->index(['kid_id', 'created_at']);
            $table->index(['kid_id', 'updated_at']);
            $table->index('device_asset_id');
            $table->index('device_fingerprint');
            $table->index(['file_size', 'original_filename']);
        });

        Schema::create('moment_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('moment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // like or comment
            $table->text('content')->nullable();
            $table->timestamps();

            $table->index(['moment_id', 'type']);
            $table->index(['user_id', 'moment_id', 'type']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->string('email')->nullable();
            $table->string('role', 16)->default('family');
            $table->boolean('can_add_content')->default(true);
            $table->boolean('can_comment')->default(true);
            $table->boolean('can_like')->default(true);
            $table->boolean('can_download')->default(true);
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['exports', 'invitations', 'moment_interactions', 'moments', 'media', 'milestones', 'kids'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
