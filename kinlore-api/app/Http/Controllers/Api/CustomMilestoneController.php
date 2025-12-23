<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomMilestoneController extends Controller
{
    /**
     * List user's custom milestones.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $milestones = CustomMilestone::forUser($user->id)
            ->with('iconImage')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $milestones->map(fn($m) => $m->toArray()),
        ]);
    }

    /**
     * Create a new custom milestone.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon_image_id' => 'nullable|uuid|exists:images,id',
        ]);

        $user = $request->user();

        $milestone = CustomMilestone::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon_image_id' => $validated['icon_image_id'] ?? null,
        ]);

        $milestone->load('iconImage');

        Log::info('Custom milestone created', [
            'milestone_id' => $milestone->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom milestone created successfully',
            'data' => $milestone->toArray(),
        ], 201);
    }

    /**
     * Get a specific custom milestone.
     */
    public function show(CustomMilestone $customMilestone)
    {
        $this->authorize('view', $customMilestone);

        $customMilestone->load('iconImage');

        return response()->json([
            'success' => true,
            'data' => $customMilestone->toArray(),
        ]);
    }

    /**
     * Update a custom milestone.
     */
    public function update(Request $request, CustomMilestone $customMilestone)
    {
        $this->authorize('update', $customMilestone);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon_image_id' => 'nullable|uuid|exists:images,id',
        ]);

        $customMilestone->update($validated);
        $customMilestone->load('iconImage');

        return response()->json([
            'success' => true,
            'message' => 'Custom milestone updated successfully',
            'data' => $customMilestone->toArray(),
        ]);
    }

    /**
     * Delete a custom milestone.
     */
    public function destroy(CustomMilestone $customMilestone)
    {
        $this->authorize('delete', $customMilestone);

        $customMilestone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom milestone deleted successfully',
        ]);
    }
}
