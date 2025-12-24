<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\PredefinedMilestone;
use App\Models\UserMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserMilestoneController extends Controller
{
    /**
     * List user milestones with optional filters.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = UserMilestone::with(['predefinedMilestone', 'iconImage', 'moment', 'kid'])
            ->forUser($user->id)
            ->orderByDate('desc');

        // Filter by kid
        if ($request->has('kid_id')) {
            $query->forKid($request->input('kid_id'));
        }

        // Filter by type
        if ($request->input('type') === 'custom') {
            $query->custom();
        } elseif ($request->input('type') === 'predefined') {
            $query->predefined();
        }

        $milestones = $query->paginate($request->input('per_page', 20));

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kid_id' => 'required|uuid|exists:kids,id',
            'predefined_milestone_id' => 'nullable|string|exists:predefined_milestones,id',
            'custom_title' => 'required_without:predefined_milestone_id|nullable|string|max:255',
            'custom_description' => 'nullable|string|max:5000',
            'milestone_date' => 'required|date',
            'notes' => 'nullable|string|max:5000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
            'moment_id' => 'nullable|uuid|exists:moments,id',
        ]);

        $user = $request->user();
        $kid = Kid::findOrFail($validated['kid_id']);
        
        // Authorize access to kid
        $this->authorize('view', $kid);

        // Validate predefined milestone exists if provided
        if (!empty($validated['predefined_milestone_id'])) {
            $predefined = PredefinedMilestone::find($validated['predefined_milestone_id']);
            if (!$predefined) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid predefined milestone',
                ], 422);
            }
        }

        // Verify icon image belongs to user if provided
        if (!empty($validated['icon_image_id'])) {
            $iconImage = Media::find($validated['icon_image_id']);
            if (!$iconImage || $iconImage->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid icon image',
                ], 422);
            }
        }

        // Verify moment belongs to kid if provided
        if (!empty($validated['moment_id'])) {
            $moment = Moment::find($validated['moment_id']);
            if (!$moment || $moment->kid_id !== $kid->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid moment',
                ], 422);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $kid, $user) {
                // Create a new moment with milestone type if no moment_id provided
                $momentId = $validated['moment_id'] ?? null;
                
                if (!$momentId) {
                    $moment = Moment::create([
                        'id' => Str::uuid()->toString(),
                        'kid_id' => $kid->id,
                        'moment_type' => 'milestone',
                        'title' => $validated['custom_title'] ?? null,
                    ]);
                    $momentId = $moment->id;
                }

                // Create user milestone
                $milestone = UserMilestone::create([
                    'id' => Str::uuid()->toString(),
                    'kid_id' => $kid->id,
                    'user_id' => $user->id,
                    'predefined_milestone_id' => $validated['predefined_milestone_id'] ?? null,
                    'custom_title' => $validated['custom_title'] ?? null,
                    'custom_description' => $validated['custom_description'] ?? null,
                    'milestone_date' => $validated['milestone_date'],
                    'notes' => $validated['notes'] ?? null,
                    'icon_image_id' => $validated['icon_image_id'] ?? null,
                    'moment_id' => $momentId,
                ]);

                $milestone->load(['predefinedMilestone', 'iconImage', 'moment']);

                Log::info('User milestone created', [
                    'milestone_id' => $milestone->id,
                    'kid_id' => $kid->id,
                    'is_custom' => $milestone->isCustom(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Milestone created successfully',
                    'data' => $milestone->toArray(),
                ], 201);
            });
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
    public function show(UserMilestone $userMilestone)
    {
        $this->authorize('view', $userMilestone->kid);

        $userMilestone->load(['predefinedMilestone', 'iconImage', 'moment']);

        return response()->json([
            'success' => true,
            'data' => $userMilestone->toArray(),
        ]);
    }

    /**
     * Update a user milestone.
     */
    public function update(Request $request, UserMilestone $userMilestone)
    {
        $this->authorize('view', $userMilestone->kid);

        $validated = $request->validate([
            'custom_title' => 'nullable|string|max:255',
            'custom_description' => 'nullable|string|max:5000',
            'milestone_date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:5000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ]);

        $user = $request->user();

        // Verify icon image belongs to user if provided
        if (!empty($validated['icon_image_id'])) {
            $iconImage = Media::find($validated['icon_image_id']);
            if (!$iconImage || $iconImage->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid icon image',
                ], 422);
            }
        }

        try {
            $userMilestone->update($validated);
            $userMilestone->load(['predefinedMilestone', 'iconImage', 'moment']);

            return response()->json([
                'success' => true,
                'message' => 'Milestone updated successfully',
                'data' => $userMilestone->toArray(),
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
    public function destroy(UserMilestone $userMilestone)
    {
        $this->authorize('delete', $userMilestone->kid);

        try {
            $userMilestone->delete();

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
