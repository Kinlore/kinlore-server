<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyServerRequest;
use App\Http\Requests\Api\ShowServerRequest;
use App\Http\Requests\Api\StoreServerRequest;
use App\Http\Requests\Api\UpdateServerRequest;
use App\Models\Server;
use App\Services\ServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function __construct(
        private ServerService $serverService
    ) {}

    /**
     * Get user's servers.
     */
    public function userServers(Request $request): JsonResponse
    {
        $servers = $this->serverService->getServersForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $servers,
        ]);
    }

    /**
     * Store a newly created server.
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $server = $this->serverService->createServer($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $server,
        ], 201);
    }

    /**
     * Display the specified server.
     */
    public function show(ShowServerRequest $request, Server $server): JsonResponse
    {
        // Load stats/details if needed
        $server->loadCount(['members', 'kids']);

        return response()->json([
            'success' => true,
            'data' => $server,
            'meta' => [
                'role' => $server->getMemberRole(request()->user()),
            ]
        ]);
    }

    /**
     * Update the specified server.
     */
    public function update(UpdateServerRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        $server->update($validated);

        return response()->json([
            'success' => true,
            'data' => $server,
        ]);
    }

    /**
     * Remove the specified server.
     */
    public function destroy(DestroyServerRequest $request, Server $server): JsonResponse
    {
        $server->delete();

        return response()->json([
            'success' => true,
            'message' => 'Server deleted successfully',
        ]);
    }
}
