<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Kid;
use App\Models\User;
use Illuminate\Support\Collection;

class KidService
{
    public function getKidsForUser(User $user): Collection
    {
        return Kid::withCount('moments')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map($this->formatKid(...));
    }

    public function createKid(User $user, array $data): Kid
    {
        return $user->kids()->create([
            'name' => $data['name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'avatar_color' => $data['avatar_color'] ?? Kid::randomAvatarColor(),
        ]);
    }

    public function getKidDetails(Kid $kid): array
    {
        $kid->loadCount('moments');

        return $this->formatKid($kid) + [
            'first_moment' => $kid->getFirstMoment()?->display_date?->toIso8601String(),
            'latest_moment' => $kid->getLatestMoment()?->display_date?->toIso8601String(),
        ];
    }

    public function updateKid(Kid $kid, array $data): Kid
    {
        $kid->update($data);

        return $kid->fresh();
    }

    // Soft delete
    public function deleteKid(Kid $kid): void
    {
        $kid->delete();
    }

    public function formatKid(Kid $kid): array
    {
        return $kid->toArray();
    }
}
