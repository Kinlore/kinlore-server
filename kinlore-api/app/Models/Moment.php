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
        'title',
        'description',
        'photo_url',
        'thumbnail_url',
        'medium_url',
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
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'exif_date' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'orientation' => 'integer',
        'tags' => 'array',
        'synced_at' => 'integer',
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

    public function user()
    {
        return $this->hasOneThrough(User::class, Kid::class, 'id', 'id', 'kid_id', 'user_id');
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
        return [
            'id' => $this->id,
            'kid_id' => $this->kid_id,
            'title' => $this->title,
            'description' => $this->description,
            'photo_url' => $this->photo_url,
            'medium_url' => $this->medium_url,
            'thumbnail_url' => $this->thumbnail_url,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'mime_type' => $this->mime_type,
            'exif_date' => $this->exif_date?->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'has_location' => $this->has_location,
            'camera_make' => $this->camera_make,
            'camera_model' => $this->camera_model,
            'tags' => $this->tags,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
