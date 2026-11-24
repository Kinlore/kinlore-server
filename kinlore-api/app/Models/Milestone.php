<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable([
    'id',
    'title',
    'description',
    'stage_key',
    'stage_label',
    'age_min_months',
    'age_max_months',
    'sort_order',
])]
class Milestone extends Model
{
    use HasFactory;

    // ids are string keys from database/seeders/milestones.json
    public $incrementing = false;

    protected $keyType = 'string';

    #[\Override]
    protected function casts(): array
    {
        return [
            'age_min_months' => 'integer',
            'age_max_months' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function byStage(Builder $query, string $stageKey): void
    {
        $query->where('stage_key', $stageKey);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        // sort_order runs across all stages in milestones.json order, so it also orders the stages
        $query->orderBy('sort_order');
    }

    /** No cache: the list is about a hundred rows and rarely read. */
    public static function getGroupedByStage(): Collection
    {
        return static::query()->ordered()->get()->groupBy('stage_key')->map(fn (Collection $stageMilestones, string $stageKey): array => [
            'stage_key' => $stageKey,
            'stage_label' => $stageMilestones->first()->stage_label,
            'milestones' => $stageMilestones->values(),
        ])->values();
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
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
