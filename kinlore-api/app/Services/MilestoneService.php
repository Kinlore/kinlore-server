<?php

namespace App\Services;

use App\Models\Milestone;
use Illuminate\Support\Collection;

/**
 * Service for handling milestone operations.
 */
class MilestoneService
{
    /**
     * Get milestones with optional grouping/filtering.
     */
    public function getMilestones(array $filters = []): Collection|array
    {
        $groupBy = $filters['group_by'] ?? null;
        
        if ($groupBy === 'stage') {
            return Milestone::getGroupedByStage();
        }
        
        if ($groupBy === 'category') {
            return Milestone::getGroupedByCategory();
        }

        $query = Milestone::ordered();
        
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (!empty($filters['stage_key'])) {
            $query->byStage($filters['stage_key']);
        }

        return $query->get();
    }

    /**
     * Get a single milestone.
     */
    public function getMilestone(string $id): Milestone
    {
        return Milestone::findOrFail($id);
    }
}
