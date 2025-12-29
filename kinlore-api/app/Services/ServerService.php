<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ServerService
{
    /**
     * Create a new server for a user
     */
    public function createServer(User $owner, array $data): Server
    {
        return DB::transaction(function () use ($owner, $data) {
            $server = Server::create([
                'name' => $data['name'],
                'owner_id' => $owner->id,
                'settings' => $data['settings'] ?? null,
            ]);

            // Add owner as a member with 'owner' role
            ServerMember::create([
                'user_id' => $owner->id,
                'server_id' => $server->id,
                'role' => 'owner',
            ]);

            return $server;
        });
    }

    /**
     * Get all servers for a user
     */
    public function getServersForUser(User $user): Collection
    {
        return $user->servers;
    }

    /**
     * Get members of a server
     */
    public function getMembersForServer(Server $server): Collection
    {
        return $server->users;
    }

    /**
     * Check if user is a member of the server
     */
    public function isMember(User $user, Server $server): bool
    {
        return $server->hasMember($user);
    }
    
    /**
     * Get user's role in a server
     */
    public function getMemberRole(User $user, Server $server): ?string
    {
        // First check relationship if loaded, otherwise query
        if ($user->relationLoaded('serverMemberships')) {
             $membership = $user->serverMemberships->where('server_id', $server->id)->first();
             return $membership ? $membership->role : null;
        }
        
        $membership = ServerMember::where('user_id', $user->id)
            ->where('server_id', $server->id)
            ->first();
            
        return $membership ? $membership->role : null;
    }

    /**
     * Remove a member from a server
     */
    public function removeMember(Server $server, ServerMember $member): bool
    {
        // Logic to prevent removing the last owner could be here, or in the controller/policy
        // Generally, the policy handles "can I remove this person", but "is this the last owner" is a business rule.
        
        if ($member->role === 'owner') {
             $ownerCount = ServerMember::where('server_id', $server->id)
                 ->where('role', 'owner')
                 ->count();
                 
             if ($ownerCount <= 1) {
                 return false; // Cannot remove last owner
             }
        }
        
        return $member->delete();
    }
}
