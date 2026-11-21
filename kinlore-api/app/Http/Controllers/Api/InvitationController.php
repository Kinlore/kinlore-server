<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvitationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Admin-only invitations. All routes are behind the `can:admin` middleware.
 */
class InvitationController extends Controller
{
    /**
     * List pending invitations.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(Invitation::pending()->with('inviter')->latest()->get()->map->toItem());
    }

    /**
     * Create an invitation. The token is returned only once, here.
     */
    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $token = Str::random(48);

        $permissions = $validated['permissions'] ?? [];

        $invitation = Invitation::create([
            'token' => $token,
            'email' => $validated['email'] ?? null,
            'role' => $validated['role'] ?? 'family',
            'can_add_content' => $permissions['add_content'] ?? true,
            'can_comment' => $permissions['comment'] ?? true,
            'can_like' => $permissions['like'] ?? true,
            'can_download' => $permissions['download'] ?? true,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays((int) ($validated['expires_in_days'] ?? 7)),
        ]);

        return ApiResponse::success(
            $invitation->toItem() + [
                'token' => $token,
                'invite_link' => $invitation->inviteLink(),
                'invite_url' => $invitation->inviteUrl(),
            ],
            'Invitation created',
            201
        );
    }

    /**
     * Revoke an invitation that was not used yet.
     */
    public function destroy(Invitation $invitation): JsonResponse
    {
        if ($invitation->accepted_at) {
            return ApiResponse::error('Invitation was already used', 409);
        }

        $invitation->delete();

        return ApiResponse::success(null, 'Invitation revoked');
    }
}
