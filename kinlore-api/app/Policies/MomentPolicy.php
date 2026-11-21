<?php

namespace App\Policies;

use App\Models\Moment;
use App\Models\User;

/**
 * One family per instance: every user sees and adds moments.
 * Changing a moment follows the rules for its kid.
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
        return (new KidPolicy)->update($user, $moment->kid);
    }

    public function delete(User $user, Moment $moment): bool
    {
        return $this->update($user, $moment);
    }

    public function restore(User $user, Moment $moment): bool
    {
        return $this->update($user, $moment);
    }

    public function forceDelete(User $user, Moment $moment): bool
    {
        return $this->update($user, $moment);
    }
}
