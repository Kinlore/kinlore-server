<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptInvitationRequest;
use App\Http\Requests\Api\DestroyInvitationRequest;
use App\Http\Requests\Api\IndexInvitationRequest;
use App\Http\Requests\Api\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\Server;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    /**
     * List invitations for a server.
     */
    public function index(IndexInvitationRequest $request, Server $server): JsonResponse
    {
        $invitations = $this->invitationService->getPendingInvitations($server);

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(StoreInvitationRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

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
    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $member = $this->invitationService->acceptInvitation(
                $validated['token'],
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
    public function destroy(DestroyInvitationRequest $request, Server $server, Invitation $invitation): JsonResponse
    {
        $this->invitationService->revokeInvitation($invitation);

        return response()->json([
            'success' => true,
            'message' => 'Invitation revoked successfully',
        ]);
    }
}
