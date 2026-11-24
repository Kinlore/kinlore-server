<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessVideoJob;
use App\Models\Kid;
use App\Models\Media;
use App\Models\User;
use App\Services\MomentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('seaweedfs');
        Storage::fake('local');
    }

    public function test_corrupt_photo_leaves_no_media_row_and_no_file(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $file = UploadedFile::fake()->createWithContent('broken.jpg', 'not an image');

        try {
            app(MomentService::class)->create($user, $kid, ['moment_type' => 'photo'], $file);
            $this->fail('A corrupt photo must throw');
        } catch (\Throwable) {
        }

        $this->assertSame(0, Media::withTrashed()->count());
        $this->assertSame([], Storage::disk('seaweedfs')->allFiles());
    }

    public function test_heic_photo_keeps_its_original_and_gets_jpeg_previews(): void
    {
        if (trim((string) shell_exec('command -v heif-dec')) === '') {
            $this->markTestSkipped('heif-dec (libheif) is not installed');
        }
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $bytes = file_get_contents(base_path('tests/fixtures/photo.heic'));
        $file = UploadedFile::fake()->createWithContent('IMG_0001.HEIC', $bytes);

        app(MomentService::class)->create($user, $kid, ['moment_type' => 'photo'], $file);

        $media = Media::sole();
        $this->assertSame(Media::STATUS_COMPLETED, $media->status);
        $this->assertSame($bytes, Storage::disk('seaweedfs')->get($media->file_path));
        $this->assertNotFalse(imagecreatefromstring(Storage::disk('seaweedfs')->get($media->thumbnail_url)));
    }

    public function test_failed_video_marks_media_failed_and_removes_local_copy(): void
    {
        $media = $this->videoMedia();
        Storage::disk('seaweedfs')->put($media->file_path, 'not a video');

        app(MomentService::class)->processVideoMoment($media);

        $this->assertSame(Media::STATUS_FAILED, $media->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_killed_video_job_removes_local_copy(): void
    {
        $media = $this->videoMedia();
        Storage::disk('local')->put("tmp/video_{$media->id}/original.mp4", 'partial');

        (new ProcessVideoJob($media->id))->failed(new \RuntimeException('timeout'));

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(Media::STATUS_FAILED, $media->fresh()->status);
    }

    private function videoMedia(): Media
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        return Media::create([
            'kid_id' => $kid->id,
            'user_id' => $user->id,
            'type' => Media::TYPE_VIDEO,
            'original_filename' => 'clip.mp4',
            'file_path' => "media/{$kid->id}/v/original.mp4",
            'mime_type' => 'video/mp4',
            'file_size' => 11,
            'status' => Media::STATUS_PROCESSING,
        ]);
    }
}
