<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CommentMomentRequest;
use App\Http\Requests\Api\LikeMomentRequest;
use App\Http\Requests\Api\ListMomentCommentsRequest;
use App\Http\Requests\Api\UnlikeMomentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Services\InteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MomentInteractionController extends Controller
{
    public function __construct(
        private readonly InteractionService $interactionService,
    ) {}

    public function like(LikeMomentRequest $request, Moment $moment): JsonResponse
    {
        $interaction = $this->interactionService->like($request->user(), $moment);

        if ($interaction === null) {
            return ApiResponse::success(message: 'Already liked');
        }

        return ApiResponse::success(message: 'Moment liked', status: 201);
    }

    public function unlike(UnlikeMomentRequest $request, Moment $moment): JsonResponse
    {
        $deleted = $this->interactionService->unlike($request->user(), $moment);

        if ($deleted) {
            return ApiResponse::success(message: 'Moment unliked');
        }

        return ApiResponse::error('Not liked yet', 404);
    }

    public function comment(CommentMomentRequest $request, Moment $moment): JsonResponse
    {
        $validated = $request->validated();

        $interaction = $this->interactionService->comment(
            $request->user(),
            $moment,
            $validated['content']
        );

        return ApiResponse::success($interaction, 'Comment posted', 201);
    }

    public function deleteComment(Request $request, MomentInteraction $interaction): JsonResponse
    {
        // Only the author or the admin can delete a comment
        try {
            $this->interactionService->deleteComment($request->user(), $interaction);

            return ApiResponse::success(message: 'Comment deleted');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
    }

    public function index(ListMomentCommentsRequest $request, Moment $moment): JsonResponse
    {
        $comments = $this->interactionService->getComments($moment);

        return ApiResponse::success($comments);
    }
}
