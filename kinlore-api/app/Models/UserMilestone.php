<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserMilestone extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_milestones';

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
        'predefined_milestone_id',
        'custom_title',
        'custom_description',
        'milestone_date',
        'notes',
        'icon_image_id',
        'moment_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'milestone_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    public function predefinedMilestone()
    {
        return $this->belongsTo(PredefinedMilestone::class, 'predefined_milestone_id');
    }

    public function iconImage()
    {
        return $this->belongsTo(Media::class, 'icon_image_id');
    }

    public function moment()
    {
        return $this->belongsTo(Moment::class);
    }

    /**
     * Determine if this is a custom milestone
     */
    public function isCustom(): bool
    {
        return is_null($this->predefined_milestone_id);
    }

    /**
     * Get the title (from predefined or custom)
     */
    public function getTitleAttribute(): string
    {
        if ($this->predefinedMilestone) {
            return $this->predefinedMilestone->title;
        }
        
        return $this->custom_title ?? 'Untitled Milestone';
    }

    /**
     * Get the description (from predefined or custom)
     */
    public function getDescriptionAttribute(): ?string
    {
        if ($this->predefinedMilestone) {
            return $this->predefinedMilestone->description;
        }
        
        return $this->custom_description;
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

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCustom($query)
    {
        return $query->whereNull('predefined_milestone_id');
    }

    public function scopePredefined($query)
    {
        return $query->whereNotNull('predefined_milestone_id');
    }

    public function scopeOrderByDate($query, $direction = 'desc')
    {
        return $query->orderBy('milestone_date', $direction);
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
            'is_custom' => $this->isCustom(),
            'title' => $this->title,
            'description' => $this->description,
            'milestone_date' => $this->milestone_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'icon_url' => $this->icon_url,
            'moment_id' => $this->moment_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];

        // Include predefined milestone details if loaded
        if ($this->relationLoaded('predefinedMilestone') && $this->predefinedMilestone) {
            $data['predefined_milestone'] = $this->predefinedMilestone->toArray();
        }

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
