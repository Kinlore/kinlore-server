<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Service for handling Kid operations.
 */
class KidService
{
    /**
     * Get all kids for a user.
     */
    public function getKidsForUser(User $user): Collection
    {
        return Kid::withCount('moments')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Kid $kid) => $this->formatKid($kid));
    }

    /**
     * Create a new kid for a user.
     */
    public function createKid(User $user, array $data): Kid
    {
        return $user->kids()->create([
            'name' => $data['name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'avatar_color' => $data['avatar_color'] ?? Kid::randomAvatarColor(),
        ]);
    }

    /**
     * Get kid details with stats.
     */
    public function getKidDetails(Kid $kid): array
    {
        $kid->loadCount('moments');

        return $this->formatKid($kid) + [
            'first_moment' => $kid->getFirstMoment()?->display_date?->toIso8601String(),
            'latest_moment' => $kid->getLatestMoment()?->display_date?->toIso8601String(),
        ];
    }

    /**
     * Update a kid.
     */
    public function updateKid(Kid $kid, array $data): Kid
    {
        $kid->update($data);
        
        return $kid->fresh();
    }

    /**
     * Delete a kid (soft delete).
     */
    public function deleteKid(Kid $kid): void
    {
        $kid->delete();
    }

    /**
     * Format kid for API response.
     */
    public function formatKid(Kid $kid): array
    {
        return $kid->toArray();
    }
}
