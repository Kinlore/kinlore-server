<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Member management by the admin (docs/API_CONTRACT.md section 4).
 */
class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_members(): void
    {
        $admin = User::factory()->admin()->create();
        $family = User::factory()->family(['can_download' => false])->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id]);
        Moment::factory()->count(2)->create(['kid_id' => $kid->id, 'user_id' => $family->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/members')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'admin')
            ->assertJsonPath('data.1.id', $family->id)
            ->assertJsonPath('data.1.is_admin', false)
            ->assertJsonPath('data.1.moments_count', 2)
            ->assertJsonPath('data.1.permissions.download', false);
    }

    public function test_only_admin_manages_members(): void
    {
        User::factory()->admin()->create();
        $partner = User::factory()->create();
        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/members')->assertForbidden();
        $this->patchJson("/api/v1/members/{$partner->id}", ['role' => 'admin'])->assertForbidden();
        $this->deleteJson("/api/v1/members/{$partner->id}")->assertForbidden();
    }

    public function test_admin_updates_role_and_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->family()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/members/{$member->id}", ['permissions' => ['like' => false, 'comment' => false]])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['add_content' => true, 'comment' => false, 'like' => false, 'download' => true]);

        $this->patchJson("/api/v1/members/{$member->id}", ['role' => 'partner'])
            ->assertOk()->assertJsonPath('data.role', 'partner');

        $this->patchJson("/api/v1/members/{$member->id}", ['role' => 'owner'])->assertStatus(422);
    }

    public function test_setting_admin_on_another_user_transfers_the_role(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->family()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/members/{$member->id}", ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin')->assertJsonPath('data.is_admin', true);

        $this->assertSame(Role::Partner, $admin->fresh()->role);
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_admin_cannot_demote_or_remove_themself(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/members/{$admin->id}", ['role' => 'partner'])->assertStatus(422);
        $this->patchJson("/api/v1/members/{$admin->id}", ['role' => 'family'])->assertStatus(422);
        $this->patchJson("/api/v1/members/{$admin->id}", ['role' => 'admin'])->assertOk();
        $this->deleteJson("/api/v1/members/{$admin->id}")->assertStatus(422);

        $this->assertSame(Role::Admin, $admin->fresh()->role);
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_removing_a_member_keeps_kids_and_moments(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $member->createToken('mobile-app');

        $theirKid = Kid::factory()->create(['user_id' => $member->id]);
        $momentOnTheirKid = Moment::factory()->create(['kid_id' => $theirKid->id, 'user_id' => $member->id]);
        $adminKid = Kid::factory()->create(['user_id' => $admin->id]);
        $theirUpload = Moment::factory()->create(['kid_id' => $adminKid->id, 'user_id' => $member->id]);
        foreach (['like' => null, 'comment' => 'Cute'] as $type => $content) {
            MomentInteraction::create(['user_id' => $member->id, 'moment_id' => $theirUpload->id, 'type' => $type, 'content' => $content]);
        }
        $adminComment = MomentInteraction::create(['user_id' => $admin->id, 'moment_id' => $theirUpload->id, 'type' => 'comment', 'content' => 'Yes']);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/members/{$member->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $member->id]);
        $this->assertSame($admin->id, (int) $theirKid->fresh()->user_id);
        $this->assertNull($momentOnTheirKid->fresh()->user_id);
        $this->assertNull($theirUpload->fresh()->user_id);
        $this->assertSame(2, Kid::count());
        $this->assertSame(2, Moment::count());
        $this->assertSame([$adminComment->id], MomentInteraction::pluck('id')->all());
    }
}
