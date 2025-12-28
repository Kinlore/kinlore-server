<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to update the check constraint because the enum was already created with limited types
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE moments DROP CONSTRAINT IF EXISTS moments_moment_type_check");
            DB::statement("ALTER TABLE moments ADD CONSTRAINT moments_moment_type_check CHECK (moment_type::text = ANY (ARRAY['photo'::character varying, 'video'::character varying, 'text'::character varying, 'mixed'::character varying, 'story'::character varying, 'milestone'::character varying]::text[]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE moments DROP CONSTRAINT IF EXISTS moments_moment_type_check");
            DB::statement("ALTER TABLE moments ADD CONSTRAINT moments_moment_type_check CHECK (moment_type::text = ANY (ARRAY['photo'::character varying, 'video'::character varying, 'text'::character varying, 'mixed'::character varying]::text[]))");
        }
    }
};
