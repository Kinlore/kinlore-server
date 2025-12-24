<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoryController extends Controller
{
    /**
     * List stories with optional filters.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Story::with(['iconImage', 'moment', 'kid'])
            ->whereHas('kid', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc');

        // Filter by kid
        if ($request->has('kid_id')) {
            $query->forKid($request->input('kid_id'));
        }

        $stories = $query->paginate($request->input('per_page', 20));

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
     * This also creates a linked moment with moment_type = 'story'.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kid_id' => 'required|uuid|exists:kids,id',
            'title' => 'required|string|max:255',
            'text' => 'required|string|max:50000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ]);

        $user = $request->user();
        $kid = Kid::findOrFail($validated['kid_id']);
        
        // Authorize access to kid
        $this->authorize('view', $kid);

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
            return DB::transaction(function () use ($validated, $kid, $user) {
                // Create moment first
                $moment = Moment::create([
                    'id' => Str::uuid()->toString(),
                    'kid_id' => $kid->id,
                    'moment_type' => 'story',
                    'title' => $validated['title'],
                    'story_text' => $validated['text'],
                ]);

                // Create story linked to moment
                $story = Story::create([
                    'id' => Str::uuid()->toString(),
                    'moment_id' => $moment->id,
                    'kid_id' => $kid->id,
                    'title' => $validated['title'],
                    'text' => $validated['text'],
                    'icon_image_id' => $validated['icon_image_id'] ?? null,
                ]);

                $story->load(['iconImage', 'moment']);

                Log::info('Story created', [
                    'story_id' => $story->id,
                    'moment_id' => $moment->id,
                    'kid_id' => $kid->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Story created successfully',
                    'data' => $story->toArray(),
                ], 201);
            });
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
    public function show(Story $story)
    {
        $this->authorize('view', $story->kid);

        $story->load(['iconImage', 'moment']);

        return response()->json([
            'success' => true,
            'data' => $story->toArray(),
        ]);
    }

    /**
     * Update a story.
     */
    public function update(Request $request, Story $story)
    {
        $this->authorize('view', $story->kid);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'text' => 'sometimes|required|string|max:50000',
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
            return DB::transaction(function () use ($validated, $story) {
                // Update story
                $story->update($validated);

                // Update linked moment
                if ($story->moment) {
                    $momentUpdates = [];
                    if (isset($validated['title'])) {
                        $momentUpdates['title'] = $validated['title'];
                    }
                    if (isset($validated['text'])) {
                        $momentUpdates['story_text'] = $validated['text'];
                    }
                    if (!empty($momentUpdates)) {
                        $story->moment->update($momentUpdates);
                    }
                }

                $story->load(['iconImage', 'moment']);

                return response()->json([
                    'success' => true,
                    'message' => 'Story updated successfully',
                    'data' => $story->toArray(),
                ]);
            });
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
    public function destroy(Story $story)
    {
        $this->authorize('delete', $story->kid);

        try {
            return DB::transaction(function () use ($story) {
                // Delete linked moment (cascade will handle story via foreign key)
                if ($story->moment) {
                    $story->moment->delete();
                }
                
                $story->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Story deleted successfully',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Story deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete story',
            ], 500);
        }
    }
}
