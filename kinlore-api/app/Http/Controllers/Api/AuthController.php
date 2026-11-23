<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Public: does this instance still need its first user?
     */
    public function setupStatus(): JsonResponse
    {
        return ApiResponse::success($this->authService->setupStatus());
    }

    /**
     * Public: check an invitation token before showing the sign-up form.
     */
    public function checkInvitation(string $token): JsonResponse
    {
        $invitation = $this->authService->findPendingInvitation($token);

        if (! $invitation) {
            return ApiResponse::error('Invitation not found, already used or expired', 404);
        }

        return ApiResponse::success([
            'valid' => true,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'invited_by' => ['name' => $invitation->inviter?->name],
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'instance_name' => config('app.name'),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->register($validated);

        return ApiResponse::success($result, 'User registered successfully', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            $validated['email'],
            // The rule is only `required`, so a JSON number must become a string.
            $request->string('password')->toString(),
        );

        return ApiResponse::success($result, 'Login successful');
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(message: 'Logged out successfully');
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return ApiResponse::success(message: 'Logged out from all devices');
    }

    public function me(Request $request): JsonResponse
    {
        $userData = $this->authService->getUserInfo($request->user());

        return ApiResponse::success($userData);
    }

    /**
     * Refresh token (revoke old, create new).
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->authService->refresh($request->user());

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed');
    }

    /**
     * Get all active sessions (tokens).
     */
    public function sessions(Request $request): JsonResponse
    {
        $sessions = $this->authService->getSessions($request->user());

        return ApiResponse::success($sessions);
    }

    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        $deleted = $this->authService->revokeSession($request->user(), (int) $tokenId);

        if (! $deleted) {
            return ApiResponse::error('Token not found', 404);
        }

        return ApiResponse::success(message: 'Session revoked');
    }
}
