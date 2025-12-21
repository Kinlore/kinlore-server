<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moment;
use App\Models\MomentInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MomentInteractionController extends Controller
{
    /**
     * Like a moment.
     */
    public function like(Request $request, Moment $moment)
    {
        // Check if already liked
        $existingLike = $moment->likes()->where('user_id', Auth::id())->first();

        if ($existingLike) {
            return response()->json(['message' => 'Already liked'], 200);
        }

        $interaction = new MomentInteraction([
            'user_id' => Auth::id(),
            'moment_id' => $moment->id,
            'type' => 'like',
        ]);
        
        $interaction->save();

        return response()->json(['message' => 'Moment liked'], 201);
    }

    /**
     * Unlike a moment.
     */
    public function unlike(Request $request, Moment $moment)
    {
        $deleted = $moment->likes()->where('user_id', Auth::id())->delete();

        if ($deleted) {
            return response()->json(['message' => 'Moment unliked'], 200);
        }

        return response()->json(['message' => 'Not liked yet'], 404);
    }

    /**
     * Post a comment.
     */
    public function comment(Request $request, Moment $moment)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $interaction = new MomentInteraction([
            'user_id' => Auth::id(),
            'moment_id' => $moment->id,
            'type' => 'comment',
            'content' => $request->input('content'),
        ]);

        $interaction->save();

        return response()->json([
            'message' => 'Comment posted',
            'data' => $interaction->load('user'),
        ], 201);
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(Request $request, MomentInteraction $interaction)
    {
        // Verify ownership and type
        if ($interaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($interaction->type !== 'comment') {
            return response()->json(['message' => 'Invalid interaction type'], 400);
        }

        $interaction->delete();

        return response()->json(['message' => 'Comment deleted'], 200);
    }
}
