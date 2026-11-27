<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// The token name is the device name in GET /auth/sessions.
class DeviceNameTest extends TestCase
{
    use RefreshDatabase;

    private function login(array $extra = []): TestResponse
    {
        User::factory()->create(['email' => 'test@kinlore.local', 'password' => bcrypt('password123')]);

        return $this->postJson('/api/auth/login', ['email' => 'test@kinlore.local', 'password' => 'password123'] + $extra);
    }

    private function sessionNames(string $token): array
    {
        return $this->withToken($token)->getJson('/api/auth/sessions')->assertOk()->json('data.*.name');
    }

    public function test_login_with_device_name_names_the_session(): void
    {
        $token = $this->login(['device_name' => '  Elyess’s iPhone  '])->assertOk()->json('data.token');

        $this->assertSame(['Elyess’s iPhone'], $this->sessionNames($token));
    }

    public function test_login_without_device_name_uses_the_old_name(): void
    {
        $token = $this->login()->assertOk()->json('data.token');

        $this->assertSame(['mobile-app'], $this->sessionNames($token));
    }

    public function test_login_with_empty_device_name_uses_the_old_name(): void
    {
        $token = $this->login(['device_name' => '   '])->assertOk()->json('data.token');

        $this->assertSame(['mobile-app'], $this->sessionNames($token));
    }

    public function test_refresh_keeps_the_device_name(): void
    {
        $token = $this->login(['device_name' => 'Pixel 9'])->json('data.token');
        $newToken = $this->withToken($token)->postJson('/api/auth/refresh')->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->assertSame(['Pixel 9'], $this->sessionNames($newToken));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidDeviceNames(): array
    {
        return [
            'too long' => [str_repeat('a', 61)],
            'number' => [123],
            'array' => [['iPhone']],
            'control character' => ["iPhone\x07"],
        ];
    }

    #[DataProvider('invalidDeviceNames')]
    public function test_login_rejects_an_invalid_device_name(mixed $deviceName): void
    {
        $this->login(['device_name' => $deviceName])->assertStatus(422)->assertJsonValidationErrors('device_name');
    }

    public function test_register_with_device_name_names_the_session(): void
    {
        $invitation = Invitation::create([
            'token' => 'tok-device',
            'role' => 'family',
            'invited_by' => User::factory()->admin()->create()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $token = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'new@kinlore.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $invitation->token,
            'device_name' => 'iPad',
        ])->assertCreated()->json('data.token');

        $this->assertSame(['iPad'], $this->sessionNames($token));
    }

    public function test_register_rejects_a_too_long_device_name(): void
    {
        User::factory()->admin()->create(); // registration is closed until setup made the first user

        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'new@kinlore.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => 'whatever',
            'device_name' => str_repeat('a', 61),
        ])->assertStatus(422)->assertJsonValidationErrors('device_name');
    }
}
