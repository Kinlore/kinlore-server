<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Due dates, growth moments, background video status and exports.
 * See docs/API_CONTRACT.md sections 5, 6 and 8.
 */
return new class extends Migration
{
    private const TYPES = ['photo', 'video', 'text', 'mixed', 'story', 'milestone', 'growth'];

    public function up(): void
    {
        Schema::table('kids', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
            $table->date('due_date')->nullable()->after('date_of_birth');
        });

        $hasDuration = Schema::hasColumn('moments', 'duration');

        Schema::table('moments', function (Blueprint $table) use ($hasDuration) {
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('weight_kg', 6, 3)->nullable();
            $table->decimal('head_circumference_cm', 6, 2)->nullable();
            $table->string('video_status', 16)->nullable();
            if (!$hasDuration) {
                $table->decimal('duration', 10, 3)->nullable();
            }
        });

        // SQLite rebuilt the table without the enum check, so only PostgreSQL needs the new type.
        $this->setMomentTypes(self::TYPES);

        Schema::create('exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');

        $this->setMomentTypes(array_diff(self::TYPES, ['growth']));

        Schema::table('moments', function (Blueprint $table) {
            $table->dropColumn(['height_cm', 'weight_kg', 'head_circumference_cm', 'video_status', 'duration']);
        });

        Schema::table('kids', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }

    private function setMomentTypes(array $types): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(', ', array_map(fn ($t) => "'{$t}'::character varying", $types));
        DB::statement('ALTER TABLE moments DROP CONSTRAINT IF EXISTS moments_moment_type_check');
        DB::statement("ALTER TABLE moments ADD CONSTRAINT moments_moment_type_check CHECK (moment_type::text = ANY (ARRAY[{$list}]::text[]))");
    }
};
