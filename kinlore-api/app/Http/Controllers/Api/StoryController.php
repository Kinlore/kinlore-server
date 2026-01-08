<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyStoryRequest;
use App\Http\Requests\Api\IndexStoryRequest;
use App\Http\Requests\Api\ShowStoryRequest;
use App\Http\Requests\Api\StoreStoryRequest;
use App\Http\Requests\Api\UpdateStoryRequest;
use App\Models\Kid;
use App\Models\Story;
use App\Services\StoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StoryController extends Controller
{
    public function __construct(
        private StoryService $storyService
    ) {}

    /**
     * List stories with optional filters.
     */
    public function index(IndexStoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function store(StoreStoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kid = Kid::findOrFail($validated['kid_id']);

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
    public function show(ShowStoryRequest $request, Story $story): JsonResponse
    {
        $story = $this->storyService->getStory($story);

        return response()->json([
            'success' => true,
            'data' => $story->toArray(),
        ]);
    }

    /**
     * Update a story.
     */
    public function update(UpdateStoryRequest $request, Story $story): JsonResponse
    {
        $validated = $request->validated();

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
    public function destroy(DestroyStoryRequest $request, Story $story): JsonResponse
    {
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
