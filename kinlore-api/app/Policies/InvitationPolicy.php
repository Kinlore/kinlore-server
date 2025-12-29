<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Server;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Determine if the user can view invitations for a server.
     */
    public function viewAny(User $user, Server $server): bool
    {
        return $server->isOwner($user);
    }

    /**
     * Determine if the user can create invitations.
     */
    public function create(User $user, Server $server): bool
    {
        return $server->isOwner($user);
    }

    /**
     * Determine if the user can revoke the invitation.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $invitation->server->isOwner($user);
    }
}
