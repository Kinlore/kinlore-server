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
        Schema::create('stories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('moment_id');
            $table->uuid('kid_id');
            $table->string('title');
            $table->longText('text');
            $table->uuid('icon_image_id')->nullable(); // FK to media table
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('moment_id')
                  ->references('id')
                  ->on('moments')
                  ->cascadeOnDelete();
                  
            $table->foreign('kid_id')
                  ->references('id')
                  ->on('kids')
                  ->cascadeOnDelete();
                  
            $table->foreign('icon_image_id')
                  ->references('id')
                  ->on('media')
                  ->nullOnDelete();

            // Indexes
            $table->index('kid_id');
            $table->index('moment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
