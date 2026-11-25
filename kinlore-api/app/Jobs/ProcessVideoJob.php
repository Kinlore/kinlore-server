<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Media;
use App\Services\MomentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Makes the HLS stream, thumbnail and medium image of an uploaded video,
 * then sets the moment's video_status to ready or failed (docs/reference/api.md section 6).
 */
// shortcut: no retry, ffmpeg failures are almost always bad input; add retries if storage errors show up
#[Queue('video-processing')]
#[Tries(1)]
#[Timeout(1800)] // 30 minutes
class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $mediaId) {}

    public function handle(MomentService $moments): void
    {
        $media = Media::find($this->mediaId);

        if (! $media) {
            Log::error('ProcessVideoJob: Media not found', ['media_id' => $this->mediaId]);

            return;
        }

        $moments->processVideoMoment($media);
    }

    // Called when the worker kills the job (timeout) or it throws.
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessVideoJob: Job failed', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);

        $media = Media::find($this->mediaId);
        $media?->markAsFailed($exception->getMessage());
        $media?->moments()->update(['video_status' => VideoStatus::Failed]);
        // A killed worker never reaches the cleanup in MomentService
        Storage::disk('local')->deleteDirectory("tmp/video_{$this->mediaId}");
    }
}
