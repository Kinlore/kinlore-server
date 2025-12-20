<?php

namespace App\Policies;

use App\Models\Moment;
use App\Models\User;

class MomentPolicy
{
    /**
     * Determine if the user can view any moments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the moment.
     */
    public function view(User $user, Moment $moment): bool
    {
        return $user->id === $moment->kid->user_id;
    }

    /**
     * Determine if the user can create moments.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the moment.
     */
    public function update(User $user, Moment $moment): bool
    {
        return $user->id === $moment->kid->user_id;
    }

    /**
     * Determine if the user can delete the moment.
     */
    public function delete(User $user, Moment $moment): bool
    {
        return $user->id === $moment->kid->user_id;
    }

    /**
     * Determine if the user can restore the moment.
     */
    public function restore(User $user, Moment $moment): bool
    {
        return $user->id === $moment->kid->user_id;
    }

    /**
     * Determine if the user can permanently delete the moment.
     */
    public function forceDelete(User $user, Moment $moment): bool
    {
        return $user->id === $moment->kid->user_id;
    }
}