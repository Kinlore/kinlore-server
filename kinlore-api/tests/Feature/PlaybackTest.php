<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VideoStatus;
use App\Jobs\ProcessVideoJob;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaybackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('seaweedfs');
    }

    private function videoMoment(): Moment
    {
        $disk = Storage::disk('seaweedfs');
        $disk->put('media/k/v/hls/playlist.m3u8', "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=2500000\nplaylist_0_2500.m3u8\n");
        $disk->put('media/k/v/hls/playlist_0_2500.m3u8', "#EXTM3U\n#EXTINF:10.0,\nplaylist_0_2500_00000.ts\n#EXT-X-ENDLIST\n");
        $disk->put('media/k/v/hls/playlist_0_2500_00000.ts', 'TS-BYTES');

        return Moment::factory()->create([
            'moment_type' => 'video',
            'hls_url' => 'media/k/v/hls/playlist.m3u8',
            'video_status' => 'ready',
        ]);
    }

    public function test_playback_returns_signed_url_and_playlists_are_signed(): void
    {
        $moment = $this->videoMoment();
        Sanctum::actingAs(User::factory()->family(['can_download' => false])->create());

        $data = $this->getJson("/api/v1/moments/{$moment->id}/playback")->assertOk()->json('data');
        $this->assertStringContainsString('signature=', $data['hls_url']);
        $this->assertEqualsWithDelta(now()->addHours(6)->timestamp, strtotime($data['expires_at']), 5);

        $this->app['auth']->forgetGuards();

        $master = $this->get($data['hls_url'])->assertOk()->getContent();
        $variantUrl = collect(explode("\n", $master))->first(fn ($l) => str_contains($l, 'playlist_0_2500.m3u8'));
        $this->assertStringContainsString('signature=', $variantUrl);
        parse_str(parse_url($data['hls_url'], PHP_URL_QUERY), $query);
        $this->assertStringContainsString('expires='.$query['expires'], $variantUrl); // same expiry as the master

        $variant = $this->get($variantUrl)->assertOk()->getContent();
        $segmentUrl = collect(explode("\n", $variant))->first(fn ($l) => str_contains($l, '.ts'));
        $this->assertSame('TS-BYTES', $this->get($segmentUrl)->assertOk()->getContent());
    }

    public function test_unsigned_or_expired_hls_requests_get_403(): void
    {
        $moment = $this->videoMoment();
        Sanctum::actingAs(User::factory()->create());
        $url = $this->getJson("/api/v1/moments/{$moment->id}/playback")->json('data.hls_url');
        $this->app['auth']->forgetGuards();

        $this->get("/api/v1/moments/{$moment->id}/file/hls")->assertStatus(403);
        $this->get("/api/v1/moments/{$moment->id}/file/playlist_0_2500.m3u8")->assertStatus(403);
        $this->get(str_replace('signature=', 'signature=x', $url))->assertStatus(403);

        $this->travel(7)->hours();
        $this->get($url)->assertStatus(403);
    }

    public function test_playback_of_video_not_ready_returns_409(): void
    {
        $moment = Moment::factory()->create(['moment_type' => 'video', 'hls_url' => null, 'video_status' => 'processing']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/moments/{$moment->id}/playback")->assertStatus(409);
    }

    public function test_video_upload_returns_at_once_with_processing_status(): void
    {
        Queue::fake();
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/v1/moments', [
            'kid_id' => $kid->id,
            'file' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.moment.moment_type', 'video')
            ->assertJsonPath('data.moment.video_status', 'processing');

        Queue::assertPushedOn('video-processing', ProcessVideoJob::class,
            fn (ProcessVideoJob $job) => $job->mediaId === $response->json('data.media.id'));
    }

    public function test_failed_video_processing_sets_failed_status(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        // Sync queue: the job runs inline on a file that is not a real video
        $response = $this->post('/api/v1/moments', [
            'kid_id' => $kid->id,
            'file' => UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertSame(VideoStatus::Failed, Moment::find($response->json('data.moment.id'))->video_status);
    }
}
