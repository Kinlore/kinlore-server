<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Display size of a photo or video, so the app can lay out the timeline before an image loads.
    // On moments like orientation and duration: the API and sync read only moments. Filled for older rows by kinlore:media-dimensions.
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('moments', fn (Blueprint $table) => $table->dropColumn(['width', 'height']));
    }
};
