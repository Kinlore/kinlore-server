<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PredefinedMilestone;
use Illuminate\Http\Request;

class PredefinedMilestoneController extends Controller
{
    /**
     * List all predefined milestones.
     */
    public function index(Request $request)
    {
        // Optional grouping
        $groupBy = $request->input('group_by');
        
        if ($groupBy === 'stage') {
            return response()->json([
                'success' => true,
                'data' => PredefinedMilestone::getGroupedByStage(),
            ]);
        }
        
        if ($groupBy === 'category') {
            return response()->json([
                'success' => true,
                'data' => PredefinedMilestone::getGroupedByCategory(),
            ]);
        }

        // Filter by category if provided
        $query = PredefinedMilestone::ordered();
        
        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->has('stage_key')) {
            $query->byStage($request->input('stage_key'));
        }

        $milestones = $query->get();

        return response()->json([
            'success' => true,
            'data' => $milestones,
        ]);
    }

    /**
     * Get single predefined milestone.
     */
    public function show(string $id)
    {
        $milestone = PredefinedMilestone::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $milestone->toArray(),
        ]);
    }
}
