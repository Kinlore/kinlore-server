<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    /**
     * List all predefined milestones.
     * Returns milestones grouped by stage for easy UI rendering.
     */
    public function index(Request $request)
    {
        // Check if flat list is requested
        if ($request->boolean('flat')) {
            $milestones = Milestone::getAllCached();
            return response()->json([
                'success' => true,
                'data' => $milestones->map(fn($m) => $m->toArray()),
            ]);
        }

        // Default: return grouped by stage
        $grouped = Milestone::getGroupedByStage();

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Get a specific milestone by ID.
     */
    public function show(Request $request, string $identifier)
    {
        $milestone = Milestone::where('id', $identifier)
            ->with('iconImage')
            ->first();

        if (!$milestone) {
            return response()->json([
                'success' => false,
                'message' => 'Milestone not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $milestone->toArray(),
        ]);
    }

    /**
     * Get milestones for a specific stage.
     */
    public function byStage(Request $request, string $stageKey)
    {
        $milestones = Milestone::byStage($stageKey)
            ->ordered()
            ->with('iconImage')
            ->get();

        if ($milestones->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Stage not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stage_key' => $stageKey,
                'stage_label' => $milestones->first()->stage_label,
                'milestones' => $milestones->map(fn($m) => $m->toArray()),
            ],
        ]);
    }
}
