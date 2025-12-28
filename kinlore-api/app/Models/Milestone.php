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
        'image_path',
        'category',
        'stage_key',
        'stage_label',
        'age_min_months',
        'age_max_months',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'age_min_months' => 'integer',
        'age_max_months' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    public function userMilestones()
    {
        return $this->hasMany(UserMilestone::class, 'milestone_id');
    }

    /**
     * Scopes
     */
    
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByStage($query, string $stageKey)
    {
        return $query->where('stage_key', $stageKey);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('stage_key')->orderBy('sort_order');
    }

    /**
     * Get all milestones (cached for performance)
     */
    public static function getAllCached()
    {
        return Cache::remember('milestones.all', 60 * 60 * 24, function () {
            return static::ordered()->get();
        });
    }

    /**
     * Get milestones grouped by stage
     */
    public static function getGroupedByStage()
    {
        return Cache::remember('milestones.grouped', 60 * 60 * 24, function () {
            $milestones = static::ordered()->get();
            
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
     * Get milestones grouped by category
     */
    public static function getGroupedByCategory()
    {
        return Cache::remember('milestones.by_category', 60 * 60 * 24, function () {
            $milestones = static::ordered()->get();
            
            return $milestones->groupBy('category')->map(function ($categoryMilestones, $category) {
                return [
                    'category' => $category,
                    'milestones' => $categoryMilestones->values(),
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
            Cache::forget('milestones.by_category');
        });

        static::deleted(function () {
            Cache::forget('milestones.all');
            Cache::forget('milestones.grouped');
            Cache::forget('milestones.by_category');
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
            'image_path' => $this->image_path,
            'category' => $this->category,
            'stage_key' => $this->stage_key,
            'stage_label' => $this->stage_label,
            'age_min_months' => $this->age_min_months,
            'age_max_months' => $this->age_max_months,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
