<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyServerMemberRequest;
use App\Http\Requests\Api\IndexServerMemberRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Server;
use App\Models\ServerMember;
use App\Services\ServerService;
use Illuminate\Http\JsonResponse;

class ServerMemberController extends Controller
{
    public function __construct(
        private ServerService $serverService
    ) {}

    /**
     * List members of a server.
     */
    public function index(IndexServerMemberRequest $request, Server $server): JsonResponse
    {
        $members = $server->users; // Uses the belongsToMany relationship on Server

        return ApiResponse::success($members);
    }

    /**
     * Remove a member from the server.
     */
    public function destroy(DestroyServerMemberRequest $request, Server $server, ServerMember $member): JsonResponse
    {
        $result = $this->serverService->removeMember($server, $member);

        if (!$result) {
            return ApiResponse::error('Could not remove member. This might be the last owner.', 422);
        }

        return ApiResponse::success(message: 'Member removed successfully');
    }
}
