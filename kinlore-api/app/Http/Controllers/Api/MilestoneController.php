<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function __construct(
        private readonly MilestoneService $milestoneService,
    ) {}

    /**
     * List all milestones.
     * Returns milestones grouped by stage for easy UI rendering by default.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['group_by', 'category', 'stage_key']);

        // Default to stage grouping if no group_by specified and not flat
        if (! $request->has('group_by') && ! $request->boolean('flat')) {
            $filters['group_by'] = 'stage';
        }

        $milestones = $this->milestoneService->getMilestones($filters);

        return ApiResponse::success($milestones);
    }

    public function show(string $id): JsonResponse
    {
        $milestone = $this->milestoneService->getMilestone($id);

        return ApiResponse::success($milestone->toArray());
    }
}
