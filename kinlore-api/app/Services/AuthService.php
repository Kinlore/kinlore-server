<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     * The first user of the instance becomes the admin. Everyone after that
     * needs a valid invitation token created by the admin.
     *
     * @param array{name: string, email: string, password: string, invite_token?: string|null} $data
     * @return array{user: array, token: string, token_type: string}
     * @throws ValidationException
     */
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data) {
            // shortcut: two sign-ups on an empty instance at the same instant could both become admin; add a setup lock if instances ever get exposed before the owner registers.
            $isFirstUser = !User::exists();
            $invitation = $isFirstUser ? null : $this->claimInvitation($data['invite_token'] ?? null, $data['email']);

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->is_admin = $isFirstUser;
            $user->save();

            $invitation?->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

            return $user;
        });

        $token = $user->createToken('mobile-app', ['*'])->plainTextToken;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Lock and return a usable invitation, or fail validation.
     *
     * @throws ValidationException
     */
    private function claimInvitation(?string $token, string $email): Invitation
    {
        $invitation = $token
            ? Invitation::pending()->where('token', $token)->lockForUpdate()->first()
            : null;

        if (!$invitation || ($invitation->email && strcasecmp($invitation->email, $email) !== 0)) {
            throw ValidationException::withMessages([
                'invite_token' => ['A valid invitation is required to join this instance.'],
            ]);
        }

        return $invitation;
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
                'is_admin' => $user->is_admin,
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
            'is_admin' => $user->is_admin,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at->toIso8601String(),
            'kids_count' => $user->kids()->count(),
            'moments_count' => $user->getTotalMomentsCount(),
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
