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
        Schema::create('milestones', function (Blueprint $table) {
            // Using string ID from JSON file (e.g., "first_smile", "first_steps")
            $table->string('id')->primary();
            
            // Title of the milestone (e.g., "That First Real Smile")
            $table->string('title');
            
            // Description of the milestone
            $table->text('description')->nullable();
            
            // Stage grouping (e.g., "infancy_0_12m", "toddler_1_3y")
            $table->string('stage_key');
            
            // Human-readable stage label (e.g., "Infancy (0–12 months)")
            $table->string('stage_label');
            
            // Order within the stage for sorting
            $table->integer('sort_order')->default(0);
            
            // Optional icon image
            $table->uuid('icon_image_id')->nullable();
            
            $table->timestamps();

            // Foreign key for icon image
            $table->foreign('icon_image_id')
                  ->references('id')
                  ->on('images')
                  ->nullOnDelete();

            // Indexes
            $table->index('stage_key');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
