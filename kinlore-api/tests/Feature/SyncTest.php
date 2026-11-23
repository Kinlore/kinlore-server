<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MomentType;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pull_initial_sync(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->count(3)->create(['kid_id' => $kid->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => null,
            'schemaVersion' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'changes' => [
                'kids' => ['created', 'updated', 'deleted'],
                'moments' => ['created', 'updated', 'deleted'],
                'milestones' => ['created', 'updated', 'deleted'],
            ],
            'timestamp',
        ]);

        $this->assertCount(1, $response->json('changes.kids.created'));
        $this->assertCount(3, $response->json('changes.moments.created'));
    }

    public function test_user_can_pull_incremental_sync(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $lastPulledAt = now()->subHours(1)->timestamp * 1000;

        // Create moment after lastPulledAt
        Moment::factory()->create(['kid_id' => $kid->id]);

        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => $lastPulledAt,
            'schemaVersion' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('changes.moments.created')));
    }

    public function test_user_can_push_changes(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/push', [
            'changes' => [
                'kids' => [
                    'created' => [
                        [
                            'id' => '123e4567-e89b-12d3-a456-426614174000',
                            'name' => 'Emma',
                            'date_of_birth' => now()->subYears(5)->timestamp * 1000,
                            'avatar_color' => '#FF6B6B',
                        ],
                    ],
                    'updated' => [],
                    'deleted' => [],
                ],
                'moments' => [
                    'created' => [],
                    'updated' => [],
                    'deleted' => [],
                ],
            ],
            'lastPulledAt' => now()->timestamp * 1000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kids_created.0.id', '123e4567-e89b-12d3-a456-426614174000')
            ->assertJsonPath('data.rejected', []);
        $this->assertDatabaseHas('kids', [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Emma',
            'user_id' => $user->id,
        ]);
    }

    public function test_sync_pull_requires_schema_version(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => null,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['schemaVersion']);
    }

    public function test_unauthenticated_user_cannot_sync(): void
    {
        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => null,
            'schemaVersion' => 1,
        ]);

        $response->assertStatus(401);
    }

    private function push(array $kids = [], array $moments = [])
    {
        $empty = ['created' => [], 'updated' => [], 'deleted' => []];

        return $this->postJson('/api/v1/sync/push', [
            'changes' => ['kids' => $kids + $empty, 'moments' => $moments + $empty],
            'lastPulledAt' => now()->getTimestampMs(),
        ])->assertOk();
    }

    public function test_push_stores_kid_dates_as_calendar_dates_and_creates_moments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $kidId = '0d6a1c2e-1111-4a2b-9c3d-000000000001';
        $momentId = '0d6a1c2e-2222-4a2b-9c3d-000000000002';

        $response = $this->push(
            ['created' => [[
                'id' => $kidId,
                'name' => 'Mia',
                'date_of_birth' => Carbon::parse('2024-03-15', 'UTC')->getTimestampMs(),
                'due_date' => null,
                'avatar_color' => '#4ECDC4',
            ]]],
            ['created' => [[
                'id' => $momentId,
                'kid_id' => $kidId,
                'moment_type' => 'growth',
                'title' => null,
                'description' => 'First check-up',
                'milestone_id' => null,
                'exif_date' => Carbon::parse('2024-04-01 09:00:00', 'UTC')->getTimestampMs(),
                'height_cm' => 55.5,
                'weight_kg' => 4.2,
                'head_circumference_cm' => null,
            ]]],
        );

        $response->assertJsonPath('data.kids_created', [['local_id' => $kidId, 'id' => $kidId]])
            ->assertJsonPath('data.moments_created', [['local_id' => $momentId, 'id' => $momentId]])
            ->assertJsonPath('data.rejected', []);

        $this->assertSame('2024-03-15', Kid::find($kidId)->date_of_birth->toDateString());
        $moment = Moment::find($momentId);
        $this->assertSame(MomentType::Growth, $moment->moment_type);
        $this->assertSame($user->id, $moment->user_id);
        $this->assertSame(55.5, $moment->height_cm);
        $this->assertSame('2024-04-01 09:00:00', $moment->exif_date->toDateTimeString());
    }

    public function test_push_updates_and_deletes_moments(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $photo = Moment::factory()->create(['moment_type' => 'photo', 'title' => 'Old']);
        $story = Moment::factory()->create(['moment_type' => 'story']);

        $this->push([], [
            'updated' => [['id' => $photo->id, 'title' => 'New', 'height_cm' => 80, 'exif_date' => null]],
            'deleted' => [$story->id],
        ])->assertJsonPath('data.rejected', []);

        $photo->refresh();
        $this->assertSame('New', $photo->title);
        $this->assertNull($photo->height_cm); // photo rows accept only title, description, exif_date
        $this->assertNull($photo->exif_date);
        $this->assertSoftDeleted('moments', ['id' => $story->id]);
    }

    public function test_push_rejects_changes_the_user_may_not_make(): void
    {
        $kid = Kid::factory()->create();
        $moment = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'story']);
        Sanctum::actingAs(User::factory()->family(['can_add_content' => false])->create());
        $newKid = '0d6a1c2e-3333-4a2b-9c3d-000000000003';
        $newMoment = '0d6a1c2e-4444-4a2b-9c3d-000000000004';

        $response = $this->push(
            [
                'created' => [['id' => $newKid, 'name' => 'X', 'date_of_birth' => now()->getTimestampMs(), 'avatar_color' => '#FF6B6B']],
                'updated' => [['id' => $kid->id, 'name' => 'Renamed']],
                'deleted' => [$kid->id],
            ],
            [
                'created' => [['id' => $newMoment, 'kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'Hi']],
                'updated' => [['id' => $moment->id, 'title' => 'Hacked']],
                'deleted' => [$moment->id],
            ],
        );

        $response->assertJsonPath('data.kids_created', [])->assertJsonPath('data.moments_created', []);
        $rejected = collect($response->json('data.rejected'))->map(fn ($r) => "{$r['table']}:{$r['id']}:{$r['reason']}")->sort()->values()->all();
        $this->assertSame(collect([
            "kids:{$newKid}:forbidden",
            "kids:{$kid->id}:forbidden",
            "kids:{$kid->id}:forbidden",
            "moments:{$newMoment}:forbidden",
            "moments:{$moment->id}:forbidden",
            "moments:{$moment->id}:forbidden",
        ])->sort()->values()->all(), $rejected);

        $this->assertDatabaseMissing('kids', ['id' => $newKid]);
        $this->assertDatabaseMissing('moments', ['id' => $newMoment]);
        $this->assertNotSoftDeleted('kids', ['id' => $kid->id]);
        $this->assertNotSoftDeleted('moments', ['id' => $moment->id]);
    }

    public function test_family_user_with_add_content_can_push_a_story(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->family()->create());
        $id = '0d6a1c2e-5555-4a2b-9c3d-000000000005';

        $this->push([], ['created' => [['id' => $id, 'kid_id' => $kid->id, 'moment_type' => 'story', 'title' => 'T', 'description' => 'D']]])
            ->assertJsonPath('data.moments_created.0.id', $id)
            ->assertJsonPath('data.rejected', []);

        $this->push([], ['created' => [['id' => '0d6a1c2e-6666-4a2b-9c3d-000000000006', 'kid_id' => $kid->id, 'moment_type' => 'photo']]])
            ->assertJsonPath('data.rejected.0.reason', 'invalid');
    }

    public function test_pull_returns_contract_fields_in_milliseconds(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $kid = Kid::factory()->create(['date_of_birth' => '2024-03-15', 'due_date' => '2024-03-20']);
        $moment = Moment::factory()->create(['kid_id' => $kid->id, 'user_id' => $user->id, 'exif_date' => null]);
        $moment->interactions()->create(['user_id' => $user->id, 'type' => 'like']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => null, 'schemaVersion' => 2])->assertOk();

        $kidRow = $response->json('changes.kids.created.0');
        $this->assertSame(Carbon::parse('2024-03-15', 'UTC')->getTimestampMs(), $kidRow['date_of_birth']);
        $this->assertSame(Carbon::parse('2024-03-20', 'UTC')->getTimestampMs(), $kidRow['due_date']);
        $this->assertSame($kid->user_id, $kidRow['user_id']);

        $row = $response->json('changes.moments.created.0');
        foreach (['orientation', 'likes_count', 'comments_count', 'is_liked', 'duration', 'video_status', 'uploader_name',
            'height_cm', 'weight_kg', 'head_circumference_cm', 'device_asset_id', 'user_id'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
        $this->assertNull($row['exif_date']);
        $this->assertSame(1, $row['likes_count']);
        $this->assertTrue($row['is_liked']);
        $this->assertSame('Ana', $row['uploader_name']);
        $this->assertGreaterThan(1_000_000_000_000, $response->json('timestamp'));
    }

    public function test_pull_lists_moments_of_a_deleted_kid_as_deleted(): void
    {
        $kid = Kid::factory()->create();
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);
        $this->travel(-1)->hours();
        $lastPulledAt = now()->getTimestampMs();
        $this->travelBack();
        $kid->delete();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => $lastPulledAt, 'schemaVersion' => 2])->assertOk();

        $this->assertSame([$kid->id], $response->json('changes.kids.deleted'));
        $this->assertSame([$moment->id], $response->json('changes.moments.deleted'));
        $this->assertSame([], $response->json('changes.moments.created'));
    }
}
