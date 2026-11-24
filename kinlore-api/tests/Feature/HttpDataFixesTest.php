<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\MomentInteraction;
use App\Models\User;
use App\Services\InteractionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fixes from the 2026-11-24 audit of the HTTP and data layer.
 */
class HttpDataFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_moment_list_has_no_file_links_for_a_member_without_download(): void
    {
        $kid = Kid::factory()->create();
        $moment = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'video', 'hls_url' => 'media/k/v/hls/playlist.m3u8']);
        Sanctum::actingAs(User::factory()->family(['can_download' => false])->create());

        $response = $this->getJson('/api/v1/moments')->assertOk();

        foreach (['photo_url', 'medium_url', 'thumbnail_url', 'hls_url'] as $field) {
            $response->assertJsonMissingPath("data.0.{$field}");
        }
        $this->assertStringNotContainsString($moment->getRawOriginal('photo_url'), $response->getContent());
        $this->assertStringNotContainsString('signature=', $response->getContent());
    }

    public function test_photo_or_video_upload_without_a_file_is_a_validation_error(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach (['photo', 'video'] as $type) {
            $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'moment_type' => $type])
                ->assertStatus(422)->assertJsonValidationErrors('file');
        }
    }

    public function test_email_case_and_spaces_do_not_matter(): void
    {
        $invitation = Invitation::create([
            'token' => 'tok-case',
            'role' => 'family',
            'invited_by' => User::factory()->admin()->create()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Anna',
            'email' => ' Anna@X.com ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $invitation->token,
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'anna@x.com']);
        $this->postJson('/api/auth/login', ['email' => 'anna@x.com', 'password' => 'password123'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'ANNA@x.com', 'password' => 'password123'])->assertOk();
    }

    private function pushUpdate(array $row, ?int $lastPulledAt): array
    {
        return $this->postJson('/api/v1/sync/push', [
            'changes' => ['moments' => ['updated' => [$row]]],
            'lastPulledAt' => $lastPulledAt,
        ])->assertOk()->json('data.rejected');
    }

    public function test_push_rejects_an_edit_of_a_row_changed_on_the_server_after_the_last_pull(): void
    {
        $user = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'user_id' => $user->id, 'moment_type' => 'story', 'description' => 'Server']);
        $pulledAt = $moment->updated_at->getTimestampMs() - 5000;
        Sanctum::actingAs($user);

        $rejected = $this->pushUpdate(['id' => $moment->id, 'description' => 'Old offline edit'], $pulledAt);

        $this->assertSame([['table' => 'moments', 'id' => $moment->id, 'reason' => 'conflict']], $rejected);
        $this->assertSame('Server', $moment->fresh()->description);

        $this->assertSame([], $this->pushUpdate(['id' => $moment->id, 'description' => 'New edit'], $moment->updated_at->getTimestampMs() + 1000));
        $this->assertSame([], $this->pushUpdate(['id' => $moment->id, 'description' => 'No pull yet'], null));
        $this->assertSame('No pull yet', $moment->fresh()->description);
    }

    public function test_a_like_after_the_last_pull_does_not_reject_an_offline_edit(): void
    {
        $user = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'user_id' => $user->id, 'moment_type' => 'story', 'description' => 'Server']);
        $pulledAt = $moment->updated_at->getTimestampMs() + 1000;
        $this->travel(5)->seconds();
        $moment->interactions()->create(['user_id' => User::factory()->create()->id, 'type' => 'like']);
        $this->assertGreaterThan($pulledAt, $moment->fresh()->updated_at->getTimestampMs());
        Sanctum::actingAs($user);

        $this->assertSame([], $this->pushUpdate(['id' => $moment->id, 'description' => 'Offline edit'], $pulledAt));
        $this->assertSame('Offline edit', $moment->fresh()->description);
    }

    public function test_the_pushers_own_online_edit_is_no_conflict(): void
    {
        $user = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'user_id' => $user->id, 'moment_type' => 'story', 'description' => 'Server']);
        $pulledAt = $moment->updated_at->getTimestampMs() + 1000;
        $this->travel(5)->seconds();
        Sanctum::actingAs($user);
        $this->putJson("/api/v1/moments/{$moment->id}", ['description' => 'Online edit'])->assertOk();

        $this->assertSame([], $this->pushUpdate(['id' => $moment->id, 'description' => 'Offline edit'], $pulledAt));
        $this->assertSame('Offline edit', $moment->fresh()->description);

        // Another member's edit after the pull still conflicts.
        $this->travel(5)->seconds();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->putJson("/api/v1/moments/{$moment->id}", ['description' => 'Partner edit'])->assertOk();
        Sanctum::actingAs($user);
        $rejected = $this->pushUpdate(['id' => $moment->id, 'description' => 'Late offline edit'], $pulledAt + 6000);
        $this->assertSame('conflict', $rejected[0]['reason'] ?? null);
    }

    public function test_push_reports_a_server_failure_as_error(): void
    {
        $user = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'user_id' => $user->id, 'moment_type' => 'story', 'description' => 'D']);
        Moment::saving(fn () => throw new \RuntimeException('database down'));
        Sanctum::actingAs($user);

        $rejected = $this->pushUpdate(['id' => $moment->id, 'description' => 'E'], null);

        $this->assertSame('error', $rejected[0]['reason']);
    }

    public function test_a_like_is_stored_once(): void
    {
        $user = User::factory()->create();
        $moment = Moment::factory()->create();
        $service = app(InteractionService::class);

        $this->assertNotNull($service->like($user, $moment));
        $this->assertNull($service->like($user, $moment), 'a second like is "already liked", not a new row');
        $this->assertSame(1, $moment->likes()->count());

        $this->expectException(UniqueConstraintViolationException::class);
        MomentInteraction::create(['user_id' => $user->id, 'moment_id' => $moment->id, 'type' => 'like']);
    }

    public function test_removing_a_member_bumps_the_moments_they_liked(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $moment = Moment::factory()->create(['user_id' => $admin->id]);
        MomentInteraction::create(['user_id' => $member->id, 'moment_id' => $moment->id, 'type' => 'like']);
        $before = $moment->fresh()->updated_at;

        $this->travel(1)->minutes();
        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/members/{$member->id}")->assertOk();

        $this->assertTrue($moment->fresh()->updated_at->isAfter($before));
        $this->assertSame(0, $moment->likes()->count());
    }
}
