<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PredefinedMilestone extends Model
{
    use HasFactory;

    /**
     * Category constants
     */
    public const CATEGORY_PHYSICAL = 'physical';
    public const CATEGORY_COGNITIVE = 'cognitive';
    public const CATEGORY_SOCIAL = 'social';
    public const CATEGORY_LANGUAGE = 'language';
    public const CATEGORY_EMOTIONAL = 'emotional';
    public const CATEGORY_BEHAVIORAL = 'behavioral';

    /**
     * The table associated with the model.
     */
    protected $table = 'predefined_milestones';

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
        'typical_age_months',
        'stage_key',
        'stage_label',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'typical_age_months' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    public function userMilestones()
    {
        return $this->hasMany(UserMilestone::class, 'predefined_milestone_id');
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
        return Cache::remember('predefined_milestones.all', 60 * 60 * 24, function () {
            return static::ordered()->get();
        });
    }

    /**
     * Get milestones grouped by stage
     */
    public static function getGroupedByStage()
    {
        return Cache::remember('predefined_milestones.grouped', 60 * 60 * 24, function () {
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
        return Cache::remember('predefined_milestones.by_category', 60 * 60 * 24, function () {
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
            Cache::forget('predefined_milestones.all');
            Cache::forget('predefined_milestones.grouped');
            Cache::forget('predefined_milestones.by_category');
        });

        static::deleted(function () {
            Cache::forget('predefined_milestones.all');
            Cache::forget('predefined_milestones.grouped');
            Cache::forget('predefined_milestones.by_category');
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
            'typical_age_months' => $this->typical_age_months,
            'stage_key' => $this->stage_key,
            'stage_label' => $this->stage_label,
            'sort_order' => $this->sort_order,
        ];
    }
}
