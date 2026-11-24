<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@kinlore.local',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@kinlore.local',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token',
                'token_type',
            ],
        ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@kinlore.local',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@kinlore.local',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@kinlore.local',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@kinlore.local',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_user_receives_token_on_successful_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@kinlore.local',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@kinlore.local',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_an_unknown_email_still_checks_a_password_hash(): void
    {
        // Same work as for a known email, so the answer time does not show which emails exist.
        Hash::shouldReceive('check')->once()->andReturnFalse();

        $this->postJson('/api/auth/login', ['email' => 'nobody@kinlore.local', 'password' => 'password123'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_a_token_not_used_for_90_days_stops_working(): void
    {
        $user = User::factory()->create();
        $token = fn (array $times): string => tap($user->createToken('phone'), fn ($t) => $t->accessToken->forceFill($times)->save())->plainTextToken;
        $me = fn (string $plain) => $this->withToken($plain)->getJson('/api/auth/me');

        // Invalid tokens first: the guard keeps a user it found for the rest of the test.
        $me($token(['last_used_at' => now()->subDays(91)]))->assertUnauthorized();
        $me($token(['last_used_at' => null, 'created_at' => now()->subDays(91)]))->assertUnauthorized();
        $me($token(['last_used_at' => now()->subDays(89), 'created_at' => now()->subYear()]))->assertOk();
    }
}
