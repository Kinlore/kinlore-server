<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('predefined_milestones', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });

        // Make category nullable since new milestones don't have categories
        DB::statement("ALTER TABLE predefined_milestones MODIFY category ENUM('physical', 'cognitive', 'social', 'language', 'emotional', 'behavioral') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predefined_milestones', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
