<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $inviteToken;

    // Registration is for invited users; the first admin comes from the web setup page.
    protected function setUp(): void
    {
        parent::setUp();

        $this->inviteToken = Invitation::create([
            'token' => 'tok-registration',
            'role' => 'family',
            'invited_by' => User::factory()->admin()->create()->id,
            'expires_at' => now()->addDays(7),
        ])->token;
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@kinlore.local',
            'password' => 'password123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'token',
                'token_type',
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@kinlore.local',
            'name' => 'Test User',
        ]);
    }

    public function test_user_cannot_register_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@kinlore.local',
            'password' => '123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_mismatched_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@kinlore.local',
            'password' => 'password123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => 'different123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@kinlore.local']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@kinlore.local',
            'password' => 'password123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@kinlore.local',
            'password' => 'password123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_registered_user_receives_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@kinlore.local',
            'password' => 'password123',
            'invite_token' => $this->inviteToken,
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals('Bearer', $response->json('data.token_type'));
    }

    public function test_without_a_valid_invitation_the_answer_does_not_show_whether_an_email_is_a_member(): void
    {
        User::factory()->create(['email' => 'member@kinlore.local']);

        $this->postJson('/api/auth/register', [
            'name' => 'Stranger',
            'email' => 'member@kinlore.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => 'not-an-invitation',
        ])->assertStatus(422)->assertJsonValidationErrors(['invite_token'])->assertJsonMissingValidationErrors(['email']);
    }
}
