<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column
        Schema::table('kids', function (Blueprint $table) {
            $table->foreignUuid('server_id')->nullable()->after('user_id');
        });

        // Migrate data: Create servers for existing users and assign kids
        // Using raw SQL for performance and simplicity in migration
        
        // 1. Create a server for each user who has kids (or just all users to be safe)
        // We'll call it "{Name}'s Family"
        // 1. Create a server for each user who has kids (or just all users to be safe)
        // We'll call it "{Name}'s Family"
        // PostgreSQL specific syntax
        // 1. Create a server for each user who has kids (or just all users to be safe)
        // We'll call it "{Name}'s Family"
        // PostgreSQL specific syntax - skipped for SQLite/Testing
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                INSERT INTO servers (id, name, owner_id, created_at, updated_at)
                SELECT 
                    gen_random_uuid(), 
                    name || '''s Family', 
                    id, 
                    NOW(), 
                    NOW()
                FROM users
            ");

            // 2. Add the owners as members of their new servers
            DB::statement("
                INSERT INTO server_members (user_id, server_id, role, joined_at, created_at, updated_at)
                SELECT owner_id, id, 'owner', NOW(), NOW(), NOW()
                FROM servers
            ");

            // 3. Update kids to belong to their user's server
            // This assumes the user_id on the kid is the owner
            DB::statement("
                UPDATE kids 
                SET server_id = servers.id
                FROM servers 
                WHERE servers.owner_id = kids.user_id
            ");
        }
        
        // 4. Handle any orphaned kids (if any user didn't get a server for some reason, though step 1 covers all users)
        // Ideally we shouldn't have any if we created servers for all users.
        
        // 5. Make server_id required now that we've populated it (only if we are sure all kids have servers)
        // We will make it required only if there are no nulls, to be safe.
        $nullCount = DB::table('kids')->whereNull('server_id')->count();
        
        if ($nullCount === 0) {
            Schema::table('kids', function (Blueprint $table) {
                $table->uuid('server_id')->nullable(false)->change();
                $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kids', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['server_id']);
            $table->dropColumn('server_id');
        });
        
        // We don't delete servers/members in down() because we don't want to lose data 
        // if this was run in production and then rolled back after some usage.
        // But strictly speaking, a rollback should reverse the structure.
    }
};
