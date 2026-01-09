<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyUserMilestoneRequest;
use App\Http\Requests\Api\IndexUserMilestoneRequest;
use App\Http\Requests\Api\ShowUserMilestoneRequest;
use App\Http\Requests\Api\StoreUserMilestoneRequest;
use App\Http\Requests\Api\UpdateUserMilestoneRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Kid;
use App\Models\UserMilestone;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserMilestoneController extends Controller
{
    public function __construct(
        private MilestoneService $milestoneService
    ) {}

    /**
     * List user milestones with optional filters.
     */
    public function index(IndexUserMilestoneRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $milestones = $this->milestoneService->getUserMilestones($request->user(), $validated);

        return ApiResponse::success(
            $milestones->items(),
            meta: [
                'current_page' => $milestones->currentPage(),
                'last_page' => $milestones->lastPage(),
                'per_page' => $milestones->perPage(),
                'total' => $milestones->total(),
            ]
        );
    }

    /**
     * Create a new user milestone (predefined or custom).
     */
    public function store(StoreUserMilestoneRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kid = Kid::findOrFail($validated['kid_id']);

        try {
            $milestone = $this->milestoneService->createUserMilestone($request->user(), $kid, $validated);

            return ApiResponse::success($milestone->toArray(), 'Milestone created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Milestone creation failed: ' . $e->getMessage());

            return ApiResponse::error(
                'Failed to create milestone',
                500,
                [],
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error']
            );
        }
    }

    /**
     * Get single user milestone.
     */
    public function show(ShowUserMilestoneRequest $request, UserMilestone $userMilestone): JsonResponse
    {
        $milestone = $this->milestoneService->getUserMilestone($userMilestone);

        return ApiResponse::success($milestone->toArray());
    }

    /**
     * Update a user milestone.
     */
    public function update(UpdateUserMilestoneRequest $request, UserMilestone $userMilestone): JsonResponse
    {
        $validated = $request->validated();

        try {
            $milestone = $this->milestoneService->updateUserMilestone($userMilestone, $validated);

            return ApiResponse::success($milestone->toArray(), 'Milestone updated successfully');
        } catch (\Exception $e) {
            Log::error('Milestone update failed: ' . $e->getMessage());

            return ApiResponse::error('Failed to update milestone', 500);
        }
    }

    /**
     * Delete a user milestone.
     */
    public function destroy(DestroyUserMilestoneRequest $request, UserMilestone $userMilestone): JsonResponse
    {
        try {
            $this->milestoneService->deleteUserMilestone($userMilestone);

            return ApiResponse::success(message: 'Milestone deleted successfully');
        } catch (\Exception $e) {
            Log::error('Milestone deletion failed: ' . $e->getMessage());

            return ApiResponse::error('Failed to delete milestone', 500);
        }
    }
}
