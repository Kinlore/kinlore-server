<?php

namespace App\Policies;

use App\Models\Kid;
use App\Models\User;

class KidPolicy
{
    /**
     * Determine if the user can view any kids.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view their own kids
    }

    /**
     * Determine if the user can view the kid.
     */
    public function view(User $user, Kid $kid): bool
    {
        return $user->id === $kid->user_id;
    }

    /**
     * Determine if the user can create kids.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create kids
    }

    /**
     * Determine if the user can update the kid.
     */
    public function update(User $user, Kid $kid): bool
    {
        return $user->id === $kid->user_id;
    }

    /**
     * Determine if the user can delete the kid.
     */
    public function delete(User $user, Kid $kid): bool
    {
        return $user->id === $kid->user_id;
    }

    /**
     * Determine if the user can restore the kid.
     */
    public function restore(User $user, Kid $kid): bool
    {
        return $user->id === $kid->user_id;
    }

    /**
     * Determine if the user can permanently delete the kid.
     */
    public function forceDelete(User $user, Kid $kid): bool
    {
        return $user->id === $kid->user_id;
    }
}
