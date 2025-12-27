<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Media;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * Service for handling media upload and processing.
 */
class MediaService
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
     * Upload media and create associated moment.
     *
     * @return array{media: Media, moment: Moment}
     */
    public function upload(User $user, Kid $kid, UploadedFile $file, array $metadata = []): array
    {
        $mimeType = $file->getMimeType();
        $type = $this->determineMediaType($mimeType);

        if (!$type) {
            throw new \InvalidArgumentException(
                'Unsupported file type. Please upload an image (JPEG, PNG, HEIC) or video (MP4, MOV, WebM).'
            );
        }

        Log::info('Media upload started', [
            'type' => $type,
            'mime' => $mimeType,
            'size' => $file->getSize(),
        ]);

        $mediaId = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $basePath = "media/{$kid->id}/{$mediaId}";
        $originalPath = "{$basePath}/original.{$extension}";

        // Store original file
        $this->storage->storeFile($originalPath, $file);
        Log::info('Original file uploaded', ['path' => $originalPath]);

        // Build EXIF data for photos
        $exifData = $type === Media::TYPE_PHOTO ? $this->buildExifData($metadata) : null;

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
            'device_asset_id' => $metadata['device_asset_id'] ?? null,
            'device_fingerprint' => $this->generateFingerprint($file),
            'processing_started_at' => now(),
        ]);

        // Process based on type
        if ($type === Media::TYPE_PHOTO) {
            $this->processPhoto($media, $file, $basePath);
        } else {
            $this->processVideo($media);
        }

        // Create associated moment
        $media->refresh();
        $moment = $this->createMomentForMedia($media, $kid, $metadata);

        Log::info('Media and moment created', [
            'media_id' => $mediaId,
            'moment_id' => $moment->id,
            'type' => $type,
        ]);

        return [
            'media' => $media,
            'moment' => $moment,
        ];
    }

    /**
     * Create moment record for uploaded media.
     */
    private function createMomentForMedia(Media $media, Kid $kid, array $metadata): Moment
    {
        return Moment::create([
            'kid_id' => $kid->id,
            'media_id' => $media->id,
            'moment_type' => $media->type,
            'title' => $metadata['title'] ?? null,
            'description' => $metadata['description'] ?? null,
            'photo_url' => $media->getRawFilePath(),
            'thumbnail_url' => $media->getRawThumbnailUrl(),
            'medium_url' => $media->getRawMediumUrl(),
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
    }

    /**
     * Process photo: generate thumbnail and medium sizes.
     */
    public function processPhoto(Media $media, UploadedFile $file, string $basePath): void
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
            Log::info('Medium photo processed', ['path' => $mediumPath]);

            // Generate thumbnail (300x300 cover)
            $thumbnailImage = Image::read($file->getRealPath())
                ->orient()
                ->cover(300, 300)
                ->toJpeg(quality: 80);

            $this->storage->store($thumbnailPath, (string) $thumbnailImage);
            Log::info('Thumbnail photo processed', ['path' => $thumbnailPath]);

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
     * Extracts duration, generates thumbnail.
     */
    public function processVideo(Media $media): void
    {
        $tempDir = null;
        
        try {
            Log::info('MediaService: Starting video processing', ['media_id' => $media->id]);

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

            // Extract video duration using FFMpeg
            $ffmpeg = FFMpeg::fromDisk('local')->open($tempInputFile);
            $duration = $ffmpeg->getDurationInSeconds();
            
            Log::info('MediaService: Duration extracted', [
                'media_id' => $media->id,
                'duration' => $duration
            ]);

            // Generate thumbnail at 50% of duration
            $thumbnailTempPath = $tempDir . '/thumbnail.jpg';
            $timestamp = max(1, $duration * 0.5);

            $ffmpeg->getFrameFromSeconds($timestamp)
                ->export()
                ->toDisk('local')
                ->save($thumbnailTempPath);

            // Upload thumbnail to storage
            $basePath = dirname($storagePath);
            $thumbnailStoragePath = "{$basePath}/thumbnail.jpg";
            Storage::disk('seaweedfs')->put(
                $thumbnailStoragePath,
                file_get_contents($thumbnailTempPath)
            );

            Log::info('MediaService: Thumbnail generated', [
                'media_id' => $media->id,
                'thumbnail_path' => $thumbnailStoragePath
            ]);

            // Update media with video metadata
            $media->markAsCompleted([
                'thumbnail_url' => $thumbnailStoragePath,
                'video_metadata' => [
                    'duration' => $duration,
                ],
            ]);

            Log::info('MediaService: Video processing completed', [
                'media_id' => $media->id,
                'duration' => $duration,
                'thumbnail_url' => $thumbnailStoragePath
            ]);

        } catch (\Exception $e) {
            Log::error('MediaService: Video processing failed', [
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
     * Get media list for user with filters.
     */
    public function getMediaForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Media::forUser($user->id)->orderBy('created_at', 'desc');

        if (!empty($filters['kid_id'])) {
            $query->forKid($filters['kid_id']);
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['photo', 'video'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['uploading', 'processing', 'completed', 'failed'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get uploaded identifiers for duplicate detection.
     */
    public function getUploadedIdentifiers(Kid $kid): Collection
    {
        return Media::forKid($kid->id)
            ->where(function ($query) {
                $query->whereNotNull('device_asset_id')
                      ->orWhereNotNull('device_fingerprint');
            })
            ->get(['device_asset_id', 'device_fingerprint', 'file_size', 'original_filename'])
            ->map(fn ($media) => [
                'device_asset_id' => $media->device_asset_id,
                'device_fingerprint' => $media->device_fingerprint,
                'file_size' => $media->file_size,
                'filename' => $media->original_filename,
            ]);
    }

    /**
     * Serve file through authenticated proxy.
     */
    public function serveFile(Media $media, string $type): array
    {
        $pathMap = [
            'original' => $media->getRawFilePath(),
            'medium' => $media->getRawMediumUrl(),
            'thumbnail' => $media->getRawThumbnailUrl(),
            'hls' => $media->getRawHlsUrl(),
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

        // Determine MIME type
        if ($type === 'thumbnail' || $type === 'medium') {
            $mimeType = 'image/jpeg';
        } elseif ($type === 'hls') {
            $mimeType = str_ends_with($path, '.m3u8') 
                ? 'application/vnd.apple.mpegurl' 
                : 'video/MP2T';
        } else {
            $mimeType = $media->mime_type ?? 'application/octet-stream';
        }

        return [
            'content' => $content,
            'size' => $size,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Delete media with all associated files.
     */
    public function deleteMedia(Media $media): void
    {
        $media->deleteWithFiles();
    }

    /**
     * Determine media type from MIME type.
     */
    public function determineMediaType(string $mimeType): ?string
    {
        if (in_array($mimeType, self::PHOTO_MIMES)) {
            return Media::TYPE_PHOTO;
        }

        if (in_array($mimeType, self::VIDEO_MIMES)) {
            return Media::TYPE_VIDEO;
        }

        return null;
    }

    /**
     * Build EXIF data array from metadata.
     */
    private function buildExifData(array $metadata): array
    {
        return array_filter([
            'exif_date' => $metadata['exif_date'] ?? null,
            'latitude' => $metadata['latitude'] ?? null,
            'longitude' => $metadata['longitude'] ?? null,
            'camera_make' => $metadata['camera_make'] ?? null,
            'camera_model' => $metadata['camera_model'] ?? null,
            'orientation' => $metadata['orientation'] ?? null,
        ], fn($v) => !is_null($v));
    }

    /**
     * Generate fingerprint for duplicate detection.
     */
    public function generateFingerprint(UploadedFile $file): string
    {
        return md5_file($file->getRealPath());
    }
}
