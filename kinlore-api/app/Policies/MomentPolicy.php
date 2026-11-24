<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Moment;
use App\Models\User;

/**
 * One family per instance: every user sees every moment.
 * Adding needs the add_content permission (checked where moments are created).
 * Admin, partner, the uploader and the kid's creator can change a moment.
 */
class MomentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Moment $moment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Moment $moment): bool
    {
        return $user->canManageContent()
            || $user->id === (int) $moment->user_id
            || $user->id === (int) $moment->kid?->user_id;
    }

    public function delete(User $user, Moment $moment): bool
    {
        return $this->update($user, $moment);
    }
}
