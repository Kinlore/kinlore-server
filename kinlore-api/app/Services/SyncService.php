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
        // Get the user's primary server (create one if missing, though typically Auth registers it)
        $server = $user->servers()->first();
        
        if (!$server) {
            // Fallback: If for some reason no server exists, we can't create a Kid properly.
            // But we can check if they own one? 
            // Ideally should fail or auto-create.
            // Let's log error and skip changes, or auto-create?
            // Auth flow guarantees server.
            Log::error('Sync push failed: User has no server', ['user_id' => $user->id]);
            return;
        }

        // Created kids
        if (!empty($kidsChanges['created'])) {
            foreach ($kidsChanges['created'] as $kidData) {
                $kid = Kid::withTrashed()->find($kidData['id']);
                
                $data = [
                    'name' => $kidData['name'],
                    'date_of_birth' => Carbon::createFromTimestamp((float)($kidData['date_of_birth'] ?? 0) / 1000),
                    'avatar_color' => $kidData['avatar_color'],
                    // 'server_id' => $server->id // Should we update server_id? 
                    // If we move kids between servers? For now let's assume server_id is sticky or set on create.
                    // If valid move, update it. If accidental, maybe not.
                    // Let's stick to update mutable fields.
                ];

                if ($kid) {
                    // Update existing
                    if ($kid->trashed()) {
                        $kid->restore();
                    }
                    
                    // Only update if authorized (server check)
                    if ($kid->server_id == $server->id || $user->servers->contains('id', $kid->server_id)) {
                         $kid->update($data);
                    }
                } else {
                    // Create new
                    $data['id'] = $kidData['id'];
                    $data['user_id'] = $user->id;
                    $data['server_id'] = $server->id;
                    Kid::create($data);
                }
            }
        }

        // Updated kids
        if (!empty($kidsChanges['updated'])) {
            foreach ($kidsChanges['updated'] as $kidData) {
                $kid = Kid::find($kidData['id']);
                // Check if kid belongs to one of user's servers or just the server we picked?
                // Ideally check 'server_id' matches one of user's servers.
                // For now, check if user is a member of the kid's server permissions.
                // Simplified: User can update if they are member of the kid's server.
                
                if ($kid && ($kid->server_id /* check membership */)) { 
                    // To keep it simple and performant, we assume if we found the kid and the user has access via Policy (already checked?), 
                    // but Sync is controller level. 
                    // Let's just check if the Kid's server is in user's servers list? Too expensive for loop.
                    // Or relies on 'server_id' being correct.
                    // Previous code checked `user_id === user->id`.
                    // Now we check `user->servers->contains($kid->server_id)`.
                    
                    // We can use the helper: $user->can('update', $kid)? No, Sync command doesn't use Policy easily inside loop.
                    
                    // Let's check if the kid belongs to the server we picked OR any server user owns.
                    // Actually, if we just check ownership:
                }
                
                // Better approach:
                // Just use the server we fetched above? No, Kid might belong to a DIFFERENT server of the user.
                // But Sync implies User context. 
                // Let's allow update if the Kid exists. The constraint is handled by model/logic.
                // But we must respect the new ownership model.
                
                if ($kid && ($kid->server_id == $server->id || $user->servers->contains('id', $kid->server_id))) {
                    $kid->update([
                        'name' => $kidData['name'],
                        'date_of_birth' => Carbon::createFromTimestamp((float)($kidData['date_of_birth'] ?? 0) / 1000),
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
