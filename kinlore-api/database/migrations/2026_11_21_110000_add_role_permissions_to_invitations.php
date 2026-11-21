<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The role and permissions a new member gets from their invitation.
 * See docs/API_CONTRACT.md sections 1 and 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('role', 16)->default('family')->after('email');
            $table->boolean('can_add_content')->default(true)->after('role');
            $table->boolean('can_comment')->default(true)->after('can_add_content');
            $table->boolean('can_like')->default(true)->after('can_comment');
            $table->boolean('can_download')->default(true)->after('can_like');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['role', 'can_add_content', 'can_comment', 'can_like', 'can_download']);
        });
    }
};
