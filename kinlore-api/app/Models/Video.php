<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Video extends Model
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
     * The table associated with the model.
     */
    protected $table = 'videos';

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
        'user_id',
        'original_filename',
        'file_size',
        'mime_type',
        'duration',
        'status',
        'storage_path',
        'hls_url',
        'thumbnail_url',
        'error_message',
        'processing_started_at',
        'processing_completed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'float',
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
        'duration_formatted',
        'is_ready',
    ];

    /**
     * Relationships
     */
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moments()
    {
        return $this->hasMany(Moment::class);
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

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processing_started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $hlsUrl, string $thumbnailUrl, float $duration): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'hls_url' => $hlsUrl,
            'thumbnail_url' => $thumbnailUrl,
            'duration' => $duration,
            'processing_completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'processing_completed_at' => now(),
        ]);
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

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = floor($this->duration % 60);
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * URL Generation (Signed URLs for security)
     */
    
    public function getHlsUrlAttribute($value): ?string
    {
        return $this->generateSignedUrl($value);
    }

    public function getThumbnailUrlAttribute($value): ?string
    {
        return $this->generateSignedUrl($value);
    }

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
            \Log::error('Failed to generate signed URL for video: ' . $e->getMessage());
            return Storage::disk('seaweedfs')->url($path);
        }
    }

    /**
     * Get raw storage path without signed URL transformation
     */
    public function getRawStoragePath(): string
    {
        return $this->attributes['storage_path'];
    }

    public function getRawHlsUrl(): ?string
    {
        return $this->attributes['hls_url'] ?? null;
    }

    public function getRawThumbnailUrl(): ?string
    {
        return $this->attributes['thumbnail_url'] ?? null;
    }

    /**
     * Scopes
     */
    
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
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

    /**
     * Delete video and associated files
     */
    public function deleteWithFiles(): bool
    {
        try {
            $filesToDelete = array_filter([
                $this->getRawStoragePath(),
                $this->getRawHlsUrl(),
                $this->getRawThumbnailUrl(),
            ]);

            if (!empty($filesToDelete)) {
                Storage::disk('seaweedfs')->delete($filesToDelete);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to delete video files: ' . $e->getMessage());
        }

        return $this->delete();
    }

    /**
     * API Resource formatting
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'thumbnail_url' => $this->thumbnail_url,
            'hls_url' => $this->hls_url,
            'duration' => $this->duration,
            'duration_formatted' => $this->duration_formatted,
            'error_message' => $this->error_message,
            'is_ready' => $this->is_ready,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'mime_type' => $this->mime_type,
            'duration' => $this->duration,
            'duration_formatted' => $this->duration_formatted,
            'status' => $this->status,
            'hls_url' => $this->hls_url,
            'thumbnail_url' => $this->thumbnail_url,
            'is_ready' => $this->is_ready,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
