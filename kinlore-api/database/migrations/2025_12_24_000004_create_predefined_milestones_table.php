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
        Schema::create('predefined_milestones', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g., "first_smile", "first_steps"
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', [
                'physical',
                'cognitive', 
                'social',
                'language',
                'emotional',
                'behavioral'
            ]);
            $table->integer('typical_age_months')->nullable();
            $table->string('stage_key'); // e.g., "infancy_0_12m"
            $table->string('stage_label'); // e.g., "Infancy (0–12 months)"
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('category');
            $table->index('stage_key');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predefined_milestones');
    }
};
