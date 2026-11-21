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
        return ApiResponse::success(Invitation::pending()->latest()->get());
    }

    /**
     * Create an invitation. The token is returned only once, here.
     */
    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $token = Str::random(48);

        $invitation = Invitation::create([
            'token' => $token,
            'email' => $validated['email'] ?? null,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays($validated['expires_in_days'] ?? 7),
        ]);

        return ApiResponse::success(
            $invitation->toArray() + ['token' => $token, 'invite_link' => "kinlore://invite/{$token}"],
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
