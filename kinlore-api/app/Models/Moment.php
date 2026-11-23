<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InteractionType;
use App\Enums\MomentType;
use App\Enums\VideoStatus;
use App\Policies\MomentPolicy;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kid_id',
    'user_id',
    'video_id',
    'media_id',
    'moment_type',
    'milestone_id',
    'custom_milestone_id',
    'title',
    'description',
    'story_text',
    'photo_url',
    'thumbnail_url',
    'medium_url',
    'hls_url',
    'file_size',
    'mime_type',
    'original_filename',
    'device_asset_id',
    'device_fingerprint',
    'uploaded_from_device',
    'uploaded_from_ip',
    'exif_date',
    'latitude',
    'longitude',
    'orientation',
    'camera_make',
    'camera_model',
    'tags',
    'synced_at',
    'duration',
    'video_status',
    'height_cm',
    'weight_kg',
    'head_circumference_cm',
])]
#[Appends(['has_location', 'file_size_formatted'])]
#[UsePolicy(MomentPolicy::class)]
class Moment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    #[\Override]
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'exif_date' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'duration' => 'float',
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'head_circumference_cm' => 'float',
            'orientation' => 'integer',
            'tags' => 'array',
            'synced_at' => 'integer',
            'moment_type' => MomentType::class,
            'video_status' => VideoStatus::class,
        ];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    /** The uploader (moments.user_id). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(MomentInteraction::class);
    }

    public function likes(): HasMany
    {
        return $this->interactions()->where('type', InteractionType::Like);
    }

    public function comments(): HasMany
    {
        return $this->interactions()->where('type', InteractionType::Comment);
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function isVideo(): bool
    {
        return $this->moment_type === MomentType::Video;
    }

    protected function hasLocation(): Attribute
    {
        return Attribute::make(get: fn (): bool => !is_null($this->latitude) && !is_null($this->longitude));
    }

    protected function fileSizeFormatted(): Attribute
    {
        return Attribute::make(get: function (): string {
            $bytes = $this->file_size;

            if ($bytes >= 1073741824) {
                return number_format($bytes / 1073741824, 2) . ' GB';
            } elseif ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2) . ' MB';
            } elseif ($bytes >= 1024) {
                return number_format($bytes / 1024, 2) . ' KB';
            }

            return $bytes . ' bytes';
        });
    }

    protected function displayDate(): Attribute
    {
        // Prefer EXIF date over created_at
        return Attribute::make(get: fn (): mixed => $this->exif_date ?? $this->created_at)->withoutObjectCaching();
    }

    // The stored values are storage paths; reading them gives signed URLs.
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => $this->generateSignedUrl($value));
    }

    protected function mediumUrl(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => $this->generateSignedUrl($value));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => $this->generateSignedUrl($value));
    }

    protected function hlsUrl(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => $this->generateSignedUrl($value));
    }

    protected function generateSignedUrl(?string $path, int $expiration = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            return Storage::disk('seaweedfs')->temporaryUrl($path, now()->addMinutes($expiration));
        } catch (\Exception $e) {
            Log::error('Failed to generate signed URL: ' . $e->getMessage());
            return Storage::disk('seaweedfs')->url($path);
        }
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            $this->save();
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (($key = array_search($tag, $tags)) !== false) {
            unset($tags[$key]);
            $this->tags = array_values($tags);
            $this->save();
        }
    }

    #[Scope]
    protected function orderByDate(Builder $query, string $direction = 'desc'): void
    {
        $query->orderByRaw('COALESCE(exif_date, created_at) ' . $direction);
    }

    #[Scope]
    protected function inDateRange(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('exif_date', [$startDate, $endDate])
              ->orWhereBetween('created_at', [$startDate, $endDate]);
    }

    /** Everything the moment payload needs, without N+1: uploader, counts and the caller's like. */
    #[Scope]
    protected function forPayload(Builder $query, ?User $viewer = null): void
    {
        $query->with(['user:id,name', 'milestone'])
            ->withCount(['likes', 'comments'])
            ->withExists(['likes as is_liked' => fn (Builder $q) => $q->where('user_id', $viewer?->id)]);
    }

    #[Scope]
    protected function byDevice(Builder $query, string $deviceAssetId): void
    {
        $query->where('device_asset_id', $deviceAssetId);
    }

    #[\Override]
    protected static function booted(): void
    {
        static::updated(function (Moment $moment): void {
            if (!$moment->isDirty('synced_at')) {
                $moment->synced_at = now()->timestamp * 1000;
                $moment->saveQuietly();
            }
        });

        static::forceDeleted(function (Moment $moment): void {
            try {
                Storage::disk('seaweedfs')->delete([
                    $moment->attributes['photo_url'],
                    $moment->attributes['medium_url'],
                    $moment->attributes['thumbnail_url'],
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to delete moment files: ' . $e->getMessage());
            }
        });
    }

    #[\Override]
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'kid_id' => $this->kid_id,
            'user_id' => $this->user_id,
            'uploader_name' => $this->user?->name,
            'video_id' => $this->video_id,
            'moment_type' => ($this->moment_type ?? MomentType::Photo)->value,
            'milestone_id' => $this->milestone_id,
            'custom_milestone_id' => $this->custom_milestone_id,
            'title' => $this->title,
            'description' => $this->description,
            'story_text' => $this->story_text,
            'photo_url' => $this->photo_url,
            'medium_url' => $this->medium_url,
            'thumbnail_url' => $this->thumbnail_url,
            'hls_url' => $this->isVideo() ? $this->hls_url : null,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'mime_type' => $this->mime_type,
            'original_filename' => $this->original_filename,
            'device_asset_id' => $this->device_asset_id,
            'exif_date' => $this->exif_date?->toIso8601String(),
            'display_date' => $this->display_date?->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'has_location' => $this->has_location,
            'orientation' => $this->orientation,
            'camera_make' => $this->camera_make,
            'camera_model' => $this->camera_model,
            'duration' => $this->duration,
            'video_status' => $this->video_status?->value,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'head_circumference_cm' => $this->head_circumference_cm,
            'likes_count' => $this->interactionStat('likes_count', fn (): int => $this->likes()->count()),
            'comments_count' => $this->interactionStat('comments_count', fn (): int => $this->comments()->count()),
            'is_liked' => (bool) $this->interactionStat('is_liked', fn (): bool => ($viewer = auth()->user()) ? $this->isLikedBy($viewer) : false),
            'tags' => $this->tags,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];

        if ($this->relationLoaded('milestone') && $this->milestone) {
            $data['milestone'] = $this->milestone->toArray();
        }

        return $data;
    }

    /** Value from the forPayload scope, or one query when the scope was not used. */
    private function interactionStat(string $key, \Closure $fallback): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $fallback();
    }
}
