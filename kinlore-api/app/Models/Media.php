<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kid_id',
    'user_id',
    'type',
    'original_filename',
    'file_path',
    'mime_type',
    'file_size',
    'exif_data',
    'video_metadata',
    'thumbnail_url',
    'medium_url',
    'hls_url',
    'status',
    'processing_error',
    'processing_started_at',
    'processing_completed_at',
    'device_asset_id',
    'device_fingerprint',
])]
#[Appends(['file_size_formatted', 'is_ready', 'has_location'])]
class Media extends Model
{
    use HasUuids, SoftDeletes;

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string TYPE_PHOTO = 'photo';

    public const string TYPE_VIDEO = 'video';

    #[\Override]
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'exif_data' => 'array',
            'video_metadata' => 'array',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
        ];
    }

    public function moments(): HasMany
    {
        return $this->hasMany(Moment::class);
    }

    public function isPhoto(): bool
    {
        return $this->type === self::TYPE_PHOTO;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    protected function isReady(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->isCompleted());
    }

    public function markAsCompleted(array $data = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_COMPLETED,
            'processing_completed_at' => now(),
        ], $data));
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'processing_error' => $errorMessage,
            'processing_completed_at' => now(),
        ]);
    }

    // EXIF values come from form fields, so they are strings until cast here.
    protected function latitude(): Attribute
    {
        return Attribute::make(get: fn (): ?float => isset($this->exif_data['latitude']) ? (float) $this->exif_data['latitude'] : null);
    }

    protected function longitude(): Attribute
    {
        return Attribute::make(get: fn (): ?float => isset($this->exif_data['longitude']) ? (float) $this->exif_data['longitude'] : null);
    }

    protected function orientation(): Attribute
    {
        return Attribute::make(get: fn (): ?int => isset($this->exif_data['orientation']) ? (int) $this->exif_data['orientation'] : null);
    }

    protected function cameraMake(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->exif_data['camera_make'] ?? null);
    }

    protected function cameraModel(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->exif_data['camera_model'] ?? null);
    }

    protected function exifDate(): Attribute
    {
        return Attribute::make(get: function (): ?Carbon {
            $date = $this->exif_data['exif_date'] ?? null;

            return $date ? Carbon::parse($date) : null;
        })->withoutObjectCaching();
    }

    protected function hasLocation(): Attribute
    {
        return Attribute::make(get: fn (): bool => ! is_null($this->latitude) && ! is_null($this->longitude));
    }

    protected function duration(): Attribute
    {
        return Attribute::make(get: fn (): ?float => isset($this->video_metadata['duration']) ? (float) $this->video_metadata['duration'] : null);
    }

    protected function width(): Attribute
    {
        return Attribute::make(get: fn (): ?int => isset($this->video_metadata['width']) ? (int) $this->video_metadata['width'] : null);
    }

    protected function height(): Attribute
    {
        return Attribute::make(get: fn (): ?int => isset($this->video_metadata['height']) ? (int) $this->video_metadata['height'] : null);
    }

    protected function durationFormatted(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            $duration = $this->duration;
            if (! $duration) {
                return null;
            }

            $minutes = floor($duration / 60);
            $seconds = floor($duration % 60);

            return sprintf('%d:%02d', $minutes, $seconds);
        });
    }

    protected function fileSizeFormatted(): Attribute
    {
        return Attribute::make(get: function (): string {
            $bytes = $this->file_size;

            if ($bytes >= 1073741824) {
                return number_format($bytes / 1073741824, 2).' GB';
            } elseif ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2).' MB';
            } elseif ($bytes >= 1024) {
                return number_format($bytes / 1024, 2).' KB';
            }

            return $bytes.' bytes';
        });
    }

    // Raw storage paths, not signed URLs.
    public function getRawFilePath(): string
    {
        return $this->attributes['file_path'];
    }

    public function getRawThumbnailUrl(): ?string
    {
        return $this->attributes['thumbnail_url'] ?? null;
    }

    public function getRawMediumUrl(): ?string
    {
        return $this->attributes['medium_url'] ?? null;
    }

    public function getRawHlsUrl(): ?string
    {
        return $this->attributes['hls_url'] ?? null;
    }

    public function deleteWithFiles(): bool
    {
        try {
            $filesToDelete = array_filter([
                $this->getRawFilePath(),
                $this->getRawThumbnailUrl(),
                $this->getRawMediumUrl(),
            ]);

            // A video's HLS stream is a directory of segments
            if ($this->isVideo() && $this->getRawHlsUrl()) {
                Storage::disk('seaweedfs')->deleteDirectory(dirname($this->getRawHlsUrl()));
            }

            if (! empty($filesToDelete)) {
                Storage::disk('seaweedfs')->delete($filesToDelete);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete media files: '.$e->getMessage());
        }

        return $this->delete();
    }

    #[\Override]
    protected static function booted(): void
    {
        static::forceDeleted(function (Media $media): void {
            try {
                $filesToDelete = array_filter([
                    $media->attributes['file_path'] ?? null,
                    $media->attributes['thumbnail_url'] ?? null,
                    $media->attributes['medium_url'] ?? null,
                ]);

                if ($media->type === self::TYPE_VIDEO && ! empty($media->attributes['hls_url'])) {
                    Storage::disk('seaweedfs')->deleteDirectory(dirname($media->attributes['hls_url']));
                }

                if (! empty($filesToDelete)) {
                    Storage::disk('seaweedfs')->delete($filesToDelete);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete media files: '.$e->getMessage());
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
            'type' => $this->type,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'is_ready' => $this->is_ready,
            'thumbnail_url' => $this->thumbnail_url,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];

        if ($this->isPhoto()) {
            $data['medium_url'] = $this->medium_url;
            $data['exif_data'] = $this->exif_data;
            $data['latitude'] = $this->latitude;
            $data['longitude'] = $this->longitude;
            $data['has_location'] = $this->has_location;
            $data['camera_make'] = $this->camera_make;
            $data['camera_model'] = $this->camera_model;
            $data['exif_date'] = $this->exif_date?->toIso8601String();
        }

        if ($this->isVideo()) {
            $data['hls_url'] = $this->hls_url;
            $data['video_metadata'] = $this->video_metadata;
            $data['duration'] = $this->duration;
            $data['duration_formatted'] = $this->duration_formatted;
            $data['width'] = $this->width;
            $data['height'] = $this->height;
        }

        if ($this->isFailed()) {
            $data['processing_error'] = $this->processing_error;
        }

        return $data;
    }
}
