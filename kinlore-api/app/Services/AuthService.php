<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Service for handling authentication operations.
 */
class AuthService
{
    /**
     * Register a new user.
     *
     * @param array{name: string, email: string, password: string} $data
     * @return array{user: array, token: string, token_type: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Create default server
        $this->serverService->createServer($user, [
            'name' => $user->name . "'s Family",
        ]);

        $token = $user->createToken('mobile-app', ['*'])->plainTextToken;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Login user and create token.
     *
     * @throws ValidationException
     * @return array{user: array, token: string, token_type: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Refresh token (revoke old, create new).
     */
    public function refresh(User $user): string
    {
        $user->currentAccessToken()->delete();
        
        return $user->createToken('mobile-app')->plainTextToken;
    }

    /**
     * Get user info with storage usage.
     */
    public function getUserInfo(User $user): array
    {
        $storageBytesUsed = $user->getStorageUsed();
        $storageBytesAvailable = (int) disk_free_space(storage_path());

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at->toIso8601String(),
            'kids_count' => $user->servers()->withCount('kids')->get()->sum('kids_count'), // Count all kids in servers user is member of
            'moments_count' => $user->getTotalMomentsCount(), // This needs update in User model ideally
            'storage_used' => $user->getStorageUsedFormatted(),
            'storage_bytes_used' => $storageBytesUsed,
            'storage_bytes_available' => $storageBytesAvailable,
        ];
    }

    /**
     * Get all active sessions (tokens).
     */
    public function getSessions(User $user): Collection
    {
        $currentTokenId = $user->currentAccessToken()->id;

        return $user->tokens->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
                'is_current' => $token->id === $currentTokenId,
            ];
        });
    }

    /**
     * Revoke specific session/token.
     */
    public function revokeSession(User $user, int $tokenId): bool
    {
        return (bool) $user->tokens()->where('id', $tokenId)->delete();
    }
}
