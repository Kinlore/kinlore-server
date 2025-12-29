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
    /**
     * Get all kids for a user across all servers they belong to.
     */
    public function getKidsForUser(User $user): Collection
    {
        $serverIds = $user->servers()->pluck('servers.id');
        
        return Kid::whereIn('server_id', $serverIds)
            ->withCount('moments')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Kid $kid) => $this->formatKid($kid));
    }

    /**
     * Create a new kid for a user.
     * Assigns to the user's owned server by default, or the first server they can contribute to.
     */
    public function createKid(User $user, array $data): Kid
    {
        // Find a suitable server
        $server = $user->ownedServers()->first();
        
        if (!$server) {
            // Fallback to any server they are a member of with contributor role
            $server = $user->servers()
                ->wherePivotIn('role', ['owner', 'contributor'])
                ->first();
        }
        
        if (!$server) {
             throw new \Exception("User does not belong to any server where they can create kids.");
        }

        // We keep user_id as the creator/original owner reference if needed, 
        // but server_id is the main grouping now.
        return Kid::create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => $data['name'],
            'date_of_birth' => $data['date_of_birth'],
            'avatar_color' => $data['avatar_color'] ?? Kid::randomAvatarColor(),
        ]);
    }

    /**
     * Get kid details with stats.
     */
    public function getKidDetails(Kid $kid): array
    {
        $kid->loadCount('moments');

        return [
            'id' => $kid->id,
            'name' => $kid->name,
            'date_of_birth' => $kid->date_of_birth->format('Y-m-d'),
            'avatar_color' => $kid->avatar_color,
            'age' => $kid->age,
            'age_detailed' => $kid->age_detailed,
            'age_in_months' => $kid->age_in_months,
            'moments_count' => $kid->moments_count,
            'first_moment' => $kid->getFirstMoment()?->display_date?->toIso8601String(),
            'latest_moment' => $kid->getLatestMoment()?->display_date?->toIso8601String(),
            'created_at' => $kid->created_at->toIso8601String(),
            'updated_at' => $kid->updated_at->toIso8601String(),
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
        return [
            'id' => $kid->id,
            'name' => $kid->name,
            'date_of_birth' => $kid->date_of_birth->format('Y-m-d'),
            'avatar_color' => $kid->avatar_color,
            'age' => $kid->age,
            'age_detailed' => $kid->age_detailed,
            'moments_count' => $kid->moments_count ?? 0,
            'created_at' => $kid->created_at->toIso8601String(),
            'updated_at' => $kid->updated_at->toIso8601String(),
        ];
    }
}
