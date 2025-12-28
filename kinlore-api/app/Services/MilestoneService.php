<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\Milestone;
use App\Models\User;
use App\Models\UserMilestone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for handling milestone operations.
 */
class MilestoneService
{
    /**
     * Get milestones with optional grouping/filtering.
     */
    public function getMilestones(array $filters = []): Collection|array
    {
        $groupBy = $filters['group_by'] ?? null;
        
        if ($groupBy === 'stage') {
            return Milestone::getGroupedByStage();
        }
        
        if ($groupBy === 'category') {
            return Milestone::getGroupedByCategory();
        }

        $query = Milestone::ordered();
        
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (!empty($filters['stage_key'])) {
            $query->byStage($filters['stage_key']);
        }

        return $query->get();
    }

    /**
     * Get a single milestone.
     */
    public function getMilestone(string $id): Milestone
    {
        return Milestone::findOrFail($id);
    }

    /**
     * Get user milestones with optional filters.
     */
    public function getUserMilestones(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = UserMilestone::with(['milestone', 'iconImage', 'moment', 'kid'])
            ->forUser($user->id)
            ->orderByDate('desc');

        if (!empty($filters['kid_id'])) {
            $query->forKid($filters['kid_id']);
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'custom') {
                $query->custom();
            } elseif ($filters['type'] === 'predefined') {
                $query->predefined();
            }
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Create a user milestone (predefined or custom).
     */
    public function createUserMilestone(User $user, Kid $kid, array $data): UserMilestone
    {
        return DB::transaction(function () use ($user, $kid, $data) {
            // Create a new moment with milestone type if no moment_id provided
            $momentId = $data['moment_id'] ?? null;
            
            if (!$momentId) {
                $moment = Moment::create([
                    'id' => Str::uuid()->toString(),
                    'kid_id' => $kid->id,
                    'moment_type' => 'milestone',
                    'title' => $data['custom_title'] ?? null,
                ]);
                $momentId = $moment->id;
            }

            // Create user milestone
            $milestone = UserMilestone::create([
                'id' => Str::uuid()->toString(),
                'kid_id' => $kid->id,
                'user_id' => $user->id,
                'milestone_id' => $data['milestone_id'] ?? null,
                'custom_title' => $data['custom_title'] ?? null,
                'custom_description' => $data['custom_description'] ?? null,
                'milestone_date' => $data['milestone_date'],
                'notes' => $data['notes'] ?? null,
                'icon_image_id' => $data['icon_image_id'] ?? null,
                'moment_id' => $momentId,
            ]);

            $milestone->load(['milestone', 'iconImage', 'moment']);

            Log::info('User milestone created', [
                'milestone_id' => $milestone->id,
                'kid_id' => $kid->id,
                'is_custom' => $milestone->isCustom(),
            ]);

            return $milestone;
        });
    }

    /**
     * Get a single user milestone.
     */
    public function getUserMilestone(UserMilestone $milestone): UserMilestone
    {
        $milestone->load(['milestone', 'iconImage', 'moment']);
        
        return $milestone;
    }

    /**
     * Update a user milestone.
     */
    public function updateUserMilestone(UserMilestone $milestone, array $data): UserMilestone
    {
        $milestone->update($data);
        $milestone->load(['milestone', 'iconImage', 'moment']);
        
        return $milestone;
    }

    /**
     * Delete a user milestone.
     */
    public function deleteUserMilestone(UserMilestone $milestone): void
    {
        $milestone->delete();
    }

    /**
     * Validate milestone exists.
     */
    public function validateMilestone(?string $id): bool
    {
        if (empty($id)) {
            return true;
        }
        
        return Milestone::where('id', $id)->exists();
    }

    /**
     * Validate icon image belongs to user.
     */
    public function validateIconImage(User $user, ?string $iconImageId): bool
    {
        if (empty($iconImageId)) {
            return true;
        }

        $iconImage = Media::find($iconImageId);
        
        return $iconImage && $iconImage->user_id === $user->id;
    }

    /**
     * Validate moment belongs to kid.
     */
    public function validateMoment(Kid $kid, ?string $momentId): bool
    {
        if (empty($momentId)) {
            return true;
        }

        $moment = Moment::find($momentId);
        
        return $moment && $moment->kid_id === $kid->id;
    }
}
