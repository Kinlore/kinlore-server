<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Server;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    /**
     * List invitations for a server.
     */
    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorize('viewAny', [Invitation::class, $server]);

        $invitations = $this->invitationService->getPendingInvitations($server);

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(Request $request, Server $server): JsonResponse
    {
        $this->authorize('create', [Invitation::class, $server]);

        $validated = $request->validate([
            'role' => 'nullable|in:viewer,contributor',
            'email' => 'nullable|email',
            'expires_in_days' => 'nullable|integer|min:1|max:90',
        ]);

        $invitation = $this->invitationService->createInvitation(
            $server, 
            $request->user(),
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $invitation->append('invite_link'),
        ], 201);
    }

    /**
     * Validate an invitation token.
     * Public endpoint.
     */
    public function validate(string $token): JsonResponse
    {
        $result = $this->invitationService->validateToken($token);

        return response()->json([
            'success' => true,
            'valid' => $result['valid'],
            'reason' => $result['reason'],
            'data' => $result['invitation'], // Will be null if invalid
        ]);
    }

    /**
     * Accept an invitation.
     */
    public function accept(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $member = $this->invitationService->acceptInvitation(
                $request->token,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted',
                'data' => $member,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Revoke an invitation.
     */
    public function destroy(Server $server, Invitation $invitation): JsonResponse
    {
        // Ensure invitation belongs to server
        if ($invitation->server_id !== $server->id) {
            abort(404, 'Invitation not found in this server');
        }

        $this->authorize('delete', $invitation);

        $this->invitationService->revokeInvitation($invitation);

        return response()->json([
            'success' => true,
            'message' => 'Invitation revoked successfully',
        ]);
    }
}
