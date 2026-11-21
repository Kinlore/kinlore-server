<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Export::find($item['id'])->fullPath()));
        $photoPath = 'Mia/2025/2025-06-01_photo_' . substr($photo->id, 0, 8) . '.jpg';
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
