<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MomentType;
use App\Enums\VideoStatus;
use App\Jobs\ProcessVideoJob;
use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\User;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * Unified service for handling all moment types (photo, video, story, milestone, growth).
 */
class MomentService
{
    private const PHOTO_MIMES = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif',
    ];

    private const VIDEO_MIMES = [
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm', 'video/x-m4v',
    ];

    public function __construct(
        private readonly FileStorageService $storage,
    ) {}

    /**
     * @return array{moment: Moment, media?: Media}
     */
    public function create(User $user, Kid $kid, array $data, ?UploadedFile $file = null): array
    {
        $rawType = (string) ($data['moment_type'] ?? $this->detectMomentType($file)->value);
        $momentType = MomentType::tryFrom($rawType) ?? throw new \InvalidArgumentException("Invalid moment_type: {$rawType}");

        Log::info('Creating moment', [
            'type' => $momentType->value,
            'kid_id' => $kid->id,
            'has_file' => ! is_null($file),
        ]);

        return match ($momentType) {
            MomentType::Photo, MomentType::Video => $this->createMediaMoment($user, $kid, $file, $data, $momentType),
            MomentType::Story, MomentType::Milestone, MomentType::Growth => $this->createTextMoment($user, $kid, $data, $momentType),
        };
    }

    private function detectMomentType(?UploadedFile $file): MomentType
    {
        if (! $file) {
            throw new \InvalidArgumentException('moment_type is required when no file is uploaded');
        }

        $mimeType = $file->getMimeType();

        if (in_array($mimeType, self::PHOTO_MIMES)) {
            return MomentType::Photo;
        }

        if (in_array($mimeType, self::VIDEO_MIMES)) {
            return MomentType::Video;
        }

        throw new \InvalidArgumentException(
            'Unsupported file type. Please upload an image (JPEG, PNG, GIF, WebP, HEIC) or video (MP4, MOV, M4V, WebM).'
        );
    }

    /**
     * @return array{moment: Moment, media: ?Media}
     */
    private function createMediaMoment(User $user, Kid $kid, UploadedFile $file, array $data, MomentType $type): array
    {
        // A file this child already has (same bytes, from any member or phone) or the same asset sent again
        // (a retry after a lost response) gets the moment it already made. A deleted moment does not count.
        // A failed video is left out, so the phone can send it again (see getUploadedIdentifiers).
        $fingerprint = $this->generateFingerprint($file);
        $existing = $kid->moments()
            ->whereIn('moment_type', [MomentType::Photo, MomentType::Video])
            ->where(fn ($q) => $q->where('device_fingerprint', $fingerprint)
                ->when(! empty($data['device_asset_id']), fn ($q) => $q->orWhere(fn ($q) => $q
                    ->where('device_asset_id', $data['device_asset_id'])
                    ->where('file_size', $file->getSize()))))
            ->where(fn ($q) => $q->whereNull('video_status')->orWhere('video_status', '!=', VideoStatus::Failed))
            ->first();
        if ($existing) {
            return ['moment' => $existing, 'media' => $existing->media];
        }

        $mimeType = $file->getMimeType();
        $mediaId = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $basePath = "media/{$kid->id}/{$mediaId}";
        $originalPath = "{$basePath}/original.{$extension}";
        $media = null;

        // Any failure from the first write to the moment row drops the media row and every file stored so far
        try {
            $this->storage->storeFile($originalPath, $file);
            Log::info('Original file uploaded', ['path' => $originalPath]);

            $media = Media::create([
                'id' => $mediaId,
                'kid_id' => $kid->id,
                'user_id' => $user->id,
                'type' => $type->value,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $originalPath,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'exif_data' => $this->buildExifData($data),
                'status' => Media::STATUS_PROCESSING,
                'device_asset_id' => $data['device_asset_id'] ?? null,
                'device_fingerprint' => $fingerprint,
                'processing_started_at' => now(),
            ]);

            // Videos are processed by a queued job so the upload returns at once; it sets their size
            $size = $type === MomentType::Photo ? $this->processPhoto($media, $file, $basePath) : [];

            $media->refresh();
            $moment = Moment::create([
                'kid_id' => $kid->id,
                'user_id' => $user->id,
                'media_id' => $media->id,
                'moment_type' => $type,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'photo_url' => $media->getRawFilePath(),
                'thumbnail_url' => $media->getRawThumbnailUrl(),
                'medium_url' => $media->getRawMediumUrl(),
                'hls_url' => $type === MomentType::Video ? $media->getRawHlsUrl() : null,
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
                'video_status' => $type === MomentType::Video ? VideoStatus::Processing : null,
            ] + $size);
        } catch (\Throwable $e) {
            Log::error('Media upload failed: '.$e->getMessage());
            $media?->forceDelete();
            $this->storage->disk()->deleteDirectory($basePath);
            throw $e;
        }

        // A new upload of a video that failed before replaces the failed moment.
        if (! empty($data['device_asset_id'])) {
            $kid->moments()->where('device_asset_id', $data['device_asset_id'])
                ->where('video_status', VideoStatus::Failed)->whereKeyNot($moment->id)
                ->get()->each(fn (Moment $failed) => $this->deleteMoment($failed));
        }

        if ($type === MomentType::Video) {
            ProcessVideoJob::dispatch($media->id);
        }

        return ['moment' => $moment, 'media' => $media];
    }

    /**
     * @return array{moment: Moment}
     */
    private function createTextMoment(User $user, Kid $kid, array $data, MomentType $type): array
    {
        $moment = Moment::create([
            'kid_id' => $kid->id,
            'user_id' => $user->id,
            'moment_type' => $type,
            'milestone_id' => $type === MomentType::Milestone ? $data['milestone_id'] : null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'head_circumference_cm' => $data['head_circumference_cm'] ?? null,
            // Metadata
            'exif_date' => self::utc($data['exif_date'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'camera_make' => $data['camera_make'] ?? null,
            'camera_model' => $data['camera_model'] ?? null,
            'orientation' => $data['orientation'] ?? null,
        ]);

        Log::info('Moment created', ['moment_id' => $moment->id, 'type' => $type->value]);

        return ['moment' => $moment];
    }

    /** @return array{width: int, height: int} display size of the photo */
    private function processPhoto(Media $media, UploadedFile $file, string $basePath): array
    {
        $thumbnailPath = "{$basePath}/thumbnail.jpg";
        $mediumPath = "{$basePath}/medium.jpg";

        // GD cannot read HEIC/HEIF: decode a JPEG copy for the previews; the stored original stays untouched.
        $source = $file->getRealPath();
        $decoded = null;

        // The cleanup also runs when heif-dec fails
        try {
            if (in_array($file->getMimeType(), ['image/heic', 'image/heif'], true)) {
                $decoded = tempnam(sys_get_temp_dir(), 'kinlore-heic-').'.jpg';
                Process::timeout(120)->run(['heif-dec', '--quality', '95', $source, $decoded])->throw();
                $source = $decoded;
            }

            $size = $this->storePreviews($source, $thumbnailPath, $mediumPath);
        } finally {
            if ($decoded) {
                @unlink($decoded);
                @unlink(substr($decoded, 0, -4));
            }
        }

        // Update media record
        $media->markAsCompleted([
            'thumbnail_url' => $thumbnailPath,
            'medium_url' => $mediumPath,
        ]);

        return $size;
    }

    /** @return array{width: int, height: int} size after the EXIF orientation */
    private function storePreviews(string $source, string $thumbnailPath, string $mediumPath): array
    {
        // Generate medium (1200px width)
        $image = Image::decodePath($source)->orient();
        $size = ['width' => $image->width(), 'height' => $image->height()];
        $mediumImage = $image->scaleDown(width: 1200)->encode(new JpegEncoder(quality: 85));

        $this->storage->store($mediumPath, (string) $mediumImage);

        // Generate thumbnail (300x300 cover)
        $thumbnailImage = Image::decodePath($source)
            ->orient()
            ->cover(300, 300)
            ->encode(new JpegEncoder(quality: 80));

        $this->storage->store($thumbnailPath, (string) $thumbnailImage);

        return $size;
    }

    // Run by ProcessVideoJob: make the HLS stream and set the moment's video_status to ready or failed.
    public function processVideoMoment(Media $media): void
    {
        try {
            $this->processVideo($media);
        } catch (\Throwable) {
            $media->moments()->update(['video_status' => VideoStatus::Failed]);

            return;
        }

        // The media can be deleted while the video converts
        $media = Media::find($media->id);
        if (! $media) {
            return;
        }

        $media->moments()->update([
            'thumbnail_url' => $media->getRawThumbnailUrl(),
            'medium_url' => $media->getRawMediumUrl(),
            'hls_url' => $media->getRawHlsUrl(),
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'video_status' => VideoStatus::Ready,
        ]);
    }

    // Extracts duration, generates thumbnail and medium, and creates the HLS stream.
    private function processVideo(Media $media): void
    {
        $localDir = 'tmp/video_'.$media->id;

        try {
            Log::info('MomentService: Starting video processing', ['media_id' => $media->id]);

            // Get the storage path
            $storagePath = $media->getRawFilePath();

            // Copy the video to Laravel's local storage for FFMpeg, as a stream: it can be larger than PHP memory
            $localStoragePath = $localDir.'/'.basename($storagePath);
            $source = Storage::disk('seaweedfs')->readStream($storagePath);
            if (! $source) {
                throw new \RuntimeException("Video file not found in storage: {$storagePath}");
            }
            try {
                if (! Storage::disk('local')->writeStream($localStoragePath, $source)) {
                    throw new \RuntimeException("Cannot copy the video for ffmpeg: {$localStoragePath}");
                }
            } finally {
                if (is_resource($source)) {
                    fclose($source);
                }
            }

            // Extract video duration using FFMpeg from local storage
            $ffmpeg = FFMpeg::fromDisk('local')->open($localStoragePath);
            $duration = $ffmpeg->getDurationInSeconds();

            Log::info('MomentService: Duration extracted', [
                'media_id' => $media->id,
                'duration' => $duration,
            ]);

            // Generate thumbnail from the first frame (300x300)
            $timestamp = 0.1; // Use first frame

            // Get full frame contents first
            $frameContent = $ffmpeg->getFrameFromSeconds($timestamp)
                ->export()
                ->getFrameContents();

            // Use GD to create resized versions. ffmpeg applies the rotation tag when it decodes (autorotate is on
            // by default), so this frame and the width and height saved below are already the display size.
            $sourceImage = imagecreatefromstring($frameContent) ?: throw new \RuntimeException('Cannot decode the video frame');
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            $thumbnailContent = $this->jpegAtWidth($sourceImage, 300);
            $mediumContent = $this->jpegAtWidth($sourceImage, min(1200, $origWidth)); // Don't upscale

            // Upload thumbnail to seaweedfs storage
            $basePath = dirname($storagePath);
            $thumbnailStoragePath = "{$basePath}/thumbnail.jpg";
            $this->storage->store($thumbnailStoragePath, $thumbnailContent);

            // Upload medium to seaweedfs storage
            $mediumStoragePath = "{$basePath}/medium.jpg";
            $this->storage->store($mediumStoragePath, $mediumContent);

            Log::info('MomentService: Thumbnail and medium generated', [
                'media_id' => $media->id,
                'thumbnail_path' => $thumbnailStoragePath,
                'medium_path' => $mediumStoragePath,
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
            $hlsStoragePath = $this->generateHLSStream($media, $hlsInputPath, $basePath);

            if (! Media::whereKey($media->id)->exists()) {
                Log::info('MomentService: Media deleted during processing, removing its files', ['media_id' => $media->id]);
                Storage::disk('seaweedfs')->deleteDirectory($basePath);

                return;
            }

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
                'hls_url' => $hlsStoragePath,
            ]);

        } catch (\Throwable $e) {
            Log::error('MomentService: Video processing failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $media->markAsFailed($e->getMessage());
            throw $e;
        } finally {
            Storage::disk('local')->deleteDirectory($localDir);
        }
    }

    // JPEG (quality 85) of $source scaled to $width, aspect ratio kept.
    private function jpegAtWidth(\GdImage $source, int $width): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $height = (int) ($sourceHeight * ($width / $sourceWidth));
        $image = imagecreatetruecolor($width, $height);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($image, null, 85);

        return ob_get_clean();
    }

    /**
     * Generate HLS stream with iOS-compatible settings.
     * Uses baseline profile for maximum compatibility with iOS devices.
     */
    private function generateHLSStream(Media $media, string $localStoragePath, string $basePath): string
    {
        // HLS output path in local storage
        $hlsLocalPath = 'tmp/video_'.$media->id.'/hls/playlist.m3u8';

        Log::info('MomentService: Starting iOS-compatible HLS generation', [
            'media_id' => $media->id,
            'output_path' => $hlsLocalPath,
        ]);

        // Get configured quality variants (default: 720, 480, 240)
        $variants = explode(',', (string) config('laravel-ffmpeg.quality_variants'));

        $ffmpeg = FFMpeg::fromDisk('local')->open($localStoragePath);

        // Create HLS export with iOS-compatible settings
        $hlsExport = $ffmpeg->exportForHLS();

        foreach ($variants as $height) {
            $height = (int) trim($height);
            $bitrate = $this->getBitrateForHeight($height);

            // Create iOS-compatible X264 format with baseline profile
            $format = (new X264('aac', 'libx264'))
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

            $hlsExport->addFormat($format, fn ($video) => $video->scale(-2, $height));
        }

        $hlsExport
            ->setSegmentLength(10)
            ->toDisk('local')
            ->save($hlsLocalPath);

        Log::info('MomentService: iOS-compatible HLS generated locally', ['media_id' => $media->id]);

        return $this->uploadHLSFilesToStorage($media, $basePath);
    }

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
            $command = escapeshellcmd($ffprobeBinary).' -v quiet -select_streams v:0 -show_entries stream=pix_fmt,color_transfer,color_primaries,bits_per_raw_sample -of json '.escapeshellarg($fullPath);

            $output = shell_exec($command);
            // shell_exec gives null or false when ffprobe prints nothing
            $data = json_decode((string) $output, true);

            if (! $data || empty($data['streams'][0])) {
                Log::debug('MomentService: Could not parse ffprobe output, assuming SDR');

                return false;
            }

            $stream = $data['streams'][0];
            $pixFmt = $stream['pix_fmt'] ?? '';
            $colorTransfer = $stream['color_transfer'] ?? '';
            $colorPrimaries = $stream['color_primaries'] ?? '';
            $bitsPerSample = (int) ($stream['bits_per_raw_sample'] ?? 8);

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
                'error' => $e->getMessage(),
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
        $outputPath = 'tmp/video_'.$media->id.'/'.$outputFileName;
        $fullOutputPath = Storage::disk('local')->path($outputPath);

        // Ensure output directory exists
        $outputDir = dirname($fullOutputPath);
        if (! file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $ffmpegBinary = config('laravel-ffmpeg.ffmpeg.binaries', '/usr/bin/ffmpeg');

        // HDR to SDR conversion command with proper tone mapping
        // Uses zscale for color space conversion and hable tonemap for HDR->SDR
        $command = escapeshellcmd($ffmpegBinary).' -y -i '.escapeshellarg($fullInputPath).
            ' -vf "scale=-2:\'min(720,ih)\',zscale=t=linear:npl=100,format=gbrpf32le,zscale=p=bt709,tonemap=hable:desat=0,zscale=t=bt709:m=bt709:r=tv,format=yuv420p"'.
            ' -c:v libx264 -preset veryfast -crf 18'.
            ' -c:a aac -b:a 128k'.
            ' -color_primaries bt709 -color_trc bt709 -colorspace bt709'.
            ' -movflags +faststart'.
            ' '.escapeshellarg($fullOutputPath).' 2>&1';

        Log::info('MomentService: Starting HDR to SDR conversion', [
            'media_id' => $media->id,
            'input' => $inputPath,
            'output' => $outputPath,
        ]);

        $output = shell_exec($command);

        // Check if output file was created
        if (! file_exists($fullOutputPath)) {
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

    private function uploadHLSFilesToStorage(Media $media, string $basePath): string
    {
        $localHlsDir = 'tmp/video_'.$media->id.'/hls';
        $files = Storage::disk('local')->files($localHlsDir);

        foreach ($files as $file) {
            $filename = basename($file);
            $storagePath = "{$basePath}/hls/{$filename}";

            $segment = Storage::disk('local')->readStream($file);
            try {
                $this->storage->store($storagePath, $segment);
            } finally {
                if (is_resource($segment)) {
                    fclose($segment);
                }
            }

            Log::debug('MomentService: Uploaded HLS file', [
                'local' => $file,
                'storage' => $storagePath,
            ]);
        }

        Log::info('MomentService: All HLS files uploaded', [
            'media_id' => $media->id,
            'file_count' => count($files),
        ]);

        return "{$basePath}/hls/playlist.m3u8";
    }

    private function buildExifData(array $data): array
    {
        return array_filter([
            'exif_date' => self::utc($data['exif_date'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'camera_make' => $data['camera_make'] ?? null,
            'camera_model' => $data['camera_model'] ?? null,
            'orientation' => $data['orientation'] ?? null,
        ], fn ($v) => ! is_null($v));
    }

    // A date with an offset from the REST API, stored in UTC like the sync API stores it
    private static function utc(?string $date): ?string
    {
        return $date === null ? null : Carbon::parse($date)->utc()->toDateTimeString();
    }

    // Fingerprint for duplicate detection
    private function generateFingerprint(UploadedFile $file): string
    {
        return md5_file($file->getRealPath());
    }

    // ========================================================================
    // READ OPERATIONS
    // ========================================================================

    public function getMomentsForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $displayDate = 'COALESCE(exif_date, created_at)';

        $query = Moment::has('kid')
            ->forPayload($user)
            ->orderByDate('desc')
            ->orderBy('id');

        if (! empty($filters['kid_id'])) {
            $query->where('kid_id', $filters['kid_id']);
        }

        if (! empty($filters['moment_type'])) {
            $query->whereIn('moment_type', explode(',', $filters['moment_type']));
        }

        if (filter_var($filters['liked'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('likes', fn ($q) => $q->where('user_id', $user->id));
        }

        if (! empty($filters['from'])) {
            $query->whereRaw("{$displayDate} >= ?", [Carbon::parse($filters['from'])->startOfDay()->toDateTimeString()]);
        }

        if (! empty($filters['to'])) {
            $query->whereRaw("{$displayDate} < ?", [Carbon::parse($filters['to'])->addDay()->startOfDay()->toDateTimeString()]);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function getMoment(Moment $moment, ?User $viewer = null): array
    {
        return Moment::whereKey($moment->id)->forPayload($viewer)->firstOrFail()->toArray();
    }

    public function updateMoment(Moment $moment, array $data): Moment
    {
        if (array_key_exists('exif_date', $data)) {
            $data['exif_date'] = self::utc($data['exif_date']);
        }

        $moment->update(array_intersect_key($data, array_flip([
            'title', 'description', 'exif_date', 'milestone_id', 'height_cm', 'weight_kg', 'head_circumference_cm',
        ])));

        return $moment;
    }

    public function deleteMoment(Moment $moment): void
    {
        $media = $moment->media;
        $paths = array_filter([
            $moment->getRawOriginal('photo_url'),
            $moment->getRawOriginal('medium_url'),
            $moment->getRawOriginal('thumbnail_url'),
        ]);

        DB::transaction(function () use ($moment, $media): void {
            $moment->delete();
            $media?->delete();
        });

        // Files go only after the commit, also of a caller's transaction (sync): a failed row delete must never leave a moment without its files
        DB::afterCommit(function () use ($moment, $media, $paths): void {
            $media?->deleteFiles();
            if (! $this->storage->delete($paths)) {
                Log::warning('Cannot delete moment files', ['moment_id' => $moment->id, 'paths' => $paths]);
            }
        });
    }

    public function serveFile(Moment $moment, string $type): array
    {
        $pathMap = [
            'original' => $moment->getRawOriginal('photo_url'),
            'medium' => $moment->getRawOriginal('medium_url'),
            'thumbnail' => $moment->getRawOriginal('thumbnail_url'),
            'hls' => $moment->getRawOriginal('hls_url'),
        ];

        // Not isset(): a video without a thumbnail yet has a null path, which is "not found", not a bad type
        if (! array_key_exists($type, $pathMap)) {
            throw new \InvalidArgumentException('Invalid file type. Use: original, medium, thumbnail, or hls');
        }

        $path = $pathMap[$type];

        if (empty($path) || ! $this->storage->exists($path)) {
            throw new \RuntimeException('File not found');
        }

        $size = $this->storage->size($path);

        // An original can be a video larger than PHP memory, so it goes out as a stream
        if ($type === 'original') {
            return [
                'stream' => $this->storage->disk()->readStream($path),
                'size' => $size,
                'mime_type' => $moment->mime_type ?? 'application/octet-stream',
            ];
        }

        return [
            'content' => $this->storage->get($path),
            'size' => $size,
            'mime_type' => $type === 'hls' ? self::hlsMimeType($path) : 'image/jpeg',
        ];
    }

    // A variant playlist or a segment
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

        if (! $this->storage->exists($filePath)) {
            throw new \RuntimeException("HLS file not found: {$filename}");
        }

        return [
            'content' => $this->storage->get($filePath),
            'size' => $this->storage->size($filePath),
            'mime_type' => self::hlsMimeType($filename),
        ];
    }

    private static function hlsMimeType(string $path): string
    {
        return str_ends_with($path, '.m3u8') ? 'application/vnd.apple.mpegurl' : 'video/MP2T';
    }

    /**
     * Signed HLS URL for the player (docs/reference/api.md section 6).
     *
     * @return array{hls_url: string, expires_at: string}
     */
    public function playback(Moment $moment): array
    {
        $expires = now()->addHours(6);

        return [
            'hls_url' => URL::temporarySignedRoute('moments.hls.master', $expires, ['moment' => $moment->id]),
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    /**
     * Serve the master playlist ($filename null) or a variant/segment, with playlist references signed until $expires.
     */
    public function serveSignedHls(Moment $moment, ?string $filename, int $expires): array
    {
        $file = $filename === null ? $this->serveFile($moment, 'hls') : $this->serveHlsFile($moment, $filename);

        return $this->storage->signPlaylist($file, 'moments.hls.file', ['moment' => $moment->id], $expires);
    }

    // Diagnostic
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

    // For duplicate detection on the device
    public function getUploadedIdentifiers(Kid $kid): Collection
    {
        return $kid->moments()
            ->whereIn('moment_type', [MomentType::Photo, MomentType::Video])
            ->where(fn ($q) => $q->whereNotNull('device_asset_id')->orWhereNotNull('device_fingerprint'))
            // A failed video is left out, so the phone sends it again
            ->where(fn ($q) => $q->whereNull('video_status')->orWhere('video_status', '!=', VideoStatus::Failed))
            ->get(['device_asset_id', 'device_fingerprint', 'file_size', 'original_filename'])
            ->map(fn ($m) => [
                'device_asset_id' => $m->device_asset_id,
                'device_fingerprint' => $m->device_fingerprint,
                'file_size' => $m->file_size,
                'filename' => $m->original_filename,
            ]);
    }
}
