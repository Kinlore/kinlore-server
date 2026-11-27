<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\User;
use App\Services\MomentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Tests\TestCase;

// Every photo and video moment carries its display width and height (after rotation); other moments have null.
class MomentDimensionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('seaweedfs');
        Storage::fake('local');
    }

    public static function photos(): array
    {
        return [
            'portrait' => [300, 400, null, 300, 400],
            'landscape' => [400, 300, null, 400, 300],
            'EXIF orientation 6 (stored landscape, shown portrait)' => [400, 300, 6, 300, 400],
        ];
    }

    #[DataProvider('photos')]
    public function test_photo_upload_stores_the_display_size(int $w, int $h, ?int $orientation, int $width, int $height): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $file = UploadedFile::fake()->createWithContent('photo.jpg', $this->jpeg($w, $h, $orientation));

        $response = $this->post('/api/v1/moments', ['kid_id' => $kid->id, 'file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $response->assertJsonPath('data.moment.width', $width)->assertJsonPath('data.moment.height', $height);
        $this->assertSame([$width, $height], [Moment::sole()->width, Moment::sole()->height]);
    }

    public function test_photo_wider_than_the_medium_keeps_its_full_size(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create();
        $file = UploadedFile::fake()->createWithContent('big.jpg', $this->jpeg(1600, 900));

        $moment = app(MomentService::class)->create($user, $kid, ['moment_type' => 'photo'], $file)['moment'];

        $this->assertSame([1600, 900], [$moment->width, $moment->height]);
    }

    public function test_video_gets_the_size_of_its_decoded_frame(): void
    {
        // ffmpeg applies the rotation tag when it decodes, so a portrait phone video gives a portrait frame.
        $media = $this->videoMedia();
        $this->fakeFfmpeg($media, 48, 64);

        app(MomentService::class)->processVideoMoment($media);

        $moment = $media->moments()->sole();
        $this->assertSame([48, 64], [$moment->width, $moment->height]);
    }

    public function test_text_moments_have_no_size(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'First steps'])
            ->assertStatus(201)
            ->assertJsonPath('data.moment.width', null)
            ->assertJsonPath('data.moment.height', null);
    }

    public function test_moment_api_and_sync_pull_send_the_size(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create();
        $photo = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'photo', 'width' => 3024, 'height' => 4032]);
        $story = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'Hi']);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/moments/{$photo->id}")->assertOk()
            ->assertJsonPath('data.width', 3024)->assertJsonPath('data.height', 4032);

        $rows = collect($this->postJson('/api/v1/sync/pull', ['lastPulledAt' => null])->assertOk()
            ->json('changes.moments.created'))->keyBy('id');
        $this->assertSame([3024, 4032], [$rows[$photo->id]['width'], $rows[$photo->id]['height']]);
        $this->assertArrayHasKey('width', $rows[$story->id]);
        $this->assertNull($rows[$story->id]['width']);
        $this->assertNull($rows[$story->id]['height']);
    }

    public function test_backfill_fills_from_the_medium_or_video_metadata_and_skips_unreadable(): void
    {
        $kid = Kid::factory()->create();
        $photo = $this->oldMoment($kid, 'photo', 'media/a/medium.jpg');
        Storage::disk('seaweedfs')->put('media/a/medium.jpg', $this->jpeg(900, 1200));
        $broken = $this->oldMoment($kid, 'photo', 'media/b/medium.jpg');
        Storage::disk('seaweedfs')->put('media/b/medium.jpg', 'not an image');
        $missing = $this->oldMoment($kid, 'photo', 'media/c/medium.jpg');
        $video = $this->oldMoment($kid, 'video', 'media/d/medium.jpg', ['video_status' => 'ready']);
        $video->media->update(['video_metadata' => ['duration' => 3, 'width' => 1080, 'height' => 1920]]);
        $story = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'story', 'description' => 'Hi']);

        $this->artisan('kinlore:media-dimensions', ['--dry-run' => true])
            ->expectsOutputToContain('Would fill 2 moments. Cannot read 2.')->assertSuccessful();
        $this->assertNull($photo->fresh()->width);

        $this->artisan('kinlore:media-dimensions')->expectsOutputToContain('Filled 2 moments. Cannot read 2.')->assertSuccessful();

        $this->assertSame([900, 1200], [$photo->fresh()->width, $photo->fresh()->height]);
        $this->assertSame([1080, 1920], [$video->fresh()->width, $video->fresh()->height]);
        $this->assertNull($broken->fresh()->width);
        $this->assertNull($missing->fresh()->width);
        $this->assertNull($story->fresh()->width);

        // A second run changes nothing it filled before.
        $this->artisan('kinlore:media-dimensions')->expectsOutputToContain('Filled 0 moments. Cannot read 2.')->assertSuccessful();
    }

    public function test_a_backfilled_moment_reaches_the_next_sync_pull_without_a_conflict(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create();
        $photo = $this->oldMoment($kid, 'photo', 'media/a/medium.jpg');
        Storage::disk('seaweedfs')->put('media/a/medium.jpg', $this->jpeg(1200, 800));
        $editedAt = $photo->fresh()->edited_at;
        $lastPulledAt = now()->getTimestampMs();
        $this->travel(5)->seconds();

        $this->artisan('kinlore:media-dimensions')->assertSuccessful();

        $this->assertEquals($editedAt, $photo->fresh()->edited_at);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => $lastPulledAt])->assertOk()
            ->assertJsonPath('changes.moments.updated.0.id', $photo->id)
            ->assertJsonPath('changes.moments.updated.0.width', 1200)
            ->assertJsonPath('changes.moments.updated.0.height', 800);
    }

    /** A photo or video moment from before this update: no size, made an hour ago. */
    private function oldMoment(Kid $kid, string $type, string $mediumPath, array $extra = []): Moment
    {
        $this->travel(-1)->hours();
        $media = Media::create([
            'kid_id' => $kid->id,
            'user_id' => $kid->user_id,
            'type' => $type,
            'original_filename' => 'x',
            'file_path' => dirname($mediumPath).'/original.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1,
            'status' => Media::STATUS_COMPLETED,
        ]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id, 'media_id' => $media->id, 'moment_type' => $type,
            'medium_url' => $mediumPath, 'width' => null, 'height' => null] + $extra);
        $this->travelBack();

        return $moment;
    }

    /** A JPEG of $width x $height pixels, with an EXIF Orientation tag when $orientation is given. */
    private function jpeg(int $width, int $height, ?int $orientation = null): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));
        $jpeg = ob_get_clean();
        if ($orientation === null) {
            return $jpeg;
        }

        // Big-endian TIFF with one IFD entry: tag 0x0112 (Orientation), type SHORT, count 1
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00".$tiff;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }

    // ffmpeg is not installed on every machine: the fake gives one frame and writes a one-file HLS stream.
    private function fakeFfmpeg(Media $media, int $width, int $height): void
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));
        $frame = ob_get_clean();

        $hls = Mockery::mock();
        $hls->shouldReceive('addFormat', 'setSegmentLength', 'toDisk')->andReturnSelf();
        $hls->shouldReceive('save')->andReturnUsing(fn () => Storage::disk('local')->put("tmp/video_{$media->id}/hls/playlist.m3u8", '#EXTM3U'));
        $video = Mockery::mock();
        $video->shouldReceive('getDurationInSeconds')->andReturn(1);
        $video->shouldReceive('getFrameFromSeconds->export->getFrameContents')->andReturn($frame);
        $video->shouldReceive('exportForHLS')->andReturn($hls);
        FFMpeg::shouldReceive('fromDisk->open')->andReturn($video);
    }

    private function videoMedia(): Media
    {
        $kid = Kid::factory()->create();
        $id = (string) Str::uuid();
        $media = Media::create([
            'id' => $id,
            'kid_id' => $kid->id,
            'user_id' => $kid->user_id,
            'type' => Media::TYPE_VIDEO,
            'original_filename' => 'clip.mov',
            'file_path' => "media/{$kid->id}/{$id}/original.mov",
            'mime_type' => 'video/quicktime',
            'file_size' => 5,
            'status' => Media::STATUS_PROCESSING,
        ]);
        Storage::disk('seaweedfs')->put($media->file_path, 'VIDEO');
        Moment::factory()->create(['kid_id' => $kid->id, 'media_id' => $media->id, 'moment_type' => 'video', 'video_status' => 'processing']);

        return $media;
    }
}
