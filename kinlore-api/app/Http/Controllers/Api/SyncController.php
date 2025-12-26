<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private SyncService $syncService
    ) {}

    /**
     * Pull changes from server (WatermelonDB sync).
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lastPulledAt' => 'nullable|integer',
            'schemaVersion' => 'required|integer',
            'migration' => 'nullable',
        ]);

        $result = $this->syncService->pull(
            $request->user(),
            $validated['lastPulledAt'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Push changes to server (WatermelonDB sync).
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'changes' => 'required|array',
            'lastPulledAt' => 'nullable|integer',
        ]);

        $this->syncService->push($request->user(), $validated['changes']);

        return response()->json([
            'success' => true,
            'message' => 'Changes synced successfully',
        ]);
    }
}
