<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthService
{
    // Checked for an unknown email, so sign-in takes as long as for a known one. Cost 12 is the bcrypt default.
    private const string DUMMY_PASSWORD_HASH = '$2y$12$ZID.CxNLq23g3BnC01olfeEVdvrTeMhPA3Nn8vc3sEho/vRHkXlKS';

    // Under storage/, so the web server never serves it. Relative to storage_path().
    public const string SETUP_TOKEN_FILE = 'app/setup-token';

    public function __construct(
        private readonly FamilyService $family,
    ) {}

    /**
     * Public setup state for the app's first screen.
     *
     * @return array{needs_setup: bool, setup_token_required: bool, instance_name: string, setup_url: string}
     */
    public function setupStatus(): array
    {
        $needsSetup = ! User::exists();

        return [
            'needs_setup' => $needsSetup,
            'setup_token_required' => $needsSetup,
            'instance_name' => config('app.name'),
            'setup_url' => $this->setupUrl(),
        ];
    }

    // From APP_URL when it is real: url() follows the request Host header, which any client chooses.
    public function setupUrl(): string
    {
        return PublicUrl::base().'/setup';
    }

    /**
     * A pending invitation by its token, or null when unknown, used or expired.
     */
    public function findPendingInvitation(string $token): ?Invitation
    {
        return Invitation::pending()->with('inviter')->where('token', $token)->first();
    }

    /**
     * Create the instance's first user as admin (web setup page only).
     *
     * @param  array{name: string, email: string, password: string, family_name?: string|null, setup_token?: string|null}  $data
     *
     * @throws ValidationException when the setup token does not match, or the instance already has a user
     */
    public function createFirstAdmin(#[\SensitiveParameter] array $data): User
    {
        // The lock serializes setups, so two parallel first sign-ups cannot both see an empty instance.
        $admin = Cache::lock('kinlore:register', 10)->block(5, fn () => DB::transaction(function () use ($data): User {
            if (User::exists()) {
                throw ValidationException::withMessages(['email' => ['This instance is already set up.']]);
            }

            $this->checkSetupToken($data['setup_token'] ?? null);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => Role::Admin,
            ]);
            // Stored now, so the family name does not change when the admin edits their own name.
            $this->family->rename(filled($data['family_name'] ?? null) ? $data['family_name'] : FamilyService::suggest($user->name));

            return $user;
        }));
        $this->forgetSetupToken();

        return $admin;
    }

    /**
     * The token the setup page asks for: SETUP_TOKEN when set, else a random one kept in a file until setup.
     *
     * @throws RuntimeException when the token file cannot be made or read
     */
    public function setupToken(): string
    {
        if (filled(config('app.setup_token'))) {
            return (string) config('app.setup_token');
        }

        $path = storage_path(self::SETUP_TOKEN_FILE);
        if (! is_file($path) && ($temp = tempnam(dirname($path), 'setup-token-')) !== false) {
            file_put_contents($temp, Str::random(40));
            // Owned like its folder, so PHP-FPM can read and delete it when root ran the command.
            @chown($temp, (int) fileowner(dirname($path)));
            // link() fails when the file exists, so parallel callers all end with the first token.
            @link($temp, $path);
            unlink($temp);
        }

        $token = trim((string) @file_get_contents($path));
        if (strlen($token) < 32) {
            throw new RuntimeException("Cannot read the setup token in {$path}.");
        }

        return $token;
    }

    public function forgetSetupToken(): void
    {
        @unlink(storage_path(self::SETUP_TOKEN_FILE));
    }

    /**
     * Register an invited user with the invitation's role and permissions.
     * The first admin is created on the web setup page instead (createFirstAdmin).
     *
     * @param  array{name: string, email: string, password: string, invite_token: string}  $data
     * @return array{user: array, token: string, token_type: string}
     *
     * @throws ValidationException
     */
    public function register(#[\SensitiveParameter] array $data): array
    {
        // Same lock as the setup, so an invitation cannot be claimed twice in parallel.
        $user = Cache::lock('kinlore:register', 10)->block(5, fn () => DB::transaction(function () use ($data): User {
            $invitation = $this->claimInvitation($data['invite_token'], $data['email']);

            $user = User::create($invitation->only(['role', 'can_add_content', 'can_comment', 'can_like', 'can_download']) + [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

            return $user;
        }));

        $token = $user->createToken('mobile-app', ['*'])->plainTextToken;

        return [
            'user' => $this->userPayload($user->refresh()),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * @throws ValidationException
     */
    private function checkSetupToken(?string $given): void
    {
        if (! hash_equals($this->setupToken(), (string) $given)) {
            throw ValidationException::withMessages([
                'setup_token' => ['A valid setup token is required to set up this instance.'],
            ]);
        }
    }

    /**
     * Lock and return a usable invitation, or fail validation.
     *
     * @throws ValidationException
     */
    private function claimInvitation(string $token, string $email): Invitation
    {
        $invitation = Invitation::pending()->where('token', $token)->lockForUpdate()->first();

        if (! $invitation || ($invitation->email && strcasecmp($invitation->email, $email) !== 0)) {
            throw ValidationException::withMessages([
                'invite_token' => ['A valid invitation is required to join this instance.'],
            ]);
        }

        // Only after the invitation check, so only an invited person learns that an email is taken.
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }

        return $invitation;
    }

    /**
     * @return array{user: array, token: string, token_type: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, #[\SensitiveParameter] string $password): array
    {
        $user = User::where('email', $email)->first();
        $valid = Hash::check($password, $user ? $user->password : self::DUMMY_PASSWORD_HASH);

        if (! $user || ! $valid) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return [
            'user' => $this->userPayload($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    // Revoke the current token and return a new one
    public function refresh(User $user): string
    {
        $user->currentAccessToken()->delete();

        return $user->createToken('mobile-app')->plainTextToken;
    }

    // docs/reference/api.md section 2
    private function userPayload(User $user): array
    {
        return $user->toPayload() + ['email_verified_at' => $user->email_verified_at?->toIso8601String()];
    }

    // User payload with family-wide counts and storage, plus the user's own uploads.
    public function getUserInfo(User $user): array
    {
        $storageBytesUsed = (int) Moment::sum('file_size');

        return $this->userPayload($user) + [
            'kids_count' => Kid::count(),
            'moments_count' => Moment::count(),
            'my_moments_count' => $user->uploadedMoments()->count(),
            'storage_used' => $this->formatBytes($storageBytesUsed),
            'storage_bytes_used' => $storageBytesUsed,
            'storage_bytes_available' => (int) disk_free_space(storage_path()),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => number_format($bytes / 1073741824, 2).' GB',
            $bytes >= 1048576 => number_format($bytes / 1048576, 2).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 2).' KB',
            default => $bytes.' bytes',
        };
    }

    public function getSessions(User $user): Collection
    {
        $currentTokenId = $user->currentAccessToken()->id;

        return $user->tokens->map(fn ($token): array => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at->toIso8601String(),
            'is_current' => $token->id === $currentTokenId,
        ]);
    }

    public function revokeSession(User $user, int $tokenId): bool
    {
        return (bool) $user->tokens()->where('id', $tokenId)->delete();
    }
}
