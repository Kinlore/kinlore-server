<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60; // 1 minute backoff, exponential

    /**
     * The video ID to process.
     */
    protected string $videoId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('video-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $video = Video::find($this->videoId);

        if (!$video) {
            Log::error("ProcessVideoJob: Video not found", ['video_id' => $this->videoId]);
            return;
        }

        try {
            Log::info("ProcessVideoJob: Starting processing", ['video_id' => $this->videoId]);
            
            // Mark as processing
            $video->markAsProcessing();

            // Get the storage path
            $storagePath = $video->getRawStoragePath();
            
            // Download video to temp file for processing
            $tempDir = sys_get_temp_dir() . '/video_processing_' . $this->videoId;
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempInputFile = $tempDir . '/input_' . basename($storagePath);
            $fileContents = Storage::disk('seaweedfs')->get($storagePath);
            file_put_contents($tempInputFile, $fileContents);

            // Extract video duration using FFMpeg
            $ffmpeg = FFMpeg::fromDisk('local')
                ->open($tempInputFile);
            
            $duration = $ffmpeg->getDurationInSeconds();
            
            Log::info("ProcessVideoJob: Duration extracted", [
                'video_id' => $this->videoId,
                'duration' => $duration
            ]);

            // Generate thumbnail at 50% of duration
            $thumbnailPath = $this->generateThumbnail($tempInputFile, $duration, $tempDir);
            
            // Generate HLS stream
            $hlsPath = $this->generateHLS($tempInputFile, $tempDir);

            // Upload thumbnail to storage
            $thumbnailStoragePath = "videos/{$this->videoId}/thumbnail.jpg";
            Storage::disk('seaweedfs')->put(
                $thumbnailStoragePath,
                file_get_contents($thumbnailPath)
            );

            // Upload HLS files to storage
            $hlsStoragePath = $this->uploadHLSFiles($tempDir . '/hls', $this->videoId);

            // Mark as completed
            $video->markAsCompleted(
                $hlsStoragePath,
                $thumbnailStoragePath,
                $duration
            );

            Log::info("ProcessVideoJob: Processing completed", [
                'video_id' => $this->videoId,
                'hls_url' => $hlsStoragePath,
                'thumbnail_url' => $thumbnailStoragePath,
                'duration' => $duration
            ]);

            // Clean up temp files
            $this->cleanupTempDir($tempDir);

        } catch (\Exception $e) {
            Log::error("ProcessVideoJob: Processing failed", [
                'video_id' => $this->videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $video->markAsFailed($e->getMessage());

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Generate thumbnail at 50% of video duration
     */
    protected function generateThumbnail(string $inputFile, float $duration, string $tempDir): string
    {
        $thumbnailPath = $tempDir . '/thumbnail.jpg';
        $timestamp = max(1, $duration * 0.5); // 50% of duration, minimum 1 second

        $ffmpeg = FFMpeg::fromDisk('local')
            ->open($inputFile);

        $ffmpeg->getFrameFromSeconds($timestamp)
            ->export()
            ->toDisk('local')
            ->save($thumbnailPath);

        return $thumbnailPath;
    }

    /**
     * Generate HLS stream with multiple quality variants
     */
    protected function generateHLS(string $inputFile, string $tempDir): string
    {
        $hlsDir = $tempDir . '/hls';
        if (!file_exists($hlsDir)) {
            mkdir($hlsDir, 0755, true);
        }

        $playlistPath = $hlsDir . '/playlist.m3u8';

        // Get configured quality variants
        $variants = explode(',', env('VIDEO_QUALITY_VARIANTS', '720,480,240'));

        $ffmpeg = FFMpeg::fromDisk('local')
            ->open($inputFile);

        // Create HLS export with multiple bitrates
        $hlsExport = $ffmpeg->exportForHLS();

        foreach ($variants as $height) {
            $height = (int) trim($height);
            $bitrate = $this->getBitrateForHeight($height);
            
            $hlsExport->addFormat(
                (new \FFMpeg\Format\Video\X264())
                    ->setKiloBitrate($bitrate)
                    ->setAudioKiloBitrate(128),
                function ($media) use ($height) {
                    $media->scale(-2, $height);
                }
            );
        }

        $hlsExport
            ->setSegmentLength(10)
            ->toDisk('local')
            ->save($playlistPath);

        return $playlistPath;
    }

    /**
     * Get recommended bitrate for video height
     */
    protected function getBitrateForHeight(int $height): int
    {
        return match (true) {
            $height >= 1080 => 5000,
            $height >= 720 => 2500,
            $height >= 480 => 1000,
            $height >= 360 => 600,
            default => 400,
        };
    }

    /**
     * Upload HLS files to storage
     */
    protected function uploadHLSFiles(string $hlsDir, string $videoId): string
    {
        $files = glob($hlsDir . '/*');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $storagePath = "videos/{$videoId}/hls/{$filename}";
            
            Storage::disk('seaweedfs')->put(
                $storagePath,
                file_get_contents($file)
            );
        }

        return "videos/{$videoId}/hls/playlist.m3u8";
    }

    /**
     * Clean up temporary directory
     */
    protected function cleanupTempDir(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessVideoJob: Job permanently failed", [
            'video_id' => $this->videoId,
            'error' => $exception->getMessage()
        ]);

        $video = Video::find($this->videoId);
        if ($video) {
            $video->markAsFailed('Processing failed after ' . $this->tries . ' attempts: ' . $exception->getMessage());
        }
    }
}
