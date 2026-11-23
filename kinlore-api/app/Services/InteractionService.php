<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InteractionType;
use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Models\User;
use Illuminate\Support\Collection;

class InteractionService
{
    // Returns the interaction if newly created, null if already liked.
    public function like(User $user, Moment $moment): ?MomentInteraction
    {
        if ($moment->likes()->where('user_id', $user->id)->first()) {
            return null;
        }

        $interaction = new MomentInteraction([
            'user_id' => $user->id,
            'moment_id' => $moment->id,
            'type' => InteractionType::Like,
        ]);

        $interaction->save();

        return $interaction;
    }

    // Returns true if unliked, false if not previously liked.
    public function unlike(User $user, Moment $moment): bool
    {
        $deleted = (bool) $moment->likes()->where('user_id', $user->id)->delete();

        if ($deleted) {
            $moment->touch(); // a query delete fires no model events, so bump the moment for sync by hand
        }

        return $deleted;
    }

    public function isLikedBy(User $user, Moment $moment): bool
    {
        return $moment->likes()->where('user_id', $user->id)->exists();
    }

    public function comment(User $user, Moment $moment, string $content): MomentInteraction
    {
        $interaction = new MomentInteraction([
            'user_id' => $user->id,
            'moment_id' => $moment->id,
            'type' => InteractionType::Comment,
            'content' => $content,
        ]);

        $interaction->save();
        $interaction->load('user:id,name');

        return $interaction;
    }

    /**
     * @throws \InvalidArgumentException if not a comment
     * @throws \RuntimeException if the user is neither the author nor the admin
     */
    public function deleteComment(User $user, MomentInteraction $interaction): void
    {
        if ($interaction->type !== InteractionType::Comment) {
            throw new \InvalidArgumentException('Invalid interaction type');
        }

        if ((int) $interaction->user_id !== $user->id && ! $user->is_admin) {
            throw new \RuntimeException('You can only delete your own comments.');
        }

        $interaction->delete();
    }

    public function getComments(Moment $moment): Collection
    {
        return $moment->comments()
            ->with('user:id,name') // family members see names, not email addresses
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getLikesCount(Moment $moment): int
    {
        return $moment->likes()->count();
    }

    public function getCommentsCount(Moment $moment): int
    {
        return $moment->comments()->count();
    }
}
