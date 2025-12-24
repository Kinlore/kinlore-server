<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'stories';

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
        'moment_id',
        'kid_id',
        'title',
        'text',
        'icon_image_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    public function moment()
    {
        return $this->belongsTo(Moment::class);
    }

    public function kid()
    {
        return $this->belongsTo(Kid::class);
    }

    public function iconImage()
    {
        return $this->belongsTo(Media::class, 'icon_image_id');
    }

    /**
     * Get icon URL (from related media)
     */
    public function getIconUrlAttribute(): ?string
    {
        return $this->iconImage?->thumbnail_url;
    }

    /**
     * Scopes
     */
    
    public function scopeForKid($query, $kidId)
    {
        return $query->where('kid_id', $kidId);
    }

    /**
     * API Resource formatting
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'moment_id' => $this->moment_id,
            'kid_id' => $this->kid_id,
            'title' => $this->title,
            'text' => $this->text,
            'icon_url' => $this->icon_url,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];

        // Include icon image details if loaded
        if ($this->relationLoaded('iconImage') && $this->iconImage) {
            $data['icon_image'] = $this->iconImage->toArray();
        }

        // Include moment if loaded
        if ($this->relationLoaded('moment') && $this->moment) {
            $data['moment'] = $this->moment->toArray();
        }

        return $data;
    }
}
