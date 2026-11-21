<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions per user, and the uploader of each moment.
 * See docs/API_CONTRACT.md section 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('family')->after('password');
            $table->boolean('can_add_content')->default(true)->after('role');
            $table->boolean('can_comment')->default(true)->after('can_add_content');
            $table->boolean('can_like')->default(true)->after('can_comment');
            $table->boolean('can_download')->default(true)->after('can_like');
        });

        // Existing users keep what they could do: the admin stays admin, everyone else could edit kids, so partner.
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        DB::table('users')->where('is_admin', false)->update(['role' => 'partner']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::table('moments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('kid_id')->constrained('users')->nullOnDelete();
        });

        // Before this column the kid's creator was the only known uploader.
        DB::table('moments')->whereNull('user_id')->update([
            'user_id' => DB::raw('(select kids.user_id from kids where kids.id = moments.kid_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        DB::table('users')->where('role', 'admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'can_add_content', 'can_comment', 'can_like', 'can_download']);
        });
    }
};
