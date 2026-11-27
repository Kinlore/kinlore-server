<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MomentType;
use App\Http\Requests\Api\StoreMomentRequest;
use App\Models\Kid;
use App\Models\Milestone;
use App\Models\Moment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Offline sync for the app (docs/reference/api.md section 9).
 * Every time in a sync payload is integer milliseconds since the epoch, or null.
 */
class SyncService
{
    /** Most moments in one pull response (docs/reference/api.md section 9). */
    public const PULL_PAGE_SIZE = 2000;

    private const PUSH_MOMENT_TYPES = [MomentType::Story->value, MomentType::Milestone->value, MomentType::Growth->value];

    /** Same limits as the REST requests (StoreKidRequest, StoreMomentRequest); every row also needs a uuid id. */
    private const PUSH_ROW_RULES = [
        'kids' => [
            'name' => 'nullable|string|max:255',
            'avatar_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ],
        'moments' => [
            'kid_id' => 'nullable|uuid',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'milestone_id' => 'nullable|string|exists:milestones,id',
        ],
    ];

    public function __construct(
        private readonly MomentService $momentService,
    ) {}

    /**
     * @return array{changes: array, timestamp: int}
     */
    public function pull(User $user, ?int $lastPulledAt, ?string $cursor = null, int $limit = self::PULL_PAGE_SIZE): array
    {
        $timestamp = now()->getTimestampMs();

        // Database times have second precision, so look one second back; the client upserts.
        $since = $lastPulledAt ? Carbon::createFromTimestampMs($lastPulledAt - 1000) : null;

        $none = ['created' => [], 'updated' => [], 'deleted' => []];
        [$moments, $nextCursor] = $this->getMomentsChanges($user, $since, $cursor, $limit);

        return [
            'changes' => [
                // Kids and milestones are few: they come whole with the first page only.
                'kids' => $cursor === null ? $this->getKidsChanges($since) : $none,
                'moments' => $moments,
                'milestones' => $cursor === null ? $this->getMilestonesChanges($since) : $none,
            ],
            'timestamp' => $timestamp,
            'has_more' => $nextCursor !== null,
            'next_cursor' => $nextCursor,
        ];
    }

    private function getKidsChanges(?Carbon $since): array
    {
        $kids = Kid::withTrashed()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        return $this->sortChanges($kids, $since, $this->formatKidForSync(...), fn (Kid $kid): bool => $kid->trashed());
    }

    /**
     * One page of moments changes since last sync, and the cursor of the next page (null on the last).
     * Moments of a deleted kid count as deleted.
     */
    private function getMomentsChanges(User $user, ?Carbon $since, ?string $cursor, int $limit): array
    {
        // Pages go in id order: the filter is the same on every page, so no row is skipped.
        $moments = Moment::withTrashed()
            ->forPayload($user)
            ->with(['kid' => fn ($q) => $q->withTrashed()->select('id', 'deleted_at')])
            ->when($since, fn ($q) => $q->where(fn ($q) => $q
                ->where('moments.updated_at', '>=', $since)
                ->orWhereIn('kid_id', Kid::onlyTrashed()->where('deleted_at', '>=', $since)->select('id'))))
            ->when($cursor, fn ($q) => $q->where('moments.id', '>', $cursor))
            ->orderBy('moments.id')
            ->limit($limit + 1)
            ->get();

        $nextCursor = null;
        if ($moments->count() > $limit) {
            $moments = $moments->take($limit);
            $nextCursor = $moments->last()->id;
        }

        $changes = $this->sortChanges($moments, $since, $this->formatMomentForSync(...),
            fn (Moment $moment): bool => $moment->trashed() || ! $moment->kid || $moment->kid->trashed());

        return [$changes, $nextCursor];
    }

    private function getMilestonesChanges(?Carbon $since): array
    {
        $milestones = Milestone::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('stage_key')
            ->orderBy('sort_order')
            ->get();

        return $this->sortChanges($milestones, $since, $this->formatMilestoneForSync(...));
    }

    /**
     * Sort rows into created, updated and deleted for the client: a row made since the last pull is created.
     *
     * @return array{created: array, updated: array, deleted: string[]}
     */
    private function sortChanges(iterable $rows, ?Carbon $since, \Closure $format, ?\Closure $isDeleted = null): array
    {
        $changes = ['created' => [], 'updated' => [], 'deleted' => []];

        foreach ($rows as $row) {
            if ($isDeleted && $isDeleted($row)) {
                $changes['deleted'][] = $row->id;
            } elseif (! $since || $row->created_at >= $since) {
                $changes['created'][] = $format($row);
            } else {
                $changes['updated'][] = $format($row);
            }
        }

        return $changes;
    }

    /**
     * Push changes from client to server. Each change passes the same permission rules as REST;
     * a change that fails goes into `rejected` and the others still apply.
     *
     * A created or updated row whose server copy changed after $lastPulledAt is rejected as `conflict`.
     *
     * @return array{kids_created: array, moments_created: array, rejected: array}
     */
    public function push(User $user, array $changes, ?int $lastPulledAt = null): array
    {
        $result = ['kids_created' => [], 'moments_created' => [], 'rejected' => []];
        $kids = $changes['kids'] ?? [];
        $moments = $changes['moments'] ?? [];

        // Kids first: new moments may belong to a kid from the same push.
        $this->apply($result, 'kids', 'kids_created', $kids['created'] ?? [], fn ($row) => $this->upsertKid($user, $row, $lastPulledAt));
        $this->apply($result, 'kids', null, $kids['updated'] ?? [], fn ($row) => $this->upsertKid($user, $row, $lastPulledAt));
        $this->apply($result, 'moments', 'moments_created', $moments['created'] ?? [], fn ($row) => $this->upsertMoment($user, $row, $lastPulledAt));
        $this->apply($result, 'moments', null, $moments['updated'] ?? [], fn ($row) => $this->upsertMoment($user, $row, $lastPulledAt));
        $this->apply($result, 'moments', null, $moments['deleted'] ?? [], fn ($id) => $this->deleteMoment($user, $id));
        $this->apply($result, 'kids', null, $kids['deleted'] ?? [], fn ($id) => $this->deleteKid($user, $id));

        Log::info('Sync push completed', ['user_id' => $user->id, 'rejected' => count($result['rejected'])]);

        return $result;
    }

    // Run $change for each valid row; the closure returns null on success or a reason string.
    // One transaction per row: the conflict check and the save see the same locked row.
    private function apply(array &$result, string $table, ?string $createdKey, array $rows, \Closure $change): void
    {
        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : $row;

            try {
                $valid = Validator::make(is_array($row) ? $row : ['id' => $row],
                    ['id' => 'required|uuid'] + (is_array($row) ? self::PUSH_ROW_RULES[$table] : []))->passes();
                $reason = $valid ? DB::transaction(fn () => $change($row)) : 'invalid';
            } catch (\Throwable $e) {
                Log::warning('Sync push change failed', ['table' => $table, 'id' => $id, 'error' => $e->getMessage()]);
                $reason = $e instanceof ValidationException ? 'invalid' : 'error';
            }

            if ($reason !== null) {
                $result['rejected'][] = ['table' => $table, 'id' => $id, 'reason' => $reason];
            } elseif ($createdKey) {
                $result[$createdKey][] = ['local_id' => $id, 'id' => $id];
            }
        }
    }

    private function upsertKid(User $user, array $row, ?int $lastPulledAt = null): ?string
    {
        $kid = Kid::withTrashed()->lockForUpdate()->find($row['id']);

        if ($kid?->trashed()) {
            return 'deleted';
        }

        if ($this->changedSince($user, $kid, $lastPulledAt)) {
            return 'conflict';
        }

        if ($kid ? ! $user->can('update', $kid) : ! $user->can('create', Kid::class)) {
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

        // tomorrow, as in StoreKidRequest: a parent east of UTC is already a day ahead of the server
        if (empty($kid->name) || (! $kid->date_of_birth && ! $kid->due_date)
            || ($kid->date_of_birth && $kid->date_of_birth->isAfter(today()->addDay()))) {
            return 'invalid';
        }

        $kid->save();

        return null;
    }

    private function upsertMoment(User $user, array $row, ?int $lastPulledAt = null): ?string
    {
        $moment = Moment::withTrashed()->lockForUpdate()->find($row['id']);

        if ($moment?->trashed()) {
            return 'deleted';
        }

        if ($this->changedSince($user, $moment, $lastPulledAt)) {
            return 'conflict';
        }

        if ($moment) {
            if (! $user->can('update', $moment)) {
                return 'forbidden';
            }

            // Photo and video rows come from uploads; sync may only change their text and date.
            $fields = in_array($moment->moment_type?->value, self::PUSH_MOMENT_TYPES, true)
                ? ['title', 'description', 'milestone_id', 'exif_date', 'height_cm', 'weight_kg', 'head_circumference_cm']
                : ['title', 'description', 'exif_date'];
        } else {
            if (! in_array($row['moment_type'] ?? null, self::PUSH_MOMENT_TYPES, true) || ! Kid::find($row['kid_id'] ?? null)) {
                return 'invalid';
            }

            if (! $user->hasPermission('add_content') || ! $user->can('create', Moment::class)) {
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
        if ($moment->moment_type === MomentType::Story && blank($moment->description)) {
            return 'invalid';
        }
        if ($moment->moment_type === MomentType::Growth && (! $moment->exif_date
            || ($moment->height_cm === null && $moment->weight_kg === null && $moment->head_circumference_cm === null))) {
            return 'invalid';
        }
        if ($moment->moment_type === MomentType::Milestone && blank($moment->milestone_id)) {
            return 'invalid';
        }
        // Out-of-range values overflow the decimal columns; apply() answers 'invalid' for this exception.
        Validator::make($moment->only(array_keys(StoreMomentRequest::GROWTH_RULES)), StoreMomentRequest::GROWTH_RULES)->validate();

        $moment->save();

        return null;
    }

    // The app sends 0 before its first pull: no known server state, so no conflict.
    // A moment counts from edited_at, so a like from another member does not reject an offline edit;
    // the pusher's own later edit (for example made online on the same phone) is no conflict either.
    // Database times have second precision and $lastPulledAt has ms: an edit in the same second as the
    // pull start may come after it, so the whole second counts as later (+999).
    private function changedSince(User $user, Kid|Moment|null $row, ?int $lastPulledAt): bool
    {
        if (! $row || ! $lastPulledAt || $row->edited_by === $user->id) {
            return false;
        }
        $changed = $row instanceof Moment ? $row->edited_at : $row->updated_at;

        return $changed->getTimestampMs() + 999 > $lastPulledAt;
    }

    private function deleteMoment(User $user, string $id): ?string
    {
        $moment = Moment::find($id);

        if (! $moment) {
            return null; // already gone
        }

        if (! $user->can('delete', $moment)) {
            return 'forbidden';
        }

        $this->momentService->deleteMoment($moment);

        return null;
    }

    private function deleteKid(User $user, string $id): ?string
    {
        $kid = Kid::find($id);

        if (! $kid) {
            return null;
        }

        if (! $user->can('delete', $kid)) {
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

    // Load the moment with Moment::forPayload
    public function formatMomentForSync(Moment $moment): array
    {
        return [
            'id' => $moment->id,
            'kid_id' => $moment->kid_id,
            'user_id' => $moment->user_id,
            'uploader_name' => $moment->user?->name,
            'moment_type' => ($moment->moment_type ?? MomentType::Photo)->value,
            'title' => $moment->title,
            'description' => $moment->description,
            'milestone_id' => $moment->milestone_id,
            'exif_date' => $moment->exif_date?->getTimestampMs(),
            'latitude' => $moment->latitude,
            'longitude' => $moment->longitude,
            'orientation' => $moment->orientation,
            'width' => $moment->width,
            'height' => $moment->height,
            'duration' => $moment->duration,
            'video_status' => $moment->video_status?->value,
            'height_cm' => $moment->height_cm,
            'weight_kg' => $moment->weight_kg,
            'head_circumference_cm' => $moment->head_circumference_cm,
            'likes_count' => (int) $moment->likes_count,
            'comments_count' => (int) $moment->comments_count,
            'is_liked' => (bool) $moment->is_liked,
            'file_size' => $moment->file_size,
            'mime_type' => $moment->mime_type,
            'device_asset_id' => $moment->device_asset_id,
            'created_at' => $moment->created_at->getTimestampMs(),
            'updated_at' => $moment->updated_at->getTimestampMs(),
        ];
    }

    public function formatMilestoneForSync(Milestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'title' => $milestone->title,
            'description' => $milestone->description,
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
