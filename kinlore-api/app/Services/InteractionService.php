<?php

namespace App\Services;

use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Service for handling moment interactions (likes, comments).
 */
class InteractionService
{
    /**
     * Like a moment.
     * Returns the interaction if newly created, null if already liked.
     */
    public function like(User $user, Moment $moment): ?MomentInteraction
    {
        // Check if already liked
        $existingLike = $moment->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            return null;
        }

        $interaction = new MomentInteraction([
            'user_id' => $user->id,
            'moment_id' => $moment->id,
            'type' => 'like',
        ]);
        
        $interaction->save();

        return $interaction;
    }

    /**
     * Unlike a moment.
     * Returns true if unliked, false if not previously liked.
     */
    public function unlike(User $user, Moment $moment): bool
    {
        return (bool) $moment->likes()->where('user_id', $user->id)->delete();
    }

    /**
     * Check if a moment is liked by a user.
     */
    public function isLikedBy(User $user, Moment $moment): bool
    {
        return $moment->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Add a comment to a moment.
     */
    public function comment(User $user, Moment $moment, string $content): MomentInteraction
    {
        $interaction = new MomentInteraction([
            'user_id' => $user->id,
            'moment_id' => $moment->id,
            'type' => 'comment',
            'content' => $content,
        ]);

        $interaction->save();
        $interaction->load('user');

        return $interaction;
    }

    /**
     * Delete a comment.
     * @throws \InvalidArgumentException if not a comment
     * @throws \RuntimeException if user doesn't own the comment
     */
    public function deleteComment(User $user, MomentInteraction $interaction): void
    {
        if ($interaction->type !== 'comment') {
            throw new \InvalidArgumentException('Invalid interaction type');
        }

        if ($interaction->user_id !== $user->id) {
            throw new \RuntimeException('Unauthorized');
        }

        $interaction->delete();
    }

    /**
     * Get comments for a moment.
     */
    public function getComments(Moment $moment): Collection
    {
        return $moment->comments()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get likes count for a moment.
     */
    public function getLikesCount(Moment $moment): int
    {
        return $moment->likes()->count();
    }

    /**
     * Get comments count for a moment.
     */
    public function getCommentsCount(Moment $moment): int
    {
        return $moment->comments()->count();
    }
}
