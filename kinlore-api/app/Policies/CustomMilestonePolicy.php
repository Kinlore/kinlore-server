<?php

namespace App\Policies;

use App\Models\CustomMilestone;
use App\Models\User;

class CustomMilestonePolicy
{
    /**
     * Determine if the user can view the custom milestone.
     */
    public function view(User $user, CustomMilestone $customMilestone): bool
    {
        return $user->id === $customMilestone->user_id;
    }

    /**
     * Determine if the user can update the custom milestone.
     */
    public function update(User $user, CustomMilestone $customMilestone): bool
    {
        return $user->id === $customMilestone->user_id;
    }

    /**
     * Determine if the user can delete the custom milestone.
     */
    public function delete(User $user, CustomMilestone $customMilestone): bool
    {
        return $user->id === $customMilestone->user_id;
    }
}
