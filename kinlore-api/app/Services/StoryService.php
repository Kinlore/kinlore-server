<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\Story;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for handling story operations.
 */
class StoryService
{
    /**
     * Get stories for a user with optional filters.
     */
    public function getStoriesForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Story::with(['iconImage', 'moment', 'kid'])
            ->whereHas('kid', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('created_at', 'desc');

        if (!empty($filters['kid_id'])) {
            $query->forKid($filters['kid_id']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Create a new story with associated moment.
     */
    public function createStory(Kid $kid, array $data): Story
    {
        return DB::transaction(function () use ($kid, $data) {
            // Create moment first
            $moment = Moment::create([
                'id' => Str::uuid()->toString(),
                'kid_id' => $kid->id,
                'moment_type' => 'story',
                'title' => $data['title'],
                'story_text' => $data['text'],
            ]);

            // Create story linked to moment
            $story = Story::create([
                'id' => Str::uuid()->toString(),
                'moment_id' => $moment->id,
                'kid_id' => $kid->id,
                'title' => $data['title'],
                'text' => $data['text'],
                'icon_image_id' => $data['icon_image_id'] ?? null,
            ]);

            $story->load(['iconImage', 'moment']);

            Log::info('Story created', [
                'story_id' => $story->id,
                'moment_id' => $moment->id,
                'kid_id' => $kid->id,
            ]);

            return $story;
        });
    }

    /**
     * Get a single story.
     */
    public function getStory(Story $story): Story
    {
        $story->load(['iconImage', 'moment']);
        
        return $story;
    }

    /**
     * Update a story and its linked moment.
     */
    public function updateStory(Story $story, array $data): Story
    {
        return DB::transaction(function () use ($story, $data) {
            // Update story
            $story->update($data);

            // Update linked moment
            if ($story->moment) {
                $momentUpdates = [];
                
                if (isset($data['title'])) {
                    $momentUpdates['title'] = $data['title'];
                }
                
                if (isset($data['text'])) {
                    $momentUpdates['story_text'] = $data['text'];
                }
                
                if (!empty($momentUpdates)) {
                    $story->moment->update($momentUpdates);
                }
            }

            $story->load(['iconImage', 'moment']);

            return $story;
        });
    }

    /**
     * Delete a story and its linked moment.
     */
    public function deleteStory(Story $story): void
    {
        DB::transaction(function () use ($story) {
            // Delete linked moment first
            if ($story->moment) {
                $story->moment->delete();
            }
            
            $story->delete();
        });
    }

    /**
     * Validate that an icon image belongs to the user.
     */
    public function validateIconImage(User $user, ?string $iconImageId): bool
    {
        if (empty($iconImageId)) {
            return true;
        }

        $iconImage = Media::find($iconImageId);
        
        return $iconImage && $iconImage->user_id === $user->id;
    }
}
