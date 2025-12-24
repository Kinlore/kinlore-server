<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Status constants
     */
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Type constants
     */
    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';

    /**
     * The table associated with the model.
     */
    protected $table = 'media';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
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
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'exif_data' => 'array',
        'video_metadata' => 'array',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'file_size_formatted',
        'is_ready',
        'has_location',
    ];

    /**
     * Relationships
     */
    
    public function kid()
    {
        return $this->belongsTo(Kid::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moments()
    {
        return $this->hasMany(Moment::class);
    }

    /**
     * Type Checking Helpers
     */
    
    public function isPhoto(): bool
    {
        return $this->type === self::TYPE_PHOTO;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    /**
     * Status Helpers
     */
    
    public function isUploading(): bool
    {
        return $this->status === self::STATUS_UPLOADING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getIsReadyAttribute(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Status Transition Methods
     */
    
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processing_started_at' => now(),
        ]);
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

    /**
     * EXIF Data Accessors (for photos)
     */
    
    public function getLatitudeAttribute(): ?float
    {
        return $this->exif_data['latitude'] ?? null;
    }

    public function getLongitudeAttribute(): ?float
    {
        return $this->exif_data['longitude'] ?? null;
    }

    public function getOrientationAttribute(): ?int
    {
        return $this->exif_data['orientation'] ?? null;
    }

    public function getCameraMakeAttribute(): ?string
    {
        return $this->exif_data['camera_make'] ?? null;
    }

    public function getCameraModelAttribute(): ?string
    {
        return $this->exif_data['camera_model'] ?? null;
    }

    public function getExifDateAttribute()
    {
        $date = $this->exif_data['exif_date'] ?? null;
        return $date ? \Carbon\Carbon::parse($date) : null;
    }

    public function getHasLocationAttribute(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Video Metadata Accessors (for videos)
     */
    
    public function getDurationAttribute(): ?float
    {
        return $this->video_metadata['duration'] ?? null;
    }

    public function getFpsAttribute(): ?float
    {
        return $this->video_metadata['fps'] ?? null;
    }

    public function getWidthAttribute(): ?int
    {
        return $this->video_metadata['width'] ?? null;
    }

    public function getHeightAttribute(): ?int
    {
        return $this->video_metadata['height'] ?? null;
    }

    public function getCodecAttribute(): ?string
    {
        return $this->video_metadata['codec'] ?? null;
    }

    public function getDurationFormattedAttribute(): ?string
    {
        $duration = $this->duration;
        if (!$duration) {
            return null;
        }

        $minutes = floor($duration / 60);
        $seconds = floor($duration % 60);
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Computed Attributes
     */
    
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    public function getDisplayDateAttribute()
    {
        // Prefer EXIF date for photos, otherwise created_at
        return $this->exif_date ?? $this->created_at;
    }

    /**
     * URL Generation (raw paths, not signed)
     */
    
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

    /**
     * Signed URL generation for secure access
     */
    protected function generateSignedUrl(?string $path, int $expiration = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            return Storage::disk('seaweedfs')->temporaryUrl(
                $path,
                now()->addMinutes($expiration)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to generate signed URL for media: ' . $e->getMessage());
            return Storage::disk('seaweedfs')->url($path);
        }
    }

    /**
     * Scopes
     */
    
    public function scopeForKid($query, $kidId)
    {
        return $query->where('kid_id', $kidId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePhotos($query)
    {
        return $query->where('type', self::TYPE_PHOTO);
    }

    public function scopeVideos($query)
    {
        return $query->where('type', self::TYPE_VIDEO);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_UPLOADING, self::STATUS_PROCESSING]);
    }

    public function scopeByDevice($query, $deviceAssetId)
    {
        return $query->where('device_asset_id', $deviceAssetId);
    }

    /**
     * Delete media and associated files
     */
    public function deleteWithFiles(): bool
    {
        try {
            $filesToDelete = array_filter([
                $this->getRawFilePath(),
                $this->getRawThumbnailUrl(),
                $this->getRawMediumUrl(),
            ]);

            // For videos, also delete HLS files
            if ($this->isVideo() && $this->getRawHlsUrl()) {
                // Delete HLS directory
                $hlsDir = dirname($this->getRawHlsUrl());
                Storage::disk('seaweedfs')->deleteDirectory($hlsDir);
            }

            if (!empty($filesToDelete)) {
                Storage::disk('seaweedfs')->delete($filesToDelete);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to delete media files: ' . $e->getMessage());
        }

        return $this->delete();
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Delete files when media is force deleted
        static::forceDeleted(function ($media) {
            try {
                $filesToDelete = array_filter([
                    $media->attributes['file_path'] ?? null,
                    $media->attributes['thumbnail_url'] ?? null,
                    $media->attributes['medium_url'] ?? null,
                ]);

                if ($media->type === self::TYPE_VIDEO && !empty($media->attributes['hls_url'])) {
                    $hlsDir = dirname($media->attributes['hls_url']);
                    Storage::disk('seaweedfs')->deleteDirectory($hlsDir);
                }

                if (!empty($filesToDelete)) {
                    Storage::disk('seaweedfs')->delete($filesToDelete);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to delete media files: ' . $e->getMessage());
            }
        });
    }

    /**
     * API Resource formatting
     */
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

        // Photo-specific fields
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

        // Video-specific fields
        if ($this->isVideo()) {
            $data['hls_url'] = $this->hls_url;
            $data['video_metadata'] = $this->video_metadata;
            $data['duration'] = $this->duration;
            $data['duration_formatted'] = $this->duration_formatted;
            $data['width'] = $this->width;
            $data['height'] = $this->height;
        }

        // Error info if failed
        if ($this->isFailed()) {
            $data['processing_error'] = $this->processing_error;
        }

        return $data;
    }
}
