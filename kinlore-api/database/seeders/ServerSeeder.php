<?php

namespace Database\Seeders;

use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SEED_USER_EMAIL');
        
        if (empty($email)) {
            return;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->command->warn("User {$email} not found. Cannot seed server.");
            return;
        }

        // Fixed UUID for the default server
        $serverId = '00000000-0000-0000-0000-000000000001';

        $server = Server::firstOrCreate(
            ['id' => $serverId],
            [
                'name' => $user->name . "'s Family",
                'owner_id' => $user->id,
                'settings' => [],
            ]
        );

        // Ensure user is the owner member
        ServerMember::firstOrCreate(
            [
                'user_id' => $user->id,
                'server_id' => $server->id,
            ],
            [
                'role' => 'owner',
                'joined_at' => now(),
            ]
        );
        
        // Also update the server owner_id if it existed but was different (unlikely with fixed UUID but safe)
        if ($server->owner_id !== $user->id) {
            $server->update(['owner_id' => $user->id]);
        }

        $this->command->info("Server '{$server->name}' ({$serverId}) updated/created for user {$user->email}.");
        
        // Update any kids without server_id to this server (cleanup/migration for seeded data)
        $user->kids()->whereNull('server_id')->update(['server_id' => $server->id]);
    }
}
