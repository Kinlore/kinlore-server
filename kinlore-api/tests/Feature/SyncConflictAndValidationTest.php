<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Milestone;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sync push fixes from the 2026-11-24 audit: conflict rule gaps and per-row validation.
 */
class SyncConflictAndValidationTest extends TestCase
{
    use RefreshDatabase;

    private function push(array $kids = [], array $moments = [], ?int $lastPulledAt = null): array
    {
        return $this->postJson('/api/v1/sync/push', [
            'changes' => ['kids' => $kids, 'moments' => $moments],
            'lastPulledAt' => $lastPulledAt,
        ])->assertOk()->json('data');
    }

    private function reasons(array $data): array
    {
        return collect($data['rejected'])->mapWithKeys(fn ($r) => [$r['id'] => $r['reason']])->all();
    }

    public function test_an_offline_kid_edit_conflicts_with_a_later_server_edit(): void
    {
        $kid = Kid::factory()->create(['name' => 'Server']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $data = $this->push(['updated' => [['id' => $kid->id, 'name' => 'Offline']]], [], $kid->updated_at->getTimestampMs() - 5000);

        $this->assertSame([$kid->id => 'conflict'], $this->reasons($data));
        $this->assertSame('Server', $kid->fresh()->name);
    }

    public function test_a_repeated_create_does_not_overwrite_another_members_later_edit(): void
    {
        $pusher = User::factory()->admin()->create();
        Sanctum::actingAs($pusher);
        $kidId = '0d6a1c2e-1111-4a2b-9c3d-0000000000a1';
        $momentId = '0d6a1c2e-2222-4a2b-9c3d-0000000000a2';
        $kidRow = ['id' => $kidId, 'name' => 'Mia', 'date_of_birth' => Carbon::parse('2024-03-15', 'UTC')->getTimestampMs()];
        $momentRow = ['id' => $momentId, 'kid_id' => $kidId, 'moment_type' => 'story', 'description' => 'First'];
        $this->push(['created' => [$kidRow]], ['created' => [$momentRow]]);
        $pulledAt = now()->getTimestampMs();

        // The answer was lost; meanwhile a partner edits both rows.
        $this->travel(5)->seconds();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->putJson("/api/v1/kids/{$kidId}", ['name' => 'Partner'])->assertOk();
        $this->putJson("/api/v1/moments/{$momentId}", ['description' => 'Partner'])->assertOk();

        Sanctum::actingAs($pusher);
        $data = $this->push(['created' => [$kidRow]], ['created' => [$momentRow]], $pulledAt);

        $this->assertSame([$kidId => 'conflict', $momentId => 'conflict'], $this->reasons($data));
        $this->assertSame('Partner', Kid::find($kidId)->name);
        $this->assertSame('Partner', Moment::find($momentId)->description);
    }

    public function test_an_edit_in_the_same_second_as_the_pull_start_is_a_conflict(): void
    {
        $pusher = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'moment_type' => 'story', 'description' => 'Server']);
        Carbon::setTestNow(Carbon::parse('2026-11-24 10:00:00.200'));
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->putJson("/api/v1/moments/{$moment->id}", ['description' => 'Partner'])->assertOk();

        Sanctum::actingAs($pusher);
        $pulledAt = Carbon::parse('2026-11-24 10:00:00.500')->getTimestampMs();
        $data = $this->push([], ['updated' => [['id' => $moment->id, 'description' => 'Offline']]], $pulledAt);

        $this->assertSame([$moment->id => 'conflict'], $this->reasons($data));
    }

    public function test_the_conflict_check_and_the_save_run_in_one_transaction(): void
    {
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'moment_type' => 'story', 'description' => 'D']);
        Sanctum::actingAs(User::factory()->admin()->create());
        $outer = DB::transactionLevel();
        $levels = [];
        Moment::saving(function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $this->push([], ['updated' => [['id' => $moment->id, 'description' => 'E']]]);

        $this->assertSame([$outer + 1], $levels);
    }

    public function test_a_like_on_a_moment_from_before_edited_at_does_not_reject_an_offline_edit(): void
    {
        $pusher = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['kid_id' => Kid::factory()->create()->id, 'moment_type' => 'story', 'description' => 'Server']);
        DB::table('moments')->update(['edited_at' => null]); // a row from before 1.1.0
        (require database_path('migrations/2026_11_26_000001_fill_moments_edited_at.php'))->up();
        $this->assertSame($moment->updated_at->toDateTimeString(), $moment->fresh()->edited_at?->toDateTimeString());

        $pulledAt = $moment->updated_at->getTimestampMs() + 1000;
        $this->travel(5)->seconds();
        $moment->interactions()->create(['user_id' => User::factory()->create()->id, 'type' => 'like']);
        Sanctum::actingAs($pusher);

        $data = $this->push([], ['updated' => [['id' => $moment->id, 'description' => 'Offline']]], $pulledAt);

        $this->assertSame([], $data['rejected']);
        $this->assertSame('Offline', $moment->fresh()->description);
    }

    public function test_a_row_with_an_id_that_is_not_a_uuid_is_invalid(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());
        $goodId = '0d6a1c2e-3333-4a2b-9c3d-0000000000a3';

        $data = $this->push(
            ['created' => [['id' => 'kid-1', 'name' => 'Mia', 'date_of_birth' => now()->getTimestampMs()]]],
            ['created' => [
                ['id' => 'moment-1', 'kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'D'],
                ['id' => $goodId, 'kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'D'],
            ], 'deleted' => ['moment-2']],
        );

        $this->assertSame(['kid-1' => 'invalid', 'moment-1' => 'invalid', 'moment-2' => 'invalid'], $this->reasons($data));
        $this->assertSame([['local_id' => $goodId, 'id' => $goodId]], $data['moments_created']);
        $this->assertDatabaseMissing('kids', ['id' => 'kid-1']);
        $this->assertDatabaseMissing('moments', ['id' => 'moment-1']);
    }

    public function test_an_unknown_milestone_is_invalid(): void
    {
        $kid = Kid::factory()->create();
        $milestone = Milestone::query()->value('id') ?? Milestone::create(['id' => 'first_smile', 'title' => 'First smile', 'stage_key' => 's', 'stage_label' => 'S', 'sort_order' => 1])->id;
        Sanctum::actingAs(User::factory()->admin()->create());
        $row = fn (string $id, string $milestoneId) => ['id' => $id, 'kid_id' => $kid->id, 'moment_type' => 'milestone', 'milestone_id' => $milestoneId];

        $data = $this->push([], ['created' => [
            $row('0d6a1c2e-4444-4a2b-9c3d-0000000000a4', 'no_such_milestone'),
            $row('0d6a1c2e-5555-4a2b-9c3d-0000000000a5', $milestone),
        ]]);

        $this->assertSame(['0d6a1c2e-4444-4a2b-9c3d-0000000000a4' => 'invalid'], $this->reasons($data));
        $this->assertDatabaseHas('moments', ['id' => '0d6a1c2e-5555-4a2b-9c3d-0000000000a5', 'milestone_id' => $milestone]);
    }

    public function test_rows_over_the_rest_limits_are_invalid(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());
        $dob = now()->getTimestampMs();
        $story = fn (string $id, array $values) => $values + ['id' => $id, 'kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'D'];

        $data = $this->push(
            ['created' => [
                ['id' => '0d6a1c2e-0000-4a2b-9c3d-0000000000b1', 'name' => str_repeat('a', 256), 'date_of_birth' => $dob],
                ['id' => '0d6a1c2e-0000-4a2b-9c3d-0000000000b2', 'name' => 'Mia', 'date_of_birth' => $dob, 'avatar_color' => 'red'],
                ['id' => '0d6a1c2e-0000-4a2b-9c3d-0000000000b3', 'name' => str_repeat('a', 255), 'date_of_birth' => $dob, 'avatar_color' => '#4ecdc4'],
            ]],
            ['created' => [
                $story('0d6a1c2e-0000-4a2b-9c3d-0000000000c1', ['title' => str_repeat('a', 256)]),
                $story('0d6a1c2e-0000-4a2b-9c3d-0000000000c2', ['description' => str_repeat('a', 5001)]),
                $story('0d6a1c2e-0000-4a2b-9c3d-0000000000c3', ['kid_id' => 'kid-1']),
                $story('0d6a1c2e-0000-4a2b-9c3d-0000000000c4', ['title' => str_repeat('a', 255), 'description' => str_repeat('a', 5000)]),
            ]],
        );

        $this->assertSame([
            '0d6a1c2e-0000-4a2b-9c3d-0000000000b1' => 'invalid',
            '0d6a1c2e-0000-4a2b-9c3d-0000000000b2' => 'invalid',
            '0d6a1c2e-0000-4a2b-9c3d-0000000000c1' => 'invalid',
            '0d6a1c2e-0000-4a2b-9c3d-0000000000c2' => 'invalid',
            '0d6a1c2e-0000-4a2b-9c3d-0000000000c3' => 'invalid',
        ], $this->reasons($data));
        $this->assertCount(1, $data['kids_created']);
        $this->assertCount(1, $data['moments_created']);
    }
}
