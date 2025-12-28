<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\UserMilestone;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserMilestoneController extends Controller
{
    public function __construct(
        private MilestoneService $milestoneService
    ) {}

    /**
     * List user milestones with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kid_id' => 'nullable|uuid|exists:kids,id',
            'type' => 'nullable|in:custom,predefined',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $milestones = $this->milestoneService->getUserMilestones($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $milestones->items(),
            'meta' => [
                'current_page' => $milestones->currentPage(),
                'last_page' => $milestones->lastPage(),
                'per_page' => $milestones->perPage(),
                'total' => $milestones->total(),
            ],
        ]);
    }

    /**
     * Create a new user milestone (predefined or custom).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kid_id' => 'required|uuid|exists:kids,id',
            'milestone_id' => 'nullable|string|exists:milestones,id',
            'custom_title' => 'required_without:milestone_id|nullable|string|max:255',
            'custom_description' => 'nullable|string|max:5000',
            'milestone_date' => 'required|date',
            'notes' => 'nullable|string|max:5000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
            'moment_id' => 'nullable|uuid|exists:moments,id',
        ]);

        $kid = Kid::findOrFail($validated['kid_id']);
        $this->authorize('view', $kid);

        // Validate milestone exists if provided
        if (!$this->milestoneService->validateMilestone($validated['milestone_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid milestone',
            ], 422);
        }

        // Verify icon image belongs to user if provided
        if (!$this->milestoneService->validateIconImage($request->user(), $validated['icon_image_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid icon image',
            ], 422);
        }

        // Verify moment belongs to kid if provided
        if (!$this->milestoneService->validateMoment($kid, $validated['moment_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid moment',
            ], 422);
        }

        try {
            $milestone = $this->milestoneService->createUserMilestone($request->user(), $kid, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Milestone created successfully',
                'data' => $milestone->toArray(),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Milestone creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create milestone',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get single user milestone.
     */
    public function show(UserMilestone $userMilestone): JsonResponse
    {
        $this->authorize('view', $userMilestone->kid);

        $milestone = $this->milestoneService->getUserMilestone($userMilestone);

        return response()->json([
            'success' => true,
            'data' => $milestone->toArray(),
        ]);
    }

    /**
     * Update a user milestone.
     */
    public function update(Request $request, UserMilestone $userMilestone): JsonResponse
    {
        $this->authorize('view', $userMilestone->kid);

        $validated = $request->validate([
            'custom_title' => 'nullable|string|max:255',
            'custom_description' => 'nullable|string|max:5000',
            'milestone_date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:5000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ]);

        // Verify icon image belongs to user if provided
        if (!$this->milestoneService->validateIconImage($request->user(), $validated['icon_image_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid icon image',
            ], 422);
        }

        try {
            $milestone = $this->milestoneService->updateUserMilestone($userMilestone, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Milestone updated successfully',
                'data' => $milestone->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Milestone update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update milestone',
            ], 500);
        }
    }

    /**
     * Delete a user milestone.
     */
    public function destroy(UserMilestone $userMilestone): JsonResponse
    {
        $this->authorize('delete', $userMilestone->kid);

        try {
            $this->milestoneService->deleteUserMilestone($userMilestone);

            return response()->json([
                'success' => true,
                'message' => 'Milestone deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Milestone deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete milestone',
            ], 500);
        }
    }
}
