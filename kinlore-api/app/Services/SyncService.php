<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Milestone;
use App\Models\Moment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling WatermelonDB sync operations.
 */
class SyncService
{
    /**
     * Pull changes from server since last sync.
     *
     * @return array{changes: array, timestamp: int}
     */
    public function pull(User $user, ?int $lastPulledAt): array
    {
        $lastPulledAtDate = $lastPulledAt
            ? Carbon::createFromTimestamp($lastPulledAt / 1000)
            : null;

        return [
            'changes' => [
                'kids' => $this->getKidsChanges($user, $lastPulledAtDate),
                'moments' => $this->getMomentsChanges($user, $lastPulledAtDate),
                'milestones' => $this->getMilestonesChanges($lastPulledAtDate),
            ],
            'timestamp' => now()->timestamp * 1000,
        ];
    }

    /**
     * Get kids changes since last sync.
     */
    private function getKidsChanges(User $user, ?Carbon $lastPulledAt): array
    {
        $query = Kid::where('user_id', $user->id);
        
        if ($lastPulledAt) {
            $query->where('updated_at', '>', $lastPulledAt);
        }

        $kids = $query->withTrashed()->get();

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($kids as $kid) {
            if ($kid->deleted_at) {
                $deleted[] = $kid->id;
                continue;
            }

            $kidData = $this->formatKidForSync($kid);

            if (!$lastPulledAt || $kid->created_at > $lastPulledAt) {
                $created[] = $kidData;
            } else {
                $updated[] = $kidData;
            }
        }

        return compact('created', 'updated', 'deleted');
    }

    /**
     * Get moments changes since last sync.
     */
    private function getMomentsChanges(User $user, ?Carbon $lastPulledAt): array
    {
        $query = Moment::whereHas('kid', fn ($q) => $q->where('user_id', $user->id));

        if ($lastPulledAt) {
            $query->where('updated_at', '>', $lastPulledAt);
        }

        $moments = $query->withTrashed()->get();

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($moments as $moment) {
            if ($moment->deleted_at) {
                $deleted[] = $moment->id;
                continue;
            }

            $momentData = $this->formatMomentForSync($moment);

            if (!$lastPulledAt || $moment->created_at > $lastPulledAt) {
                $created[] = $momentData;
            } else {
                $updated[] = $momentData;
            }
        }

        return compact('created', 'updated', 'deleted');
    }

    /**
     * Get milestones changes since last sync.
     */
    private function getMilestonesChanges(?Carbon $lastPulledAt): array
    {
        $query = Milestone::query();
        
        if ($lastPulledAt) {
            $query->where('updated_at', '>', $lastPulledAt);
        }

        $milestones = $query->orderBy('stage_key')->orderBy('sort_order')->get();

        $created = [];
        $updated = [];

        foreach ($milestones as $milestone) {
            $milestoneData = $this->formatMilestoneForSync($milestone);

            if (!$lastPulledAt || $milestone->created_at > $lastPulledAt) {
                $created[] = $milestoneData;
            } else {
                $updated[] = $milestoneData;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => [],
        ];
    }

    /**
     * Push changes from client to server.
     */
    public function push(User $user, array $changes): void
    {
        if (isset($changes['kids'])) {
            $this->pushKidsChanges($user, $changes['kids']);
        }

        // Note: Moments are typically created via upload endpoint, not sync
        // But we can handle updates and deletes

        Log::info('Sync push completed', ['user_id' => $user->id]);
    }

    /**
     * Push kids changes to server.
     */
    private function pushKidsChanges(User $user, array $kidsChanges): void
    {
        // Created kids
        if (!empty($kidsChanges['created'])) {
            foreach ($kidsChanges['created'] as $kidData) {
                Kid::updateOrCreate(
                    ['id' => $kidData['id']],
                    [
                        'user_id' => $user->id,
                        'name' => $kidData['name'],
                        'date_of_birth' => Carbon::createFromTimestamp($kidData['date_of_birth'] / 1000),
                        'avatar_color' => $kidData['avatar_color'],
                    ]
                );
            }
        }

        // Updated kids
        if (!empty($kidsChanges['updated'])) {
            foreach ($kidsChanges['updated'] as $kidData) {
                $kid = Kid::find($kidData['id']);
                if ($kid && $kid->user_id === $user->id) {
                    $kid->update([
                        'name' => $kidData['name'],
                        'date_of_birth' => Carbon::createFromTimestamp($kidData['date_of_birth'] / 1000),
                        'avatar_color' => $kidData['avatar_color'],
                    ]);
                }
            }
        }

        // Deleted kids
        if (!empty($kidsChanges['deleted'])) {
            foreach ($kidsChanges['deleted'] as $kidId) {
                $kid = Kid::find($kidId);
                if ($kid && $kid->user_id === $user->id) {
                    $kid->delete();
                }
            }
        }
    }

    /**
     * Format kid for sync response.
     */
    public function formatKidForSync(Kid $kid): array
    {
        return [
            'id' => $kid->id,
            'name' => $kid->name,
            'date_of_birth' => $kid->date_of_birth->timestamp * 1000,
            'avatar_color' => $kid->avatar_color,
            'created_at' => $kid->created_at->timestamp * 1000,
            'updated_at' => $kid->updated_at->timestamp * 1000,
        ];
    }

    /**
     * Format moment for sync response.
     */
    public function formatMomentForSync(Moment $moment): array
    {
        return [
            'id' => $moment->id,
            'kid_id' => $moment->kid_id,
            'moment_type' => $moment->moment_type,
            'milestone_id' => $moment->milestone_id,
            'title' => $moment->title,
            'description' => $moment->description,
            'photo_url' => $moment->getRawOriginal('photo_url'),
            'medium_url' => $moment->getRawOriginal('medium_url'),
            'thumbnail_url' => $moment->getRawOriginal('thumbnail_url'),
            'file_size' => $moment->file_size,
            'mime_type' => $moment->mime_type,
            'exif_date' => $moment->exif_date?->timestamp * 1000,
            'latitude' => $moment->latitude,
            'longitude' => $moment->longitude,
            'created_at' => $moment->created_at->timestamp * 1000,
            'updated_at' => $moment->updated_at->timestamp * 1000,
        ];
    }

    /**
     * Format milestone for sync response.
     */
    public function formatMilestoneForSync(Milestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'category' => $milestone->category,
            'stage_key' => $milestone->stage_key,
            'stage_label' => $milestone->stage_label,
            'age_min_months' => $milestone->age_min_months,
            'age_max_months' => $milestone->age_max_months,
            'sort_order' => $milestone->sort_order,
            'created_at' => $milestone->created_at?->timestamp * 1000,
            'updated_at' => $milestone->updated_at?->timestamp * 1000,
        ];
    }
}
