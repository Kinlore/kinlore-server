<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Milestone;
use App\Models\Moment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling WatermelonDB sync operations (docs/API_CONTRACT.md section 9).
 * Every time in a sync payload is integer milliseconds since the epoch, or null.
 */
class SyncService
{
    private const PUSH_MOMENT_TYPES = ['story', 'milestone', 'growth'];

    public function __construct(
        private MomentService $momentService
    ) {}

    /**
     * Pull changes from server since last sync.
     *
     * @return array{changes: array, timestamp: int}
     */
    public function pull(User $user, ?int $lastPulledAt): array
    {
        $timestamp = now()->getTimestampMs();

        // Database times have second precision, so look one second back; the client upserts.
        $since = $lastPulledAt ? Carbon::createFromTimestampMs($lastPulledAt - 1000) : null;

        return [
            'changes' => [
                'kids' => $this->getKidsChanges($since),
                'moments' => $this->getMomentsChanges($user, $since),
                'milestones' => $this->getMilestonesChanges($since),
            ],
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Get kids changes since last sync.
     */
    private function getKidsChanges(?Carbon $since): array
    {
        $kids = Kid::withTrashed()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($kids as $kid) {
            if ($kid->trashed()) {
                $deleted[] = $kid->id;
            } elseif (!$since || $kid->created_at >= $since) {
                $created[] = $this->formatKidForSync($kid);
            } else {
                $updated[] = $this->formatKidForSync($kid);
            }
        }

        return compact('created', 'updated', 'deleted');
    }

    /**
     * Get moments changes since last sync. Moments of a deleted kid count as deleted.
     */
    private function getMomentsChanges(User $user, ?Carbon $since): array
    {
        $moments = Moment::withTrashed()
            ->forPayload($user)
            ->with(['kid' => fn ($q) => $q->withTrashed()->select('id', 'deleted_at')])
            ->when($since, fn ($q) => $q->where(fn ($q) => $q
                ->where('moments.updated_at', '>=', $since)
                ->orWhereIn('kid_id', Kid::onlyTrashed()->where('deleted_at', '>=', $since)->select('id'))))
            ->get();

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($moments as $moment) {
            if ($moment->trashed() || !$moment->kid || $moment->kid->trashed()) {
                $deleted[] = $moment->id;
            } elseif (!$since || $moment->created_at >= $since) {
                $created[] = $this->formatMomentForSync($moment);
            } else {
                $updated[] = $this->formatMomentForSync($moment);
            }
        }

        return compact('created', 'updated', 'deleted');
    }

    /**
     * Get milestones changes since last sync.
     */
    private function getMilestonesChanges(?Carbon $since): array
    {
        $milestones = Milestone::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('stage_key')
            ->orderBy('sort_order')
            ->get();

        $created = [];
        $updated = [];

        foreach ($milestones as $milestone) {
            if (!$since || $milestone->created_at >= $since) {
                $created[] = $this->formatMilestoneForSync($milestone);
            } else {
                $updated[] = $this->formatMilestoneForSync($milestone);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => [],
        ];
    }

    /**
     * Push changes from client to server. Each change passes the same permission rules as REST;
     * a change that fails goes into `rejected` and the others still apply.
     *
     * @return array{kids_created: array, moments_created: array, rejected: array}
     */
    public function push(User $user, array $changes): array
    {
        $result = ['kids_created' => [], 'moments_created' => [], 'rejected' => []];
        $kids = $changes['kids'] ?? [];
        $moments = $changes['moments'] ?? [];

        // Kids first: new moments may belong to a kid from the same push.
        $this->apply($result, 'kids', 'kids_created', $kids['created'] ?? [], fn ($row) => $this->upsertKid($user, $row));
        $this->apply($result, 'kids', null, $kids['updated'] ?? [], fn ($row) => $this->upsertKid($user, $row));
        $this->apply($result, 'moments', 'moments_created', $moments['created'] ?? [], fn ($row) => $this->upsertMoment($user, $row));
        $this->apply($result, 'moments', null, $moments['updated'] ?? [], fn ($row) => $this->upsertMoment($user, $row));
        $this->apply($result, 'moments', null, $moments['deleted'] ?? [], fn ($id) => $this->deleteMoment($user, $id));
        $this->apply($result, 'kids', null, $kids['deleted'] ?? [], fn ($id) => $this->deleteKid($user, $id));

        Log::info('Sync push completed', ['user_id' => $user->id, 'rejected' => count($result['rejected'])]);

        return $result;
    }

    /**
     * Run $change for each row; the closure returns null on success or a reason string.
     */
    private function apply(array &$result, string $table, ?string $createdKey, array $rows, \Closure $change): void
    {
        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : $row;

            try {
                $reason = is_string($id) && $id !== '' ? $change($row) : 'invalid';
            } catch (\Throwable $e) {
                Log::warning('Sync push change failed', ['table' => $table, 'id' => $id, 'error' => $e->getMessage()]);
                $reason = 'invalid';
            }

            if ($reason !== null) {
                $result['rejected'][] = ['table' => $table, 'id' => $id, 'reason' => $reason];
            } elseif ($createdKey) {
                $result[$createdKey][] = ['local_id' => $id, 'id' => $id];
            }
        }
    }

    private function upsertKid(User $user, array $row): ?string
    {
        $kid = Kid::withTrashed()->find($row['id']);

        if ($kid?->trashed()) {
            return 'deleted';
        }

        if ($kid ? !$user->can('update', $kid) : !$user->can('create', Kid::class)) {
            return 'forbidden';
        }

        $kid ??= tap(new Kid(['user_id' => $user->id]), fn ($k) => $k->id = $row['id']);

        $kid->fill(array_filter([
            'name' => $row['name'] ?? null,
            'avatar_color' => $row['avatar_color'] ?? null,
        ], fn ($v) => $v !== null));

        foreach (['date_of_birth', 'due_date'] as $field) {
            if (array_key_exists($field, $row)) {
                $kid->{$field} = $this->msToDate($row[$field]);
            }
        }

        if (empty($kid->name) || (!$kid->date_of_birth && !$kid->due_date)
            || ($kid->date_of_birth && $kid->date_of_birth->isAfter(today()))) {
            return 'invalid';
        }

        $kid->save();

        return null;
    }

    private function upsertMoment(User $user, array $row): ?string
    {
        $moment = Moment::withTrashed()->find($row['id']);

        if ($moment?->trashed()) {
            return 'deleted';
        }

        if ($moment) {
            if (!$user->can('update', $moment)) {
                return 'forbidden';
            }

            // Photo and video rows come from uploads; sync may only change their text and date.
            $fields = in_array($moment->moment_type, self::PUSH_MOMENT_TYPES, true)
                ? ['title', 'description', 'milestone_id', 'exif_date', 'height_cm', 'weight_kg', 'head_circumference_cm']
                : ['title', 'description', 'exif_date'];
        } else {
            if (!in_array($row['moment_type'] ?? null, self::PUSH_MOMENT_TYPES, true) || !Kid::find($row['kid_id'] ?? null)) {
                return 'invalid';
            }

            if (!$user->hasPermission('add_content') || !$user->can('create', Moment::class)) {
                return 'forbidden';
            }

            $moment = new Moment(['kid_id' => $row['kid_id'], 'user_id' => $user->id, 'moment_type' => $row['moment_type']]);
            $moment->id = $row['id'];
            $fields = ['title', 'description', 'milestone_id', 'exif_date', 'height_cm', 'weight_kg', 'head_circumference_cm'];
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $moment->{$field} = $field === 'exif_date' && $row[$field] !== null
                    ? Carbon::createFromTimestampMs($row[$field])
                    : $row[$field];
            }
        }

        // Same rules as REST (StoreMomentRequest): a story needs text; growth needs a date and one measurement.
        if ($moment->moment_type === 'story' && blank($moment->description)) {
            return 'invalid';
        }
        if ($moment->moment_type === 'growth' && (!$moment->exif_date
            || ($moment->height_cm === null && $moment->weight_kg === null && $moment->head_circumference_cm === null))) {
            return 'invalid';
        }
        if ($moment->moment_type === 'milestone' && blank($moment->milestone_id)) {
            return 'invalid';
        }

        $moment->save();

        return null;
    }

    private function deleteMoment(User $user, string $id): ?string
    {
        $moment = Moment::find($id);

        if (!$moment) {
            return null; // already gone
        }

        if (!$user->can('delete', $moment)) {
            return 'forbidden';
        }

        $this->momentService->deleteMoment($moment);

        return null;
    }

    private function deleteKid(User $user, string $id): ?string
    {
        $kid = Kid::find($id);

        if (!$kid) {
            return null;
        }

        if (!$user->can('delete', $kid)) {
            return 'forbidden';
        }

        $kid->delete();

        return null;
    }

    /** Calendar date from ms at UTC midnight. */
    private function msToDate(mixed $ms): ?string
    {
        return $ms === null ? null : Carbon::createFromTimestampMs($ms, 'UTC')->toDateString();
    }

    /** Ms at UTC midnight of a calendar date. */
    private function dateToMs(?\DateTimeInterface $date): ?int
    {
        return $date ? Carbon::parse($date->format('Y-m-d'), 'UTC')->getTimestampMs() : null;
    }

    /**
     * Format kid for sync response.
     */
    public function formatKidForSync(Kid $kid): array
    {
        return [
            'id' => $kid->id,
            'user_id' => $kid->user_id,
            'name' => $kid->name,
            'date_of_birth' => $this->dateToMs($kid->date_of_birth),
            'due_date' => $this->dateToMs($kid->due_date),
            'avatar_color' => $kid->avatar_color,
            'created_at' => $kid->created_at->getTimestampMs(),
            'updated_at' => $kid->updated_at->getTimestampMs(),
        ];
    }

    /**
     * Format moment for sync response (load it with Moment::forPayload).
     */
    public function formatMomentForSync(Moment $moment): array
    {
        return [
            'id' => $moment->id,
            'kid_id' => $moment->kid_id,
            'user_id' => $moment->user_id,
            'uploader_name' => $moment->user?->name,
            'moment_type' => $moment->moment_type ?? 'photo',
            'title' => $moment->title,
            'description' => $moment->description,
            'milestone_id' => $moment->milestone_id,
            'exif_date' => $moment->exif_date?->getTimestampMs(),
            'latitude' => $moment->latitude,
            'longitude' => $moment->longitude,
            'orientation' => $moment->orientation,
            'duration' => $moment->duration,
            'video_status' => $moment->video_status,
            'height_cm' => $moment->height_cm,
            'weight_kg' => $moment->weight_kg,
            'head_circumference_cm' => $moment->head_circumference_cm,
            'likes_count' => (int) $moment->likes_count,
            'comments_count' => (int) $moment->comments_count,
            'is_liked' => (bool) $moment->is_liked,
            'photo_url' => $moment->getRawOriginal('photo_url'),
            'medium_url' => $moment->getRawOriginal('medium_url'),
            'thumbnail_url' => $moment->getRawOriginal('thumbnail_url'),
            'file_size' => $moment->file_size,
            'mime_type' => $moment->mime_type,
            'device_asset_id' => $moment->device_asset_id,
            'created_at' => $moment->created_at->getTimestampMs(),
            'updated_at' => $moment->updated_at->getTimestampMs(),
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
            'created_at' => $milestone->created_at?->getTimestampMs(),
            'updated_at' => $milestone->updated_at?->getTimestampMs(),
        ];
    }
}
