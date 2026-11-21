<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Moment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'moments';

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
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
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
        'moment_type' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'has_location',
        'file_size_formatted',
    ];

    /**
     * Relationships
     */
    
    public function kid()
    {
        return $this->belongsTo(Kid::class);
    }

    /** The uploader (moments.user_id). */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function interactions()
    {
        return $this->hasMany(MomentInteraction::class);
    }

    public function likes()
    {
        return $this->interactions()->where('type', 'like');
    }

    public function comments()
    {
        return $this->interactions()->where('type', 'comment');
    }

    public function isLikedBy(User $user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    public function customMilestone()
    {
        return $this->belongsTo(CustomMilestone::class);
    }

    /**
     * Check if moment is a video type
     */
    public function isVideo(): bool
    {
        return $this->moment_type === 'video';
    }

    /**
     * Get the milestone (either system or custom)
     */
    public function getAssociatedMilestone()
    {
        return $this->milestone ?? $this->customMilestone;
    }

    /**
     * Computed Attributes
     */
    
    public function getHasLocationAttribute()
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    public function getFileSizeFormattedAttribute()
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
        // Prefer EXIF date over created_at
        return $this->exif_date ?? $this->created_at;
    }

    /**
     * URL Generation (Signed URLs for security)
     */
    
    public function getPhotoUrlAttribute($value)
    {
        return $this->generateSignedUrl($value);
    }

    public function getMediumUrlAttribute($value)
    {
        return $this->generateSignedUrl($value);
    }

    public function getThumbnailUrlAttribute($value)
    {
        return $this->generateSignedUrl($value);
    }

    public function getHlsUrlAttribute($value)
    {
        return $this->generateSignedUrl($value);
    }

    protected function generateSignedUrl($path, $expiration = 60)
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
            \Log::error('Failed to generate signed URL: ' . $e->getMessage());
            return Storage::disk('seaweedfs')->url($path);
        }
    }

    /**
     * Helper Methods
     */
    
    public function deleteWithFiles()
    {
        // Delete files from MinIO
        try {
            Storage::disk('seaweedfs')->delete([
                $this->attributes['photo_url'],
                $this->attributes['medium_url'],
                $this->attributes['thumbnail_url'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to delete files from MinIO: ' . $e->getMessage());
        }

        // Delete database record
        return $this->delete();
    }

    public function addTag($tag)
    {
        $tags = $this->tags ?? [];
        
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            $this->save();
        }
    }

    public function removeTag($tag)
    {
        $tags = $this->tags ?? [];
        
        if (($key = array_search($tag, $tags)) !== false) {
            unset($tags[$key]);
            $this->tags = array_values($tags);
            $this->save();
        }
    }

    /**
     * Scopes
     */
    
    public function scopeForKid($query, $kidId)
    {
        return $query->where('kid_id', $kidId);
    }

    public function scopeWithLocation($query)
    {
        return $query->whereNotNull('latitude')
                     ->whereNotNull('longitude');
    }

    public function scopeOrderByDate($query, $direction = 'desc')
    {
        return $query->orderByRaw('COALESCE(exif_date, created_at) ' . $direction);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('exif_date', [$startDate, $endDate])
                     ->orWhereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Everything the moment payload needs, without N+1: uploader, counts and the caller's like.
     */
    public function scopeForPayload($query, ?User $viewer = null)
    {
        return $query->with(['user:id,name', 'milestone'])
            ->withCount(['likes', 'comments'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $viewer?->id)]);
    }

    public function scopeUpdatedSince($query, $timestamp)
    {
        return $query->where('updated_at', '>', Carbon::createFromTimestamp($timestamp / 1000));
    }

    public function scopeByDevice($query, $deviceAssetId)
    {
        return $query->where('device_asset_id', $deviceAssetId);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Update synced_at on update
        static::updated(function ($moment) {
            if (!$moment->isDirty('synced_at')) {
                $moment->synced_at = now()->timestamp * 1000;
                $moment->saveQuietly();
            }
        });

        // Delete files when moment is force deleted
        static::forceDeleted(function ($moment) {
            try {
                Storage::disk('seaweedfs')->delete([
                    $moment->attributes['photo_url'],
                    $moment->attributes['medium_url'],
                    $moment->attributes['thumbnail_url'],
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to delete moment files: ' . $e->getMessage());
            }
        });
    }

    /**
     * API Resource formatting
     */
    public function toArray()
    {
        $data = [
            'id' => $this->id,
            'kid_id' => $this->kid_id,
            'user_id' => $this->user_id,
            'uploader_name' => $this->user?->name,
            'video_id' => $this->video_id,
            'moment_type' => $this->moment_type ?? 'photo',
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
            'video_status' => $this->video_status,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'head_circumference_cm' => $this->head_circumference_cm,
            'likes_count' => $this->interactionStat('likes_count', fn () => $this->likes()->count()),
            'comments_count' => $this->interactionStat('comments_count', fn () => $this->comments()->count()),
            'is_liked' => (bool) $this->interactionStat('is_liked', fn () => ($viewer = auth()->user()) ? $this->isLikedBy($viewer) : false),
            'tags' => $this->tags,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];

        // Include video details if loaded and exists
        if ($this->relationLoaded('video') && $this->video) {
            $data['video'] = $this->video->toStatusArray();
        }

        // Include milestone details if loaded
        if ($this->relationLoaded('milestone') && $this->milestone) {
            $data['milestone'] = $this->milestone->toArray();
        }

        if ($this->relationLoaded('customMilestone') && $this->customMilestone) {
            $data['custom_milestone'] = $this->customMilestone->toArray();
        }

        return $data;
    }

    /** Value from scopeForPayload, or one query when the scope was not used. */
    private function interactionStat(string $key, \Closure $fallback): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $fallback();
    }
}
