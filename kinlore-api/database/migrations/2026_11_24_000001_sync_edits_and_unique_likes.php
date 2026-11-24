<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who last changed the row and when: sync conflicts ignore likes, comments and the pusher's own edits.
        Schema::table('moments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();
        });
        Schema::table('kids', function (Blueprint $table) {
            $table->unsignedBigInteger('edited_by')->nullable();
        });

        // One like per user and moment (partial index: a user may comment many times). Drop older double likes first.
        DB::statement("DELETE FROM moment_interactions WHERE type = 'like' AND id NOT IN (SELECT MIN(id) FROM moment_interactions WHERE type = 'like' GROUP BY moment_id, user_id)");
        DB::statement("CREATE UNIQUE INDEX moment_interactions_one_like ON moment_interactions (moment_id, user_id) WHERE type = 'like'");

        // Nothing writes milestone categories.
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->string('category')->nullable()->index();
        });
        DB::statement('DROP INDEX moment_interactions_one_like');
        Schema::table('kids', fn (Blueprint $table) => $table->dropColumn('edited_by'));
        Schema::table('moments', fn (Blueprint $table) => $table->dropColumn(['edited_at', 'edited_by']));
    }
};
