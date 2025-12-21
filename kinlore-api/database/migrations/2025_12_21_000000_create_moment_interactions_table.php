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
        Schema::create('moment_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('moment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'like' or 'comment'
            $table->text('content')->nullable(); // For comments
            $table->timestamps();

            // Indexes for performance
            $table->index(['moment_id', 'type']);
            $table->index(['user_id', 'moment_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moment_interactions');
    }
};
