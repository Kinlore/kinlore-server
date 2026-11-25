<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VideoStatus;
use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\User;
use App\Services\MomentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Tests\TestCase;

// The original photo or video is never lost, and no row or file is left without the other.
class MediaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Make the seaweedfs disk return false for every write whose path ends with $suffix. */
    private function failWritesEndingWith(string $suffix): void
    {
        $disk = Storage::disk('seaweedfs');
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->andReturnUsing(
            fn (string $path, $content, $options = []) => str_ends_with($path, $suffix) ? false : $disk->put($path, $content, $options)
        );
        Storage::set('seaweedfs', $mock);
    }

    public static function photoWrites(): array
    {
        return [['original.jpg'], ['medium.jpg'], ['thumbnail.jpg']];
    }

    #[DataProvider('photoWrites')]
    public function test_failed_photo_write_answers_an_error_and_leaves_nothing(string $suffix): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->failWritesEndingWith($suffix);

        $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'file' => UploadedFile::fake()->image('photo.jpg', 40, 30),
        ])->assertStatus(500);

        $this->assertSame(0, Media::withTrashed()->count());
        $this->assertSame(0, Moment::withTrashed()->count());
        $this->assertSame([], Storage::disk('seaweedfs')->allFiles());
    }

    public function test_failed_moment_insert_leaves_no_media_row_and_no_original(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        Moment::creating(fn () => throw new \RuntimeException('database down'));

        $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'file' => UploadedFile::fake()->image('photo.jpg', 40, 30),
        ])->assertStatus(500);

        $this->assertSame(0, Media::withTrashed()->count());
        $this->assertSame([], Storage::disk('seaweedfs')->allFiles());
    }

    public function test_same_file_uploaded_twice_returns_the_first_moment(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $file = UploadedFile::fake()->image('photo.jpg', 40, 30);
        $post = fn () => $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'file' => $file, 'device_asset_id' => 'asset-1']);

        $first = $post()->assertStatus(201)->json('data.moment.id');
        $this->assertSame($first, $post()->assertStatus(200)->json('data.moment.id'));

        $this->assertSame(1, Moment::count());
        $this->assertSame(1, Media::count());
    }

    public function test_the_same_file_from_another_member_returns_the_first_moment(): void
    {
        $kid = Kid::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 40, 30);

        Sanctum::actingAs(User::factory()->create());
        $first = $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'file' => $file, 'device_asset_id' => 'phone-a-1'])
            ->assertStatus(201)->json('data.moment.id');

        // Another phone has its own asset ID for the same photo
        Sanctum::actingAs(User::factory()->create());
        $second = $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'file' => $file, 'device_asset_id' => 'phone-b-7'])
            ->assertStatus(200)->json('data.moment.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Moment::count());
        $this->assertSame(1, Media::count());
    }

    public function test_the_same_file_for_another_child_or_after_a_delete_makes_a_new_moment(): void
    {
        [$anna, $ben] = Kid::factory()->count(2)->create();
        Sanctum::actingAs(User::factory()->create());
        $file = UploadedFile::fake()->image('photo.jpg', 40, 30);
        $post = fn (Kid $kid) => $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'file' => $file]);

        $first = $post($anna)->assertStatus(201)->json('data.moment.id');
        $post($ben)->assertStatus(201);

        Moment::findOrFail($first)->delete();
        $this->assertNotSame($first, $post($anna)->assertStatus(201)->json('data.moment.id'));
        $this->assertSame(2, Moment::count());
    }

    public function test_failed_heic_decode_leaves_no_temp_file(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'bad file', exitCode: 1)]);
        $user = User::factory()->create();
        $kid = Kid::factory()->create();
        $file = UploadedFile::fake()->createWithContent('IMG_0001.HEIC', file_get_contents(base_path('tests/fixtures/photo.heic')));
        $before = glob(sys_get_temp_dir().'/kinlore-heic-*');

        try {
            app(MomentService::class)->create($user, $kid, ['moment_type' => 'photo'], $file);
            $this->fail('A failed decode must throw');
        } catch (\Throwable) {
        }

        $this->assertSame($before, glob(sys_get_temp_dir().'/kinlore-heic-*'));
        $this->assertSame([], Storage::disk('seaweedfs')->allFiles());
    }

    public function test_deleting_a_failed_video_removes_every_file_the_job_uploaded(): void
    {
        $media = $this->videoMedia();
        $dir = dirname($media->file_path);
        $disk = Storage::disk('seaweedfs');
        foreach (['original.mp4', 'thumbnail.jpg', 'medium.jpg', 'hls/playlist.m3u8', 'hls/playlist_0.ts'] as $name) {
            $disk->put("{$dir}/{$name}", 'x');
        }
        $moment = Moment::factory()->create([
            'kid_id' => $media->kid_id, 'media_id' => $media->id, 'moment_type' => 'video', 'video_status' => 'failed',
            'photo_url' => $media->file_path, 'thumbnail_url' => null, 'medium_url' => null,
        ]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/v1/moments/{$moment->id}")->assertOk();

        $this->assertSame([], $disk->allFiles());
    }

    public function test_a_media_with_an_unexpected_path_never_empties_a_shared_folder(): void
    {
        $media = $this->videoMedia();
        $media->forceFill(['file_path' => 'media/stray.mp4'])->save();
        $disk = Storage::disk('seaweedfs');
        $disk->put('media/stray.mp4', 'x');
        $disk->put('media/other-kid/other-media/original.jpg', 'x');

        $this->assertFalse($media->deleteFiles());

        $this->assertTrue($disk->exists('media/other-kid/other-media/original.jpg'));
    }

    public function test_a_moment_deleted_through_sync_loses_its_files_after_the_push_commits(): void
    {
        $media = $this->videoMedia();
        $dir = dirname($media->file_path);
        $disk = Storage::disk('seaweedfs');
        $disk->put("{$dir}/original.mp4", 'x');
        $disk->put("{$dir}/hls/playlist_0.ts", 'x');
        $moment = Moment::factory()->create([
            'kid_id' => $media->kid_id, 'media_id' => $media->id, 'moment_type' => 'video', 'photo_url' => $media->file_path,
        ]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/sync/push', ['changes' => ['moments' => ['deleted' => [$moment->id]]], 'lastPulledAt' => null])
            ->assertOk()->assertJsonPath('data.rejected', []);

        $this->assertSoftDeleted('moments', ['id' => $moment->id]);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_force_deleting_a_media_removes_its_whole_directory(): void
    {
        $media = $this->videoMedia();
        $dir = dirname($media->file_path);
        Storage::disk('seaweedfs')->put("{$dir}/original.mp4", 'x');
        Storage::disk('seaweedfs')->put("{$dir}/hls/playlist_0.ts", 'x');

        $media->forceDelete();

        $this->assertSame([], Storage::disk('seaweedfs')->allFiles());
    }

    public function test_files_stay_when_the_moment_row_cannot_be_deleted(): void
    {
        $media = $this->videoMedia();
        Storage::disk('seaweedfs')->put($media->file_path, 'ORIGINAL');
        $moment = Moment::factory()->create(['kid_id' => $media->kid_id, 'media_id' => $media->id, 'photo_url' => $media->file_path]);
        Moment::deleting(fn () => throw new \RuntimeException('database down'));
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/v1/moments/{$moment->id}")->assertStatus(500);

        $this->assertNotSoftDeleted('moments', ['id' => $moment->id]);
        Storage::disk('seaweedfs')->assertExists($media->file_path);
    }

    public function test_a_file_delete_that_returns_false_is_logged(): void
    {
        $moment = Moment::factory()->create();
        $mock = Mockery::mock(Storage::disk('seaweedfs'))->makePartial();
        $mock->shouldReceive('delete')->andReturn(false);
        Storage::set('seaweedfs', $mock);
        Log::spy();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/v1/moments/{$moment->id}")->assertOk();

        $this->assertSoftDeleted('moments', ['id' => $moment->id]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'delete'))->once();
    }

    public static function videoWrites(): array
    {
        return [['thumbnail.jpg'], ['medium.jpg'], ['hls/playlist.m3u8']];
    }

    #[DataProvider('videoWrites')]
    public function test_failed_video_write_ends_as_failed(string $suffix): void
    {
        $media = $this->videoMediaWithMoment();
        $this->fakeFfmpeg($media);
        $this->failWritesEndingWith($suffix);

        app(MomentService::class)->processVideoMoment($media);

        $this->assertSame(Media::STATUS_FAILED, $media->fresh()->status);
        $this->assertSame(VideoStatus::Failed, $media->moments()->sole()->video_status);
    }

    public function test_failed_local_copy_for_ffmpeg_ends_as_failed(): void
    {
        $media = $this->videoMediaWithMoment();
        $this->fakeFfmpeg($media);
        $local = Mockery::mock(Storage::disk('local'))->makePartial();
        $local->shouldReceive('writeStream')->andReturn(false);
        Storage::set('local', $local);

        app(MomentService::class)->processVideoMoment($media);

        $this->assertSame(VideoStatus::Failed, $media->moments()->sole()->video_status);
    }

    public function test_video_with_fake_ffmpeg_ends_ready(): void
    {
        $media = $this->videoMediaWithMoment();
        $this->fakeFfmpeg($media);

        app(MomentService::class)->processVideoMoment($media);

        $this->assertSame(VideoStatus::Ready, $media->moments()->sole()->video_status);
        Storage::disk('seaweedfs')->assertExists(dirname($media->file_path).'/hls/playlist.m3u8');
    }

    // ffmpeg is not installed on every machine: the fake gives one frame and writes a one-file HLS stream.
    private function fakeFfmpeg(Media $media): void
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(64, 48));
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

    private function videoMediaWithMoment(): Media
    {
        $media = $this->videoMedia();
        Storage::disk('seaweedfs')->put($media->file_path, 'VIDEO');
        Moment::factory()->create(['kid_id' => $media->kid_id, 'media_id' => $media->id, 'moment_type' => 'video', 'video_status' => 'processing']);

        return $media;
    }

    private function videoMedia(): Media
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $id = (string) Str::uuid();

        return Media::create([
            'id' => $id,
            'kid_id' => $kid->id,
            'user_id' => $user->id,
            'type' => Media::TYPE_VIDEO,
            'original_filename' => 'clip.mp4',
            'file_path' => "media/{$kid->id}/{$id}/original.mp4",
            'mime_type' => 'video/mp4',
            'file_size' => 5,
            'status' => Media::STATUS_PROCESSING,
        ]);
    }
}
