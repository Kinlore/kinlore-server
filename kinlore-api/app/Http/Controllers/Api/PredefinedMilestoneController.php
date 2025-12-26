<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredefinedMilestoneController extends Controller
{
    public function __construct(
        private MilestoneService $milestoneService
    ) {}

    /**
     * List all predefined milestones.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['group_by', 'category', 'stage_key']);
        
        $milestones = $this->milestoneService->getPredefinedMilestones($filters);

        return response()->json([
            'success' => true,
            'data' => $milestones,
        ]);
    }

    /**
     * Get single predefined milestone.
     */
    public function show(string $id): JsonResponse
    {
        $milestone = $this->milestoneService->getPredefinedMilestone($id);

        return response()->json([
            'success' => true,
            'data' => $milestone->toArray(),
        ]);
    }
}
