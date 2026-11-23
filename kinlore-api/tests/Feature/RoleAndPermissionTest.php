<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Roles and permissions (docs/API_CONTRACT.md section 1).
 */
class RoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_cannot_create_kids_but_partner_can(): void
    {
        $payload = ['name' => 'Emma', 'date_of_birth' => '2019-03-15'];

        Sanctum::actingAs(User::factory()->family()->create());
        $this->postJson('/api/v1/kids', $payload)->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/kids', $payload)->assertStatus(201);
    }

    public function test_partner_can_edit_any_kid_and_family_only_their_own(): void
    {
        $admin = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id]);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/kids/{$kid->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');

        $family = User::factory()->family()->create();
        Sanctum::actingAs($family);
        $this->putJson("/api/v1/kids/{$kid->id}", ['name' => 'Nope'])->assertForbidden();
        $this->assertTrue($family->can('update', Kid::factory()->create(['user_id' => $family->id])));
    }

    public function test_like_and_comment_follow_the_permissions(): void
    {
        $moment = Moment::factory()->create();

        Sanctum::actingAs(User::factory()->family(['can_like' => false, 'can_comment' => false])->create());
        $this->postJson("/api/v1/moments/{$moment->id}/like")->assertForbidden()->assertJsonPath('message', 'You are not allowed to like moments.');
        $this->deleteJson("/api/v1/moments/{$moment->id}/like")->assertForbidden();
        $this->postJson("/api/v1/moments/{$moment->id}/comments", ['content' => 'Hi'])
            ->assertForbidden()->assertJsonPath('message', 'You are not allowed to comment on moments.');
        $this->getJson("/api/v1/moments/{$moment->id}/comments")->assertOk();

        Sanctum::actingAs(User::factory()->family()->create());
        $this->postJson("/api/v1/moments/{$moment->id}/like")->assertStatus(201);
        $this->postJson("/api/v1/moments/{$moment->id}/comments", ['content' => 'Hi'])->assertStatus(201);
    }

    public function test_comment_can_be_deleted_by_its_author_or_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->family()->create();
        $moment = Moment::factory()->create();
        $comment = fn () => MomentInteraction::create(['user_id' => $author->id, 'moment_id' => $moment->id, 'type' => 'comment', 'content' => 'Hi']);

        $first = $comment();
        Sanctum::actingAs(User::factory()->create()); // a partner who is not the author
        $this->deleteJson("/api/v1/interactions/{$first->id}")->assertForbidden();

        Sanctum::actingAs($author);
        $this->deleteJson("/api/v1/interactions/{$first->id}")->assertOk();

        $second = $comment();
        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/interactions/{$second->id}")->assertOk();
    }

    public function test_moment_policy_uploader_kid_creator_and_managers(): void
    {
        $admin = User::factory()->admin()->create();
        $partner = User::factory()->create();
        $family = User::factory()->family()->create();
        $kidCreator = User::factory()->family()->create();
        $kid = Kid::factory()->create(['user_id' => $kidCreator->id]);

        $own = Moment::factory()->create(['kid_id' => $kid->id, 'user_id' => $family->id]);
        $other = Moment::factory()->create(['kid_id' => $kid->id, 'user_id' => $admin->id]);

        $this->assertTrue($family->can('delete', $own));
        $this->assertTrue($family->can('update', $own));
        $this->assertFalse($family->can('delete', $other));
        $this->assertTrue($kidCreator->can('delete', $other));
        $this->assertTrue($partner->can('delete', $other));
        $this->assertTrue($admin->can('delete', $own));
    }

    public function test_admin_gate_uses_the_role(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('admin'));
        $this->assertFalse(User::factory()->create()->can('admin'));
        $this->assertFalse(User::factory()->family()->create()->can('admin'));
    }
}
