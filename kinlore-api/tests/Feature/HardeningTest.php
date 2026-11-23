<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExportStatus;
use App\Jobs\BuildExportJob;
use App\Models\Export;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Fixes from the final review of the contract v2 work. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function push(array $moments): array
    {
        return $this->postJson('/api/v1/sync/push', [
            'lastPulledAt' => now()->getTimestampMs(),
            'changes' => [
                'kids' => ['created' => [], 'updated' => [], 'deleted' => []],
                'moments' => ['created' => $moments, 'updated' => [], 'deleted' => []],
            ],
        ])->assertOk()->json('data');
    }

    public function test_sync_push_applies_the_rest_rules_for_story_and_growth(): void
    {
        $admin = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);

        $emptyStory = (string) Str::uuid();
        $growthNoMeasure = (string) Str::uuid();
        $growthOk = (string) Str::uuid();

        $data = $this->push([
            ['id' => $emptyStory, 'kid_id' => $kid->id, 'moment_type' => 'story', 'description' => ''],
            ['id' => $growthNoMeasure, 'kid_id' => $kid->id, 'moment_type' => 'growth', 'exif_date' => now()->getTimestampMs()],
            ['id' => $growthOk, 'kid_id' => $kid->id, 'moment_type' => 'growth', 'exif_date' => now()->getTimestampMs(), 'weight_kg' => 12.5],
        ]);

        $rejected = collect($data['rejected'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$emptyStory, $growthNoMeasure], $rejected);
        $this->assertDatabaseHas('moments', ['id' => $growthOk]);
        $this->assertDatabaseMissing('moments', ['id' => $emptyStory]);
    }

    public function test_like_and_unlike_bump_the_moment_for_sync(): void
    {
        $admin = User::factory()->admin()->create();
        $moment = Moment::factory()->create(['moment_type' => 'story']);
        $moment->timestamps = false;
        $moment->forceFill(['updated_at' => now()->subDay()])->save();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/moments/{$moment->id}/like")->assertSuccessful();
        $afterLike = $moment->fresh()->updated_at;
        $this->assertTrue($afterLike->greaterThan(now()->subMinute()));

        $moment->forceFill(['updated_at' => now()->subDay()])->save();
        $this->deleteJson("/api/v1/moments/{$moment->id}/like")->assertSuccessful();
        $this->assertTrue($moment->fresh()->updated_at->greaterThan(now()->subMinute()));
    }

    public function test_a_stuck_export_does_not_block_a_new_one(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $stuck = Export::create(['user_id' => $admin->id, 'status' => ExportStatus::Processing]);
        Export::whereKey($stuck->id)->update(['updated_at' => now()->subHours(7)]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/exports')->assertStatus(202);
        $this->assertSame(ExportStatus::Failed, $stuck->fresh()->status);
        Queue::assertPushed(BuildExportJob::class);
    }

    public function test_a_running_export_still_blocks_a_second_one(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        Export::create(['user_id' => $admin->id, 'status' => ExportStatus::Processing]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/exports')->assertStatus(409);
    }

    public function test_export_folder_names_cannot_leave_the_archive_root(): void
    {
        $method = new \ReflectionMethod(BuildExportJob::class, 'folderName');
        $job = (new \ReflectionClass(BuildExportJob::class))->newInstanceWithoutConstructor();

        foreach (['..' => 'Kid', '.' => 'Kid', '../evil' => '-evil', 'Mia' => 'Mia'] as $input => $expected) {
            $folders = [];
            $kid = new Kid(['name' => $input]);
            $kid->id = (string) Str::uuid();
            $this->assertSame($expected, $method->invokeArgs($job, [$kid, &$folders]), "name {$input}");
        }
    }

    public function test_image_and_hls_routes_skip_the_per_minute_api_limit(): void
    {
        foreach (['moments.hls.file', 'moments.hls.master'] as $name) {
            $this->assertContains('throttle:api', Route::getRoutes()->getByName($name)->excludedMiddleware());
        }

        $thumbnail = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/moments/{moment}/file/{type}');
        $this->assertContains('throttle:api', $thumbnail->excludedMiddleware());
    }
}
