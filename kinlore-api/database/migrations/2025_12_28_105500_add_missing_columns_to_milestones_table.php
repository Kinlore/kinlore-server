<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds missing columns to the milestones table that were added after initial creation.
     */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            if (!Schema::hasColumn('milestones', 'age_min_months')) {
                $table->integer('age_min_months')->nullable()->after('stage_label');
            }
            if (!Schema::hasColumn('milestones', 'age_max_months')) {
                $table->integer('age_max_months')->nullable()->after('age_min_months');
            }
            if (!Schema::hasColumn('milestones', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('age_max_months');
            }
            if (!Schema::hasColumn('milestones', 'stage_key')) {
                $table->string('stage_key')->nullable()->after('category');
            }
            if (!Schema::hasColumn('milestones', 'stage_label')) {
                $table->string('stage_label')->nullable()->after('stage_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $columns = ['age_min_months', 'age_max_months', 'sort_order'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('milestones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
