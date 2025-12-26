<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->authService->register($validated);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $result = $this->authService->login(
            $request->email,
            $request->password
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