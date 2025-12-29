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
        Schema::create('server_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('server_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['owner', 'contributor', 'viewer'])->default('viewer');
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('joined_at')->useCurrent();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'server_id']);
            $table->index('server_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_members');
    }
};
