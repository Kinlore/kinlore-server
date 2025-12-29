<?php

namespace App\Policies;

use App\Models\ServerMember;
use App\Models\User;

class ServerMemberPolicy
{
    /**
     * Determine if the user can remove a member.
     */
    public function delete(User $user, ServerMember $member): bool
    {
        // Only server owner can remove members
        // And they can't remove themselves (handled by business logic primarily, but consistent here)
        // Also user can remove themselves (leave server) -> logic for "leave" vs "kick"
        
        // If user is the member being removed (Leaving)
        if ($user->id === $member->user_id) {
            // Cannot leave if you are the owner (must transfer ownership or delete server)
            return !$member->isOwner(); // Actually business logic checks "last owner", but simple check: is owner?
        }
        
        // If user is trying to remove someone else (Kicking)
        $requesterMembership = $member->server->members()->where('user_id', $user->id)->first();
        
        if (!$requesterMembership || !$requesterMembership->isOwner()) {
            return false;
        }
        
        // Owner checks
        // Cannot kick another owner? (Assuming multi-owner support future proofing, but currently strictly one owner_id on server table)
        // If we strictly rely on server.owner_id then:
        return $member->server->owner_id === $user->id;
    }
}
