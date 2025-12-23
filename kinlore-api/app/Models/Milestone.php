<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Milestone extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'milestones';

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
        'id',
        'title',
        'description',
        'stage_key',
        'stage_label',
        'sort_order',
        'icon_image_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    public function moments()
    {
        return $this->hasMany(Moment::class);
    }

    public function iconImage()
    {
        return $this->belongsTo(Image::class, 'icon_image_id');
    }

    /**
     * Get icon URL (from related image or null)
     */
    public function getIconUrlAttribute(): ?string
    {
        return $this->iconImage?->url;
    }

    /**
     * Scopes
     */
    
    public function scopeByStage($query, string $stageKey)
    {
        return $query->where('stage_key', $stageKey);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get all milestones (cached for performance)
     */
    public static function getAllCached()
    {
        return Cache::remember('milestones.all', 60 * 60 * 24, function () {
            return static::with('iconImage')->orderBy('stage_key')->orderBy('sort_order')->get();
        });
    }

    /**
     * Get milestones grouped by stage
     */
    public static function getGroupedByStage()
    {
        return Cache::remember('milestones.grouped', 60 * 60 * 24, function () {
            $milestones = static::orderBy('sort_order')->get();
            
            return $milestones->groupBy('stage_key')->map(function ($stageMilestones, $stageKey) {
                return [
                    'stage_key' => $stageKey,
                    'stage_label' => $stageMilestones->first()->stage_label,
                    'milestones' => $stageMilestones->values(),
                ];
            })->values();
        });
    }

    /**
     * Clear the cache when a milestone is updated
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('milestones.all');
            Cache::forget('milestones.grouped');
        });

        static::deleted(function () {
            Cache::forget('milestones.all');
            Cache::forget('milestones.grouped');
        });
    }

    /**
     * API Resource formatting
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'stage_key' => $this->stage_key,
            'stage_label' => $this->stage_label,
            'sort_order' => $this->sort_order,
            'icon_url' => $this->icon_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
