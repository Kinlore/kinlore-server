<?php

namespace App\Policies;

use App\Models\Kid;
use App\Models\User;

/**
 * One family per instance: every user sees every kid.
 * Admin and partner add and change any kid; the kid's creator can change it too.
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
        return $user->canManageContent();
    }

    public function update(User $user, Kid $kid): bool
    {
        return $user->canManageContent() || $user->id === (int) $kid->user_id;
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
