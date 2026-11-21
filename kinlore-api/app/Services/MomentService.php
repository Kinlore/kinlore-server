<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\PredefinedMilestone;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * Unified service for handling all moment types (photo, video, story, milestone).
 */
class MomentService
{
    private const PHOTO_MIMES = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/heic', 'image/heif',
    ];

    private const VIDEO_MIMES = [
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm',
    ];

    public function __construct(
        private FileStorageService $storage
    ) {}

    /**
     * Create a moment of any type (unified entry point).
     *
     * @param User $user
     * @param Kid $kid
     * @param array $data Request data
     * @param UploadedFile|null $file Uploaded file for photo/video
     * @return array{moment: Moment, media?: Media, story?: Story, milestone?: UserMilestone}
     */
    public function create(User $user, Kid $kid, array $data, ?UploadedFile $file = null): array
    {
        // Determine moment type
        $momentType = $data['moment_type'] ?? $this->detectMomentType($file);

        Log::info('Creating moment', [
            'type' => $momentType,
            'kid_id' => $kid->id,
            'has_file' => !is_null($file),
        ]);

        return match($momentType) {
            'photo', 'video' => $this->createMediaMoment($user, $kid, $file, $data, $momentType),
            'story' => $this->createStoryMoment($kid, $data),
            'milestone' => $this->createMilestoneMoment($user, $kid, $data),
            default => throw new \InvalidArgumentException("Invalid moment_type: {$momentType}"),
        };
    }

    /**
     * Detect moment type from uploaded file.
     */
    private function detectMomentType(?UploadedFile $file): string
    {
        if (!$file) {
            throw new \InvalidArgumentException('moment_type is required when no file is uploaded');
        }

        $mimeType = $file->getMimeType();

        if (in_array($mimeType, self::PHOTO_MIMES)) {
            return 'photo';
        }

        if (in_array($mimeType, self::VIDEO_MIMES)) {
            return 'video';
        }

        throw new \InvalidArgumentException(
            'Unsupported file type. Please upload an image (JPEG, PNG, HEIC) or video (MP4, MOV, WebM).'
        );
    }

    /**
     * Create a photo or video moment.
     *
     * @return array{moment: Moment, media: Media}
     */
    private function createMediaMoment(User $user, Kid $kid, UploadedFile $file, array $data, string $type): array
    {
        $mimeType = $file->getMimeType();
        $mediaId = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $basePath = "media/{$kid->id}/{$mediaId}";
        $originalPath = "{$basePath}/original.{$extension}";

        // Store original file
        $this->storage->storeFile($originalPath, $file);
        Log::info('Original file uploaded', ['path' => $originalPath]);

        // Build EXIF data for photos and videos
        $exifData = $this->buildExifData($data);

        // Create media record
        $media = Media::create([
            'id' => $mediaId,
            'kid_id' => $kid->id,
            'user_id' => $user->id,
            'type' => $type,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $originalPath,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'exif_data' => $exifData,
            'status' => Media::STATUS_PROCESSING,
            'device_asset_id' => $data['device_asset_id'] ?? null,
            'device_fingerprint' => $this->generateFingerprint($file),
            'processing_started_at' => now(),
        ]);

        // Process based on type
        if ($type === 'photo') {
            $this->processPhoto($media, $file, $basePath);
        } else {
            $this->processVideo($media);
        }

        // Create associated moment
        $media->refresh();
        $moment = Moment::create([
            'kid_id' => $kid->id,
            'media_id' => $media->id,
            'moment_type' => $type,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'photo_url' => $media->getRawFilePath(),
            'thumbnail_url' => $media->getRawThumbnailUrl(),
            'medium_url' => $media->getRawMediumUrl(),
            'hls_url' => $type === 'video' ? $media->getRawHlsUrl() : null,
            'file_size' => $media->file_size,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
            'device_asset_id' => $media->device_asset_id,
            'device_fingerprint' => $media->device_fingerprint,
            'exif_date' => $media->exif_date,
            'latitude' => $media->latitude,
            'longitude' => $media->longitude,
            'orientation' => $media->orientation,
            'camera_make' => $media->camera_make,
            'camera_model' => $media->camera_model,
            'synced_at' => now()->timestamp * 1000,
        ]);

        return ['moment' => $moment, 'media' => $media];
    }

    /**
     * Create a story moment.
     *
     * @return array{moment: Moment}
     */
    private function createStoryMoment(Kid $kid, array $data): array
    {
        $moment = Moment::create([
            'id' => Str::uuid()->toString(),
            'kid_id' => $kid->id,
            'moment_type' => 'story',
            'description' => $data['description'],
            // Metadata
            'exif_date' => $data['exif_date'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'camera_make' => $data['camera_make'] ?? null,
            'camera_model' => $data['camera_model'] ?? null,
            'orientation' => $data['orientation'] ?? null,
            'synced_at' => now()->timestamp * 1000,
        ]);

        Log::info('Story moment created', [
            'moment_id' => $moment->id,
        ]);

        return ['moment' => $moment];
    }

    /**
     * Create a milestone moment.
     *
     * @return array{moment: Moment}
     */
    private function createMilestoneMoment(User $user, Kid $kid, array $data): array
    {
        $moment = Moment::create([
            'id' => Str::uuid()->toString(),
            'kid_id' => $kid->id,
            'moment_type' => 'milestone',
            'milestone_id' => $data['milestone_id'],
            'description' => $data['description'] ?? null,
            // Metadata
            'exif_date' => $data['exif_date'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'camera_make' => $data['camera_make'] ?? null,
            'camera_model' => $data['camera_model'] ?? null,
            'orientation' => $data['orientation'] ?? null,
            'synced_at' => now()->timestamp * 1000,
        ]);

        Log::info('Milestone moment created', [
            'moment_id' => $moment->id,
            'milestone_id' => $data['milestone_id'],
        ]);

        return ['moment' => $moment];
    }

    /**
     * Process photo: generate thumbnail and medium sizes.
     */
    private function processPhoto(Media $media, UploadedFile $file, string $basePath): void
    {
        try {
            $thumbnailPath = "{$basePath}/thumbnail.jpg";
            $mediumPath = "{$basePath}/medium.jpg";

            // Generate medium (1200px width)
            $mediumImage = Image::read($file->getRealPath())
                ->orient()
                ->scaleDown(width: 1200)
                ->toJpeg(quality: 85);

            $this->storage->store($mediumPath, (string) $mediumImage);

            // Generate thumbnail (300x300 cover)
            $thumbnailImage = Image::read($file->getRealPath())
                ->orient()
                ->cover(300, 300)
                ->toJpeg(quality: 80);

            $this->storage->store($thumbnailPath, (string) $thumbnailImage);

            // Update media record
            $media->markAsCompleted([
                'thumbnail_url' => $thumbnailPath,
                'medium_url' => $mediumPath,
            ]);

        } catch (\Exception $e) {
            Log::error('Photo processing failed: ' . $e->getMessage());
            $media->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process video synchronously in foreground.
     * Extracts duration, generates thumbnail, and creates HLS stream.
     */
    private function processVideo(Media $media): void
    {
        $tempDir = null;
        
        try {
            Log::info('MomentService: Starting video processing', ['media_id' => $media->id]);

            // Get the storage path
            $storagePath = $media->getRawFilePath();
            
            // Download video to temp file for processing
            $tempDir = sys_get_temp_dir() . '/video_processing_' . $media->id;
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempInputFile = $tempDir . '/input_' . basename($storagePath);
            $fileContents = Storage::disk('seaweedfs')->get($storagePath);
            file_put_contents($tempInputFile, $fileContents);

            // Copy temp file to Laravel's local storage for FFMpeg processing
            $localStoragePath = 'tmp/video_' . $media->id . '/' . basename($storagePath);
            Storage::disk('local')->put($localStoragePath, file_get_contents($tempInputFile));

            // Extract video duration using FFMpeg from local storage
            $ffmpeg = FFMpeg::fromDisk('local')->open($localStoragePath);
            $duration = $ffmpeg->getDurationInSeconds();
            
            Log::info('MomentService: Duration extracted', [
                'media_id' => $media->id,
                'duration' => $duration
            ]);

            // Generate thumbnail from the first frame (300x300)
            $timestamp = 0.1; // Use first frame

            // Get full frame contents first
            $frameContent = $ffmpeg->getFrameFromSeconds($timestamp)
                ->export()
                ->getFrameContents();

            // Use GD to create resized versions
            $sourceImage = imagecreatefromstring($frameContent);
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            // Create thumbnail (300px width, maintain aspect ratio)
            $thumbWidth = 300;
            $thumbHeight = (int) ($origHeight * ($thumbWidth / $origWidth));
            $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $origWidth, $origHeight);
            
            ob_start();
            imagejpeg($thumbImage, null, 85);
            $thumbnailContent = ob_get_clean();
            imagedestroy($thumbImage);

            // Create medium (1200px width, maintain aspect ratio)
            $mediumWidth = min(1200, $origWidth); // Don't upscale
            $mediumHeight = (int) ($origHeight * ($mediumWidth / $origWidth));
            $mediumImage = imagecreatetruecolor($mediumWidth, $mediumHeight);
            imagecopyresampled($mediumImage, $sourceImage, 0, 0, 0, 0, $mediumWidth, $mediumHeight, $origWidth, $origHeight);
            
            ob_start();
            imagejpeg($mediumImage, null, 85);
            $mediumContent = ob_get_clean();
            imagedestroy($mediumImage);
            imagedestroy($sourceImage);

            // Upload thumbnail to seaweedfs storage
            $basePath = dirname($storagePath);
            $thumbnailStoragePath = "{$basePath}/thumbnail.jpg";
            Storage::disk('seaweedfs')->put($thumbnailStoragePath, $thumbnailContent);

            // Upload medium to seaweedfs storage
            $mediumStoragePath = "{$basePath}/medium.jpg";
            Storage::disk('seaweedfs')->put($mediumStoragePath, $mediumContent);

            Log::info('MomentService: Thumbnail and medium generated', [
                'media_id' => $media->id,
                'thumbnail_path' => $thumbnailStoragePath,
                'medium_path' => $mediumStoragePath
            ]);

            // Check if video is HDR and pre-process if needed
            $hlsInputPath = $localStoragePath;
            $isHdr = $this->isVideoHDR($localStoragePath);
            
            if ($isHdr) {
                Log::info('MomentService: HDR video detected, pre-converting to SDR', [
                    'media_id' => $media->id,
                ]);
                $hlsInputPath = $this->convertHdrToSdr($media, $localStoragePath);
            }

            // Generate HLS stream (using pre-processed SDR file if HDR was detected)
            $hlsStoragePath = $this->generateHLSStream($media, $hlsInputPath, $basePath, $tempDir);

            // Clean up local storage temp files
            Storage::disk('local')->deleteDirectory('tmp/video_' . $media->id);

            // Update media with video metadata and HLS URL
            $media->markAsCompleted([
                'thumbnail_url' => $thumbnailStoragePath,
                'medium_url' => $mediumStoragePath,
                'hls_url' => $hlsStoragePath,
                'video_metadata' => [
                    'duration' => $duration,
                    'width' => $origWidth,
                    'height' => $origHeight,
                ],
            ]);

            Log::info('MomentService: Video processing completed', [
                'media_id' => $media->id,
                'duration' => $duration,
                'thumbnail_url' => $thumbnailStoragePath,
                'hls_url' => $hlsStoragePath
            ]);

        } catch (\Exception $e) {
            Log::error('MomentService: Video processing failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $media->markAsFailed($e->getMessage());
            throw $e;
        } finally {
            // Clean up temp files
            if ($tempDir && file_exists($tempDir)) {
                $this->cleanupTempDir($tempDir);
            }
        }
    }

    /**
     * Generate HLS stream with iOS-compatible settings.
     * Uses baseline profile for maximum compatibility with iOS devices.
     */
    private function generateHLSStream(Media $media, string $localStoragePath, string $basePath, string $tempDir): string
    {
        $hlsDir = $tempDir . '/hls';
        if (!file_exists($hlsDir)) {
            mkdir($hlsDir, 0755, true);
        }

        // HLS output path in local storage
        $hlsLocalPath = 'tmp/video_' . $media->id . '/hls/playlist.m3u8';

        Log::info('MomentService: Starting iOS-compatible HLS generation', [
            'media_id' => $media->id,
            'output_path' => $hlsLocalPath
        ]);

        // Get configured quality variants (default: 720, 480, 240)
        $variants = explode(',', env('VIDEO_QUALITY_VARIANTS', '720,480,240'));

        $ffmpeg = FFMpeg::fromDisk('local')->open($localStoragePath);

        // Create HLS export with iOS-compatible settings
        $hlsExport = $ffmpeg->exportForHLS();

        foreach ($variants as $height) {
            $height = (int) trim($height);
            $bitrate = $this->getBitrateForHeight($height);
            
            // Create iOS-compatible X264 format with baseline profile
            $format = (new \FFMpeg\Format\Video\X264('aac', 'libx264'))
                ->setKiloBitrate($bitrate)
                ->setAudioKiloBitrate(128);
            
            // Add iOS-compatible additional parameters
            // Note: -pix_fmt converts 10-bit to 8-bit for baseline profile compatibility
            // For HDR videos, this may result in slightly different colors, but it's the 
            // only compatible approach with laravel-ffmpeg's HLS complex filtergraph
            $format->setAdditionalParameters([
                '-pix_fmt', 'yuv420p',
                '-profile:v', 'baseline',
                '-level', '3.1',
                '-ar', '44100',
                '-movflags', '+faststart',
            ]);
            
            $hlsExport->addFormat(
                $format,
                function ($media) use ($height) {
                    $media->scale(-2, $height);
                }
            );
        }

        $hlsExport
            ->setSegmentLength(10)
            ->toDisk('local')
            ->save($hlsLocalPath);

        Log::info('MomentService: iOS-compatible HLS generated locally', ['media_id' => $media->id]);

        // Upload HLS files to SeaweedFS storage
        $hlsStoragePath = $this->uploadHLSFilesToStorage($media, $basePath);

        return $hlsStoragePath;
    }

    /**
     * Get recommended bitrate for video height.
     */
    private function getBitrateForHeight(int $height): int
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
     * Check if video is HDR using ffprobe.
     * Detects 10-bit HDR content by checking color properties.
     */
    private function isVideoHDR(string $localStoragePath): bool
    {
        try {
            $fullPath = Storage::disk('local')->path($localStoragePath);
            $ffprobeBinary = config('laravel-ffmpeg.ffprobe.binaries', '/usr/bin/ffprobe');
            
            // Run ffprobe to get video stream info
            $command = escapeshellcmd($ffprobeBinary) . ' -v quiet -select_streams v:0 -show_entries stream=pix_fmt,color_transfer,color_primaries,bits_per_raw_sample -of json ' . escapeshellarg($fullPath);
            
            $output = shell_exec($command);
            $data = json_decode($output, true);
            
            if (!$data || empty($data['streams'][0])) {
                Log::debug('MomentService: Could not parse ffprobe output, assuming SDR');
                return false;
            }
            
            $stream = $data['streams'][0];
            $pixFmt = $stream['pix_fmt'] ?? '';
            $colorTransfer = $stream['color_transfer'] ?? '';
            $colorPrimaries = $stream['color_primaries'] ?? '';
            $bitsPerSample = (int)($stream['bits_per_raw_sample'] ?? 8);
            
            Log::info('MomentService: Video color properties', [
                'pix_fmt' => $pixFmt,
                'color_transfer' => $colorTransfer,
                'color_primaries' => $colorPrimaries,
                'bits_per_sample' => $bitsPerSample,
            ]);
            
            // Check for HDR indicators:
            // - 10-bit or higher pixel formats (p10, p10le, etc.)
            // - HLG or PQ/SMPTE 2084 transfer functions
            // - BT.2020 color primaries
            $isHdr = false;
            
            // Check pixel format for 10-bit
            if (str_contains($pixFmt, '10') || str_contains($pixFmt, '12') || $bitsPerSample >= 10) {
                $isHdr = true;
            }
            
            // Check for HDR transfer functions
            $hdrTransfers = ['arib-std-b67', 'smpte2084', 'bt2020-10', 'bt2020-12'];
            if (in_array($colorTransfer, $hdrTransfers)) {
                $isHdr = true;
            }
            
            // Check for BT.2020 color primaries
            if ($colorPrimaries === 'bt2020') {
                $isHdr = true;
            }
            
            Log::info('MomentService: HDR detection result', ['is_hdr' => $isHdr]);
            
            return $isHdr;
            
        } catch (\Exception $e) {
            Log::warning('MomentService: HDR detection failed, assuming SDR', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Convert HDR video to SDR with proper tone mapping.
     * Creates a new file with BT.709 color space and 8-bit depth.
     */
    private function convertHdrToSdr(Media $media, string $inputPath): string
    {
        $fullInputPath = Storage::disk('local')->path($inputPath);
        $outputFileName = 'sdr_converted.mp4';
        $outputPath = 'tmp/video_' . $media->id . '/' . $outputFileName;
        $fullOutputPath = Storage::disk('local')->path($outputPath);
        
        // Ensure output directory exists
        $outputDir = dirname($fullOutputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $ffmpegBinary = config('laravel-ffmpeg.ffmpeg.binaries', '/usr/bin/ffmpeg');
        
        // HDR to SDR conversion command with proper tone mapping
        // Uses zscale for color space conversion and hable tonemap for HDR->SDR
        $command = escapeshellcmd($ffmpegBinary) . ' -y -i ' . escapeshellarg($fullInputPath) . 
            ' -vf "zscale=t=linear:npl=100,format=gbrpf32le,zscale=p=bt709,tonemap=hable:desat=0,zscale=t=bt709:m=bt709:r=tv,format=yuv420p"' .
            ' -c:v libx264 -preset medium -crf 18' .
            ' -c:a aac -b:a 128k' .
            ' -color_primaries bt709 -color_trc bt709 -colorspace bt709' .
            ' -movflags +faststart' .
            ' ' . escapeshellarg($fullOutputPath) . ' 2>&1';
        
        Log::info('MomentService: Starting HDR to SDR conversion', [
            'media_id' => $media->id,
            'input' => $inputPath,
            'output' => $outputPath,
        ]);
        
        $output = shell_exec($command);
        
        // Check if output file was created
        if (!file_exists($fullOutputPath)) {
            Log::error('MomentService: HDR to SDR conversion failed', [
                'media_id' => $media->id,
                'ffmpeg_output' => $output,
            ]);
            throw new \RuntimeException('HDR to SDR conversion failed');
        }
        
        $outputSize = filesize($fullOutputPath);
        Log::info('MomentService: HDR to SDR conversion completed', [
            'media_id' => $media->id,
            'output_size' => $outputSize,
        ]);
        
        return $outputPath;
    }

    /**
     * Upload HLS files to SeaweedFS storage.
     */
    private function uploadHLSFilesToStorage(Media $media, string $basePath): string
    {
        $localHlsDir = 'tmp/video_' . $media->id . '/hls';
        $files = Storage::disk('local')->files($localHlsDir);
        
        foreach ($files as $file) {
            $filename = basename($file);
            $storagePath = "{$basePath}/hls/{$filename}";
            
            $content = Storage::disk('local')->get($file);
            Storage::disk('seaweedfs')->put($storagePath, $content);
            
            Log::debug('MomentService: Uploaded HLS file', [
                'local' => $file,
                'storage' => $storagePath
            ]);
        }

        Log::info('MomentService: All HLS files uploaded', [
            'media_id' => $media->id,
            'file_count' => count($files)
        ]);

        return "{$basePath}/hls/playlist.m3u8";
    }

    /**
     * Clean up temporary directory.
     */
    private function cleanupTempDir(string $dir): void
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
     * Build EXIF data array from metadata.
     */
    private function buildExifData(array $data): array
    {
        return array_filter([
            'exif_date' => $data['exif_date'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'camera_make' => $data['camera_make'] ?? null,
            'camera_model' => $data['camera_model'] ?? null,
            'orientation' => $data['orientation'] ?? null,
        ], fn($v) => !is_null($v));
    }

    /**
     * Generate fingerprint for duplicate detection.
     */
    private function generateFingerprint(UploadedFile $file): string
    {
        return md5_file($file->getRealPath());
    }

    // ========================================================================
    // READ OPERATIONS
    // ========================================================================

    /**
     * Get moments for a user with optional filters.
     */
    public function getMomentsForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Moment::has('kid')
            ->with(['media', 'story', 'userMilestone', 'userMilestone.predefinedMilestone'])
            ->orderBy('created_at', $filters['sort_direction'] ?? 'desc');

        if (!empty($filters['kid_id'])) {
            $query->where('kid_id', $filters['kid_id']);
        }

        if (!empty($filters['moment_type'])) {
            $query->where('moment_type', $filters['moment_type']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get moments for a kid with optional filters.
     */
    public function getMomentsForKid(Kid $kid, array $filters = []): LengthAwarePaginator
    {
        $query = $kid->moments()
            ->with(['media', 'story', 'userMilestone', 'userMilestone.predefinedMilestone'])
            ->orderBy('created_at', $filters['sort_direction'] ?? 'desc');

        if (!empty($filters['moment_type'])) {
            $query->where('moment_type', $filters['moment_type']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }

        if (!empty($filters['device_asset_id'])) {
            $query->byDevice($filters['device_asset_id']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get a single moment with all relations.
     */
    public function getMoment(Moment $moment): array
    {
        $moment->load(['media', 'story', 'userMilestone', 'userMilestone.predefinedMilestone']);
        
        return $moment->toArray();
    }

    /**
     * Update a moment.
     */
    public function updateMoment(Moment $moment, array $data): Moment
    {
        DB::transaction(function () use ($moment, $data) {
            $moment->update(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
            ], fn($v) => !is_null($v)));

            // Update related story if exists
            if ($moment->story && isset($data['text'])) {
                $moment->story->update([
                    'title' => $data['title'] ?? $moment->story->title,
                    'text' => $data['text'],
                ]);
            }

            // Update related milestone if exists
            if ($moment->userMilestone) {
                $moment->userMilestone->update(array_filter([
                    'custom_title' => $data['custom_title'] ?? null,
                    'custom_description' => $data['custom_description'] ?? null,
                    'milestone_date' => $data['milestone_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ], fn($v) => !is_null($v)));
            }
        });

        return $moment->fresh(['media', 'story', 'userMilestone']);
    }

    /**
     * Delete a moment with all related data.
     */
    public function deleteMoment(Moment $moment): void
    {
        DB::transaction(function () use ($moment) {
            // Delete related story
            if ($moment->story) {
                $moment->story->delete();
            }

            // Delete related milestone
            if ($moment->userMilestone) {
                $moment->userMilestone->delete();
            }

            // Delete related media files
            if ($moment->media) {
                $moment->media->deleteWithFiles();
            }

            // Delete files if moment has direct file references
            $paths = array_filter([
                $moment->getRawOriginal('photo_url'),
                $moment->getRawOriginal('medium_url'),
                $moment->getRawOriginal('thumbnail_url'),
            ]);

            if (!empty($paths)) {
                $this->storage->delete($paths);
            }

            $moment->delete();
        });
    }

    /**
     * Serve file through authenticated proxy.
     */
    public function serveFile(Moment $moment, string $type): array
    {
        $pathMap = [
            'original' => $moment->getRawOriginal('photo_url'),
            'medium' => $moment->getRawOriginal('medium_url'),
            'thumbnail' => $moment->getRawOriginal('thumbnail_url'),
            'hls' => $moment->getRawOriginal('hls_url'),
        ];

        if (!isset($pathMap[$type])) {
            throw new \InvalidArgumentException('Invalid file type. Use: original, medium, thumbnail, or hls');
        }

        $path = $pathMap[$type];

        if (empty($path) || !$this->storage->exists($path)) {
            throw new \RuntimeException('File not found');
        }

        $content = $this->storage->get($path);
        $size = $this->storage->size($path);

        // Determine MIME type based on file type
        if ($type === 'thumbnail' || $type === 'medium') {
            $mimeType = 'image/jpeg';
        } elseif ($type === 'hls') {
            $mimeType = str_ends_with($path, '.m3u8') 
                ? 'application/vnd.apple.mpegurl' 
                : 'video/MP2T';
        } else {
            $mimeType = $moment->mime_type ?? 'application/octet-stream';
        }

        return [
            'content' => $content,
            'size' => $size,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Serve individual HLS file (variant playlist or segment).
     */
    public function serveHlsFile(Moment $moment, string $filename): array
    {
        // Get the base HLS path from the moment's hls_url
        $hlsUrl = $moment->getRawOriginal('hls_url');
        
        if (empty($hlsUrl)) {
            throw new \RuntimeException('HLS not available for this moment');
        }

        // The hls_url points to playlist.m3u8, get the directory
        $hlsDir = dirname($hlsUrl);
        $filePath = "{$hlsDir}/{$filename}";

        if (!$this->storage->exists($filePath)) {
            throw new \RuntimeException("HLS file not found: {$filename}");
        }

        $content = $this->storage->get($filePath);
        $size = $this->storage->size($filePath);

        // Determine MIME type based on file extension
        $mimeType = str_ends_with($filename, '.m3u8') 
            ? 'application/vnd.apple.mpegurl' 
            : 'video/MP2T';

        return [
            'content' => $content,
            'size' => $size,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Check file status (diagnostic).
     */
    public function checkFileStatus(Moment $moment): array
    {
        $photoUrl = $moment->getRawOriginal('photo_url');
        $mediumUrl = $moment->getRawOriginal('medium_url');
        $thumbnailUrl = $moment->getRawOriginal('thumbnail_url');

        return [
            'moment_id' => $moment->id,
            'moment_type' => $moment->moment_type,
            'paths' => compact('photoUrl', 'mediumUrl', 'thumbnailUrl'),
            'exists' => [
                'photo' => $photoUrl ? $this->storage->exists($photoUrl) : false,
                'medium' => $mediumUrl ? $this->storage->exists($mediumUrl) : false,
                'thumbnail' => $thumbnailUrl ? $this->storage->exists($thumbnailUrl) : false,
            ],
        ];
    }

    /**
     * Get uploaded identifiers for duplicate detection.
     */
    public function getUploadedIdentifiers(Kid $kid): Collection
    {
        return $kid->moments()
            ->whereIn('moment_type', ['photo', 'video'])
            ->where(fn($q) => $q->whereNotNull('device_asset_id')->orWhereNotNull('device_fingerprint'))
            ->get(['device_asset_id', 'device_fingerprint', 'file_size', 'original_filename'])
            ->map(fn($m) => [
                'device_asset_id' => $m->device_asset_id,
                'device_fingerprint' => $m->device_fingerprint,
                'file_size' => $m->file_size,
                'filename' => $m->original_filename,
            ]);
    }

    // ========================================================================
    // VALIDATION HELPERS
    // ========================================================================

    /**
     * Validate icon image belongs to user.
     */
    public function validateIconImage(User $user, ?string $iconImageId): bool
    {
        if (empty($iconImageId)) {
            return true;
        }

        $media = Media::find($iconImageId);
        return $media && $media->user_id === $user->id;
    }

    /**
     * Validate predefined milestone exists.
     */
    public function validatePredefinedMilestone(?string $id): bool
    {
        if (empty($id)) {
            return true;
        }

        return PredefinedMilestone::where('id', $id)->exists();
    }
}
