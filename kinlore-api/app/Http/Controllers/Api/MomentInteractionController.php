<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Services\InteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MomentInteractionController extends Controller
{
    public function __construct(
        private InteractionService $interactionService
    ) {}

    /**
     * Like a moment.
     */
    public function like(Request $request, Moment $moment): JsonResponse
    {
        $interaction = $this->interactionService->like($request->user(), $moment);

        if ($interaction === null) {
            return response()->json(['success' => true, 'message' => 'Already liked'], 200);
        }

        return response()->json(['success' => true, 'message' => 'Moment liked'], 201);
    }

    /**
     * Unlike a moment.
     */
    public function unlike(Request $request, Moment $moment): JsonResponse
    {
        $deleted = $this->interactionService->unlike($request->user(), $moment);

        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Moment unliked'], 200);
        }

        return response()->json(['success' => false, 'message' => 'Not liked yet'], 404);
    }

    /**
     * Post a comment.
     */
    public function comment(Request $request, Moment $moment): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $interaction = $this->interactionService->comment(
            $request->user(),
            $moment,
            $request->input('content')
        );

        return response()->json([
            'success' => true,
            'message' => 'Comment posted',
            'data' => $interaction,
        ], 201);
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(Request $request, MomentInteraction $interaction): JsonResponse
    {
        try {
            $this->interactionService->deleteComment($request->user(), $interaction);
            
            return response()->json(['success' => true, 'message' => 'Comment deleted'], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get comments for a moment.
     */
    public function index(Moment $moment): JsonResponse
    {
        $comments = $this->interactionService->getComments($moment);

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }
}
