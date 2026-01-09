<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PullSyncRequest;
use App\Http\Requests\Api\PushSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        private SyncService $syncService
    ) {}

    /**
     * Pull changes from server (WatermelonDB sync).
     */
    public function pull(PullSyncRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->syncService->pull(
            $request->user(),
            $validated['lastPulledAt'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Push changes to server (WatermelonDB sync).
     */
    public function push(PushSyncRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->syncService->push($request->user(), $validated['changes']);

        return ApiResponse::success(message: 'Changes synced successfully');
    }
}
