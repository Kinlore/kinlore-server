<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Media;
use App\Services\MomentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Makes the HLS stream, thumbnail and medium image of an uploaded video,
 * then sets the moment's video_status to ready or failed (docs/API_CONTRACT.md section 6).
 */
class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // shortcut: no retry, ffmpeg failures are almost always bad input; add retries if storage errors show up
    public int $tries = 1;

    public int $timeout = 1800; // 30 minutes

    public function __construct(public string $mediaId)
    {
        $this->onQueue('video-processing');
    }

    public function handle(MomentService $moments): void
    {
        $media = Media::find($this->mediaId);

        if (!$media) {
            Log::error('ProcessVideoJob: Media not found', ['media_id' => $this->mediaId]);
            return;
        }

        $moments->processVideoMoment($media);
    }

    /**
     * Called when the worker kills the job (timeout) or it throws.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessVideoJob: Job failed', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);

        $media = Media::find($this->mediaId);
        $media?->markAsFailed($exception->getMessage());
        $media?->moments()->update(['video_status' => VideoStatus::Failed]);
    }
}
