<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Milestones grouped by stage. No cache: the list is about a hundred rows and rarely read.
     */
    public static function getGroupedByStage()
    {
        return static::ordered()->get()->groupBy('stage_key')->map(fn ($stageMilestones, $stageKey) => [
            'stage_key' => $stageKey,
            'stage_label' => $stageMilestones->first()->stage_label,
            'milestones' => $stageMilestones->values(),
        ])->values();
    }

    /**
     * Milestones grouped by category.
     */
    public static function getGroupedByCategory()
    {
        return static::ordered()->get()->groupBy('category')->map(fn ($categoryMilestones, $category) => [
            'category' => $category,
            'milestones' => $categoryMilestones->values(),
        ])->values();
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
