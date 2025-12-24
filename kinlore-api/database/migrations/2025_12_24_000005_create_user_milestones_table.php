<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Refactors milestones table to be user_milestones for tracking user's
     * milestone achievements, linking to predefined or custom milestones.
     */
    public function up(): void
    {
        Schema::create('user_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kid_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Link to predefined milestone OR custom title
            $table->string('predefined_milestone_id')->nullable(); // FK to predefined_milestones
            $table->string('custom_title')->nullable(); // For custom milestones
            $table->text('custom_description')->nullable();
            
            // Achievement data
            $table->date('milestone_date'); // When milestone was achieved
            $table->text('notes')->nullable();
            
            // Icon image
            $table->uuid('icon_image_id')->nullable(); // FK to media
            
            // Link to moment (optional - milestone can be standalone or linked to a photo/video)
            $table->uuid('moment_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('kid_id')
                  ->references('id')
                  ->on('kids')
                  ->cascadeOnDelete();
                  
            $table->foreign('predefined_milestone_id')
                  ->references('id')
                  ->on('predefined_milestones')
                  ->nullOnDelete();
                  
            $table->foreign('icon_image_id')
                  ->references('id')
                  ->on('media')
                  ->nullOnDelete();
                  
            $table->foreign('moment_id')
                  ->references('id')
                  ->on('moments')
                  ->nullOnDelete();

            // Indexes
            $table->index('kid_id');
            $table->index('user_id');
            $table->index('milestone_date');
            $table->index('predefined_milestone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_milestones');
    }
};
