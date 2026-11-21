<?php

namespace App\Policies;

use App\Models\Kid;
use App\Models\User;

/**
 * One family per instance: every user sees every kid.
 * Only the admin or the user who added the kid can change it.
 */
class KidPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Kid $kid): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Kid $kid): bool
    {
        return $user->is_admin || $user->id === $kid->user_id;
    }

    public function delete(User $user, Kid $kid): bool
    {
        return $this->update($user, $kid);
    }

    public function restore(User $user, Kid $kid): bool
    {
        return $this->update($user, $kid);
    }

    public function forceDelete(User $user, Kid $kid): bool
    {
        return $this->update($user, $kid);
    }
}
