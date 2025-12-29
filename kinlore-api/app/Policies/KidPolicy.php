<?php

namespace App\Policies;

use App\Models\Kid;
use App\Models\User;

class KidPolicy
{
    /**
     * Determine if the user can view any kids.
     */
    /**
     * Determine if the user can view any kids.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtered by service/controller
    }

    /**
     * Determine if the user can view the kid.
     */
    public function view(User $user, Kid $kid): bool
    {
        return $kid->server->hasMember($user);
    }

    /**
     * Determine if the user can create kids.
     */
    public function create(User $user): bool
    {
        return $user->servers()->exists();
    }

    /**
     * Determine if the user can update the kid.
     */
    public function update(User $user, Kid $kid): bool
    {
        $role = $kid->server->getMemberRole($user);
        
        // Owner of server can update
        if ($role === 'owner') {
             return true;
        }
        
        // Creator of the kid (if they are still a member) can update if they are owner/contributor?
        // Or strictly owner? Let's say Owner or the Creator (if they have write access).
        if ($user->id === $kid->user_id && in_array($role, ['owner', 'contributor'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the kid.
     */
    public function delete(User $user, Kid $kid): bool
    {
        $role = $kid->server->getMemberRole($user);
        
        // Only server owner or kid creator can delete
        if ($role === 'owner') return true;
        
        if ($user->id === $kid->user_id && $role === 'contributor') return true;
        
        return false;
    }

    /**
     * Determine if the user can restore the kid.
     */
    public function restore(User $user, Kid $kid): bool
    {
        return $this->delete($user, $kid); // Same permissions as delete
    }

    /**
     * Determine if the user can permanently delete the kid.
     */
    public function forceDelete(User $user, Kid $kid): bool
    {
        return $this->delete($user, $kid);
    }
}
