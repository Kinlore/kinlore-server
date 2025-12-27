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
            if (!Schema::hasColumn('predefined_milestones', 'image_path')) {
                $table->string('image_path')->nullable()->after('description');
            }
        });

        // Make category nullable - use PostgreSQL syntax
        DB::statement("ALTER TABLE predefined_milestones ALTER COLUMN category DROP NOT NULL");
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
