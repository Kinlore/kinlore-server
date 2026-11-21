<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Kid;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One family per instance: first user is admin, others join by invitation,
 * everyone sees the family, a family member cannot change kids they did not add.
 */
class InstanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    private function register(string $email, ?string $token = null)
    {
        return $this->postJson('/api/auth/register', array_filter([
            'name' => 'Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $token,
        ]));
    }

    public function test_first_user_becomes_admin_and_later_users_need_an_invitation(): void
    {
        $this->register('admin@example.test')->assertStatus(201)->assertJsonPath('data.user.is_admin', true);

        $this->register('stranger@example.test')->assertStatus(422)->assertJsonValidationErrors('invite_token');
        $this->register('stranger@example.test', 'not-a-token')->assertStatus(422);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_invites_and_invitation_works_once(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $token = $this->postJson('/api/v1/invitations')->assertStatus(201)->json('data.token');
        $this->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.token');

        $this->register('guest@example.test', $token)->assertStatus(201)
            ->assertJsonPath('data.user.is_admin', false)
            ->assertJsonPath('data.user.role', 'family');
        $this->register('second@example.test', $token)->assertStatus(422);
    }

    public function test_invitation_rules_for_email_expiry_and_revoke(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $locked = $this->postJson('/api/v1/invitations', ['email' => 'Grandma@example.test'])->json('data.token');
        $this->register('other@example.test', $locked)->assertStatus(422);
        $this->register('grandma@example.test', $locked)->assertStatus(201);

        $expired = $this->postJson('/api/v1/invitations')->json('data');
        Invitation::find($expired['id'])->update(['expires_at' => now()->subMinute()]);
        $this->register('late@example.test', $expired['token'])->assertStatus(422);

        Sanctum::actingAs($admin);
        $revoked = $this->postJson('/api/v1/invitations')->json('data');
        $this->deleteJson("/api/v1/invitations/{$revoked['id']}")->assertOk();
        $this->register('revoked@example.test', $revoked['token'])->assertStatus(422);
    }

    public function test_non_admin_cannot_manage_invitations(): void
    {
        User::factory()->admin()->create();
        Sanctum::actingAs(User::factory()->create()); // partner

        $this->postJson('/api/v1/invitations')->assertStatus(403);
        $this->getJson('/api/v1/invitations')->assertStatus(403);
    }

    public function test_family_shares_kids_but_only_admin_or_creator_changes_them(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->family()->create();
        $adminKid = Kid::factory()->create(['user_id' => $admin->id]);
        $guestKid = Kid::factory()->create(['user_id' => $guest->id]);

        Sanctum::actingAs($guest);
        $this->getJson('/api/v1/kids')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/kids/{$adminKid->id}")->assertOk();
        $this->deleteJson("/api/v1/kids/{$adminKid->id}")->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/kids/{$guestKid->id}")->assertOk();
    }

    public function test_sync_push_cannot_take_over_another_users_kid(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->family()->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id, 'name' => 'Original']);

        app(SyncService::class)->push($guest, ['kids' => ['created' => [[
            'id' => $kid->id, 'name' => 'Hijacked', 'date_of_birth' => 0, 'avatar_color' => '#000000',
        ]]]]);

        $kid->refresh();
        $this->assertSame('Original', $kid->name);
        $this->assertSame($admin->id, $kid->user_id);
    }
}
