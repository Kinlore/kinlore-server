<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Server::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'nullable|array',
        ]);

        $server = $this->serverService->createServer($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $server,
        ], 201);
    }

    /**
     * Display the specified server.
     */
    public function show(Server $server): JsonResponse
    {
        $this->authorize('view', $server);

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
    public function update(Request $request, Server $server): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'settings' => 'nullable|array',
        ]);

        $server->update($validated);

        return response()->json([
            'success' => true,
            'data' => $server,
        ]);
    }

    /**
     * Remove the specified server.
     */
    public function destroy(Server $server): JsonResponse
    {
        $this->authorize('delete', $server);

        $server->delete();

        return response()->json([
            'success' => true,
            'message' => 'Server deleted successfully',
        ]);
    }
}
