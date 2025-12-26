<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Story;
use App\Services\StoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoryController extends Controller
{
    public function __construct(
        private StoryService $storyService
    ) {}

    /**
     * List stories with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kid_id' => 'nullable|uuid|exists:kids,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $stories = $this->storyService->getStoriesForUser($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $stories->items(),
            'meta' => [
                'current_page' => $stories->currentPage(),
                'last_page' => $stories->lastPage(),
                'per_page' => $stories->perPage(),
                'total' => $stories->total(),
            ],
        ]);
    }

    /**
     * Create a new story.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kid_id' => 'required|uuid|exists:kids,id',
            'title' => 'required|string|max:255',
            'text' => 'required|string|max:50000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ]);

        $kid = Kid::findOrFail($validated['kid_id']);
        $this->authorize('view', $kid);

        // Verify icon image belongs to user if provided
        if (!$this->storyService->validateIconImage($request->user(), $validated['icon_image_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid icon image',
            ], 422);
        }

        try {
            $story = $this->storyService->createStory($kid, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Story created successfully',
                'data' => $story->toArray(),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Story creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create story',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get single story.
     */
    public function show(Story $story): JsonResponse
    {
        $this->authorize('view', $story->kid);

        $story = $this->storyService->getStory($story);

        return response()->json([
            'success' => true,
            'data' => $story->toArray(),
        ]);
    }

    /**
     * Update a story.
     */
    public function update(Request $request, Story $story): JsonResponse
    {
        $this->authorize('view', $story->kid);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'text' => 'sometimes|required|string|max:50000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ]);

        // Verify icon image belongs to user if provided
        if (!$this->storyService->validateIconImage($request->user(), $validated['icon_image_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid icon image',
            ], 422);
        }

        try {
            $story = $this->storyService->updateStory($story, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Story updated successfully',
                'data' => $story->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Story update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update story',
            ], 500);
        }
    }

    /**
     * Delete a story.
     */
    public function destroy(Story $story): JsonResponse
    {
        $this->authorize('delete', $story->kid);

        try {
            $this->storyService->deleteStory($story);

            return response()->json([
                'success' => true,
                'message' => 'Story deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Story deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete story',
            ], 500);
        }
    }
}
