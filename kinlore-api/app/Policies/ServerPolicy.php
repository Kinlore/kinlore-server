<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    /**
     * Determine if the user can view any servers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the server.
     */
    public function view(User $user, Server $server): bool
    {
        return $server->hasMember($user);
    }

    /**
     * Determine if the user can create servers.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the server.
     */
    public function update(User $user, Server $server): bool
    {
        return $server->isOwner($user);
    }

    /**
     * Determine if the user can delete the server.
     */
    public function delete(User $user, Server $server): bool
    {
        return $server->isOwner($user);
    }

    /**
     * Determine if the user can invite members to the server.
     */
    public function inviteMembers(User $user, Server $server): bool
    {
        return $server->isOwner($user);
    }
}
