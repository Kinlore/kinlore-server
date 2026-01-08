<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->register($validated, $validated['invite_token'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            $validated['email'],
            $validated['password']
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $result,
        ]);
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
        ]);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request): JsonResponse
    {
        $userData = $this->authService->getUserInfo($request->user());

        return response()->json([
            'success' => true,
            'data' => $userData,
        ]);
    }

    /**
     * Refresh token (revoke old, create new).
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->authService->refresh($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Get all active sessions (tokens).
     */
    public function sessions(Request $request): JsonResponse
    {
        $sessions = $this->authService->getSessions($request->user());

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Revoke specific token/session.
     */
    public function revokeSession(Request $request, $tokenId): JsonResponse
    {
        $deleted = $this->authService->revokeSession($request->user(), $tokenId);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session revoked',
        ]);
    }
}
