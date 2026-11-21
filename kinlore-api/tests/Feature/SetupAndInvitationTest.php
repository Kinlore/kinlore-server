<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Setup status, the first admin, invitations and the public invite page (docs/API_CONTRACT.md sections 2 and 3).
 */
class SetupAndInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $extra = [])
    {
        return $this->postJson('/api/auth/register', $extra + [
            'name' => 'Person',
            'email' => 'person@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    private function invitation(array $attributes = []): Invitation
    {
        return Invitation::create($attributes + [
            'token' => 'tok-' . uniqid(),
            'role' => 'family',
            'invited_by' => User::factory()->admin()->create(['name' => 'Ann'])->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_setup_status(): void
    {
        config(['app.name' => 'The Smiths', 'app.setup_token' => null]);
        $this->getJson('/api/auth/setup-status')->assertOk()->assertJson(['data' => [
            'needs_setup' => true, 'setup_token_required' => false, 'instance_name' => 'The Smiths',
        ]]);

        config(['app.setup_token' => 'secret']);
        $this->getJson('/api/auth/setup-status')->assertJsonPath('data.setup_token_required', true);

        User::factory()->admin()->create();
        $this->getJson('/api/auth/setup-status')
            ->assertJsonPath('data.needs_setup', false)
            ->assertJsonPath('data.setup_token_required', false);
    }

    public function test_first_user_becomes_admin_without_setup_token(): void
    {
        config(['app.setup_token' => null]);

        $this->register()->assertStatus(201)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.is_admin', true)
            ->assertJsonPath('data.user.permissions', ['add_content' => true, 'comment' => true, 'like' => true, 'download' => true]);
    }

    public function test_first_user_needs_the_setup_token_when_set(): void
    {
        config(['app.setup_token' => 'secret']);

        $this->register()->assertStatus(422)->assertJsonValidationErrors('setup_token');
        $this->register(['setup_token' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('setup_token');
        $this->assertDatabaseCount('users', 0);

        $this->register(['setup_token' => 'secret'])->assertStatus(201)->assertJsonPath('data.user.role', 'admin');
    }

    public function test_check_invitation_endpoint(): void
    {
        config(['app.name' => 'The Smiths']);
        $valid = $this->invitation(['email' => 'grandma@example.test', 'role' => 'partner']);

        $this->getJson("/api/auth/invitations/{$valid->token}")->assertOk()->assertJson(['data' => [
            'valid' => true, 'email' => 'grandma@example.test', 'role' => 'partner',
            'invited_by' => ['name' => 'Ann'], 'instance_name' => 'The Smiths',
        ]]);

        $used = $this->invitation(['accepted_at' => now()]);
        $expired = $this->invitation(['expires_at' => now()->subMinute()]);
        $this->getJson("/api/auth/invitations/{$used->token}")->assertStatus(404);
        $this->getJson("/api/auth/invitations/{$expired->token}")->assertStatus(404);
        $this->getJson('/api/auth/invitations/unknown')->assertStatus(404);
    }

    public function test_invite_page_shows_details_escaped_and_404_otherwise(): void
    {
        config(['app.name' => 'The <Smiths>', 'app.url' => 'https://kin.example.test']);
        $invitation = $this->invitation();
        $invitation->inviter->update(['name' => '<script>alert(1)</script>']);

        $this->get("/invite/{$invitation->token}")->assertOk()
            ->assertSee('The &lt;Smiths&gt;', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>', false)
            ->assertSee('https://kin.example.test')
            ->assertSee($invitation->token)
            ->assertSee('kinlore://invite/' . $invitation->token . '?server=https%3A%2F%2Fkin.example.test', false);

        $used = $this->invitation(['accepted_at' => now()]);
        $this->get("/invite/{$used->token}")->assertNotFound()->assertSee('Invitation not found');
        $this->get('/invite/unknown')->assertNotFound();
    }

    public function test_admin_creates_invitation_with_role_permissions_and_links(): void
    {
        config(['app.url' => 'https://kin.example.test']);
        $admin = User::factory()->admin()->create(['name' => 'Ann']);
        Sanctum::actingAs($admin);

        $data = $this->postJson('/api/v1/invitations', [
            'role' => 'family', 'permissions' => ['comment' => false], 'expires_in_days' => 30,
        ])->assertStatus(201)->json('data');

        $this->assertSame('family', $data['role']);
        $this->assertSame(['add_content' => true, 'comment' => false, 'like' => true, 'download' => true], $data['permissions']);
        $this->assertSame(['id' => $admin->id, 'name' => 'Ann'], $data['invited_by']);
        $this->assertSame("kinlore://invite/{$data['token']}?server=https%3A%2F%2Fkin.example.test", $data['invite_link']);
        $this->assertSame("https://kin.example.test/invite/{$data['token']}", $data['invite_url']);

        $this->postJson('/api/v1/invitations')->assertStatus(201)->assertJsonPath('data.role', 'family');
        $this->postJson('/api/v1/invitations', ['expires_in_days' => 31])->assertStatus(422);
        $this->postJson('/api/v1/invitations', ['expires_in_days' => 0])->assertStatus(422);
        $this->postJson('/api/v1/invitations', ['role' => 'admin'])->assertStatus(422);

        $this->getJson('/api/v1/invitations')->assertOk()
            ->assertJsonPath('data.0.invited_by.name', 'Ann')
            ->assertJsonMissingPath('data.0.token');
    }

    public function test_registered_user_gets_role_and_permissions_of_the_invitation(): void
    {
        $invitation = $this->invitation(['role' => 'family', 'can_comment' => false, 'can_download' => false]);

        $this->register(['invite_token' => $invitation->token])->assertStatus(201)
            ->assertJsonPath('data.user.role', 'family')
            ->assertJsonPath('data.user.is_admin', false)
            ->assertJsonPath('data.user.permissions', ['add_content' => true, 'comment' => false, 'like' => true, 'download' => false]);

        $partner = $this->invitation(['role' => 'partner', 'can_like' => false]);
        $this->register(['invite_token' => $partner->token, 'email' => 'p@example.test'])->assertStatus(201)
            ->assertJsonPath('data.user.role', 'partner')
            ->assertJsonPath('data.user.permissions.like', true); // permissions only limit family
    }

    public function test_me_has_family_wide_counts_and_own_uploads(): void
    {
        $admin = User::factory()->admin()->create();
        $family = User::factory()->family()->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id]);
        Kid::factory()->create(['user_id' => $admin->id]);
        Moment::factory()->count(3)->create(['kid_id' => $kid->id, 'user_id' => $admin->id, 'file_size' => 1024]);
        Moment::factory()->create(['kid_id' => $kid->id, 'user_id' => $family->id, 'file_size' => 1024]);

        Sanctum::actingAs($family);
        $this->getJson('/api/auth/me')->assertOk()->assertJson(['data' => [
            'id' => $family->id, 'role' => 'family', 'is_admin' => false,
            'kids_count' => 2, 'moments_count' => 4, 'my_moments_count' => 1,
            'storage_bytes_used' => 4096, 'storage_used' => '4.00 KB',
        ]])->assertJsonStructure(['data' => ['permissions', 'created_at', 'email_verified_at', 'storage_bytes_available']]);
    }

    public function test_login_returns_the_user_payload(): void
    {
        $user = User::factory()->family(['can_like' => false])->create(['email' => 'f@example.test']);

        $this->postJson('/api/auth/login', ['email' => 'f@example.test', 'password' => 'password'])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'family')
            ->assertJsonPath('data.user.permissions.like', false);
    }
}
