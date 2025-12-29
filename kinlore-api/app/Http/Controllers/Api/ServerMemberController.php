<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerMember;
use App\Services\ServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerMemberController extends Controller
{
    public function __construct(
        private ServerService $serverService
    ) {}

    /**
     * List members of a server.
     */
    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        $members = $server->users; // Uses the belongsToMany relationship on Server

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Remove a member from the server.
     */
    public function destroy(Server $server, ServerMember $member): JsonResponse
    {
        // Ensure member belongs to server
        if ($member->server_id !== $server->id) {
            abort(404, 'Member not found in this server');
        }

        $this->authorize('delete', $member);

        $result = $this->serverService->removeMember($server, $member);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Could not remove member. This might be the last owner.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);
    }
}
