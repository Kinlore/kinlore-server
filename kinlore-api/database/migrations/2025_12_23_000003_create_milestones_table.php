<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates unified milestones table that stores all predefined milestone data.
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
            
            
            // Category for filtering (optional)
            $table->string('category')->nullable();
            
            // Stage grouping (e.g., "infancy_0_12m", "toddler_1_3y")
            $table->string('stage_key');
            
            // Human-readable stage label (e.g., "Infancy (0–12 months)")
            $table->string('stage_label');
            
            // Unified age range in months for easy querying
            $table->integer('age_min_months')->nullable();
            $table->integer('age_max_months')->nullable();
            
            // Order for sorting
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();

            // Indexes
            $table->index('category');
            $table->index('stage_key');
            $table->index('sort_order');
            $table->index('age_min_months');
            $table->index('age_max_months');
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
