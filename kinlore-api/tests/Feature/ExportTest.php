<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Export;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('seaweedfs');
    }

    protected function tearDown(): void
    {
        foreach (Export::all() as $export) {
            @unlink($export->fullPath() ?? '');
        }
        parent::tearDown();
    }

    public function test_photos_made_close_together_on_one_day_all_reach_the_zip(): void
    {
        $admin = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['name' => 'Mia']);
        // UUIDv7 ids made in the same millisecond share their first characters
        $ids = ['01a1ff89-d65a-70d8-a0a7-b66a09e98600', '01a1ff89-d907-734b-9d1f-6a085c675c5c'];
        foreach ($ids as $i => $id) {
            $m = Moment::factory()->make(['kid_id' => $kid->id, 'moment_type' => 'photo', 'exif_date' => '2026-11-21 10:00:00', 'photo_url' => "media/k/{$i}/original.jpg"]);
            $m->id = $id;
            $m->save();
            Storage::disk('seaweedfs')->put("media/k/{$i}/original.jpg", "PHOTO-{$i}");
        }
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/exports')->assertStatus(202);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Export::latest()->first()->fullPath()));
        $photos = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->filter(fn ($n) => str_ends_with($n, '.jpg'));
        $this->assertCount(2, $photos, 'both photos must be in the ZIP');
        $zip->close();
    }

    public function test_admin_builds_zip_and_downloads_it_with_signed_url(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        $kid = Kid::factory()->create(['name' => 'Mia']);
        $photo = Moment::factory()->create([
            'kid_id' => $kid->id, 'moment_type' => 'photo', 'exif_date' => '2025-06-01 10:00:00',
            'photo_url' => 'media/k/p/original.jpg',
        ]);
        Storage::disk('seaweedfs')->put('media/k/p/original.jpg', 'JPEG-BYTES');
        $story = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'story', 'title' => 'Story']);
        $story->interactions()->create(['user_id' => $admin->id, 'type' => 'comment', 'content' => 'Lovely']);

        Sanctum::actingAs($admin);

        // The sync queue builds the ZIP inside the request
        $this->postJson('/api/v1/exports')->assertStatus(202)->assertJsonPath('data.status', 'ready');

        $item = $this->getJson('/api/v1/exports')->assertOk()->json('data.0');
        $this->assertSame('ready', $item['status']);
        $this->assertGreaterThan(0, $item['size_bytes']);
        $this->assertNotNull($item['download_url']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Export::find($item['id'])->fullPath()));
        $photoPath = 'Mia/2025/2025-06-01_photo_'.substr(str_replace('-', '', $photo->id), -12).'.jpg';
        $this->assertSame('JPEG-BYTES', $zip->getFromName($photoPath));
        $memories = json_decode($zip->getFromName('Mia/memories.json'), true);
        $this->assertCount(2, $memories);
        $storyRow = collect($memories)->firstWhere('id', $story->id);
        $this->assertSame('Story', $storyRow['title']);
        $this->assertSame([['author' => 'Admin', 'content' => 'Lovely']], array_map(fn ($c) => array_intersect_key($c, ['author' => 1, 'content' => 1]), $storyRow['comments']));
        $root = json_decode($zip->getFromName('kinlore-export.json'), true);
        $this->assertSame('Mia', $root['kids'][0]['name']);
        $zip->close();

        // Signed URL works without an auth header; the bare URL does not.
        $this->app['auth']->forgetGuards();
        $this->get($item['download_url'])->assertOk()->assertHeader('content-type', 'application/zip');
        $this->get("/api/v1/exports/{$item['id']}/download")->assertStatus(403);
    }

    public function test_only_one_export_runs_and_only_admins_export(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/exports')->assertStatus(403);

        $admin = User::factory()->admin()->create();
        Export::create(['user_id' => $admin->id, 'status' => 'processing']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/exports')->assertStatus(409);
    }

    public function test_truncated_original_fails_the_export(): void
    {
        $admin = User::factory()->admin()->create();
        $kid = Kid::factory()->create();
        Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'photo', 'photo_url' => 'media/k/p/original.jpg']);
        $disk = Storage::disk('seaweedfs');
        $disk->put('media/k/p/original.jpg', 'JPEG');
        // The store reports more bytes than the stream gives, as when a read is cut off
        Storage::set('seaweedfs', Mockery::mock($disk)->makePartial()->shouldReceive('size')->andReturn(100)->getMock());
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/v1/exports')->assertStatus(202)->json('data.id');

        $this->assertSame('failed', Export::find($id)->status->value);
        $this->assertFileDoesNotExist(storage_path("app/exports/{$id}.zip"));
    }

    public function test_unreadable_original_fails_the_export(): void
    {
        $kid = Kid::factory()->create();
        // The moment has a file path, but the store has no file there
        Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'photo', 'photo_url' => 'media/k/p/original.jpg']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/v1/exports')->assertStatus(202)->json('data.id');

        $this->assertSame('failed', Export::find($id)->status->value);
        $this->assertFileDoesNotExist(storage_path("app/exports/{$id}.zip"));
    }

    public function test_prune_removes_zips_no_export_references(): void
    {
        $stray = storage_path('app/exports/stray.zip');
        @mkdir(dirname($stray), 0755, true);
        file_put_contents($stray, 'partial');
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/v1/exports')->assertStatus(202)->json('data.id');

        $this->assertFileDoesNotExist($stray);
        $this->assertFileExists(Export::find($id)->fullPath());
    }

    public function test_keeps_three_newest_exports(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $ids = [];
        foreach (range(1, 4) as $i) {
            $this->travel($i)->minutes();
            $ids[] = $this->postJson('/api/v1/exports')->assertStatus(202)->json('data.id');
        }

        $this->assertSame(array_slice($ids, 1), Export::orderBy('created_at')->pluck('id')->all());
        $this->assertFileDoesNotExist(storage_path("app/exports/{$ids[0]}.zip"));
        foreach (Export::all() as $export) {
            $this->assertFileExists($export->fullPath());
        }
    }
}
