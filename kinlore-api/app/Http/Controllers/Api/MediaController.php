<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Kid;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    /**
     * List media for user with optional filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Media::forUser($user->id)
            ->orderBy('created_at', 'desc');

        // Filter by kid
        if ($request->has('kid_id')) {
            $query->forKid($request->input('kid_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $type = $request->input('type');
            if (in_array($type, ['photo', 'video'])) {
                $query->where('type', $type);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->input('status');
            if (in_array($status, ['uploading', 'processing', 'completed', 'failed'])) {
                $query->where('status', $status);
            }
        }

        $media = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $media->items(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Upload a new media file (photo or video).
     */
    public function store(Request $request)
    {
        // Validate: either 'photo' or 'video' file must be present
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (env('MEDIA_MAX_SIZE_MB', 500) * 1024),
            ],
            'kid_id' => 'required|uuid|exists:kids,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'device_asset_id' => 'nullable|string|max:255',
            // EXIF data (for photos, sent from client)
            'exif_date' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'camera_make' => 'nullable|string|max:255',
            'camera_model' => 'nullable|string|max:255',
            'orientation' => 'nullable|integer|between:1,8',
        ]);

        $user = $request->user();
        $kid = Kid::findOrFail($validated['kid_id']);
        
        // Authorize access to kid
        $this->authorize('view', $kid);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();
        
        // Determine media type from MIME
        $type = $this->determineMediaType($mimeType);
        
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported file type. Please upload an image (JPEG, PNG, HEIC) or video (MP4, MOV, WebM).',
            ], 422);
        }

        Log::info('Media upload started', [
            'type' => $type,
            'mime' => $mimeType,
            'size' => $file->getSize(),
        ]);

        try {
            // Generate unique ID
            $mediaId = Str::uuid()->toString();
            $extension = $file->getClientOriginalExtension();
            
            // Store paths
            $basePath = "media/{$kid->id}/{$mediaId}";
            $originalPath = "{$basePath}/original.{$extension}";
            
            // Store original file
            Storage::disk('seaweedfs')->put(
                $originalPath,
                file_get_contents($file->getRealPath()),
                'private'
            );
            
            Log::info('Original file uploaded', ['path' => $originalPath]);

            // Build EXIF data from request
            $exifData = null;
            if ($type === Media::TYPE_PHOTO) {
                $exifData = $this->buildExifData($validated);
            }

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
                'device_asset_id' => $validated['device_asset_id'] ?? null,
                'device_fingerprint' => $this->generateFingerprint($file),
                'processing_started_at' => now(),
            ]);

            // Process based on type
            if ($type === Media::TYPE_PHOTO) {
                $this->processPhoto($media, $file, $basePath);
            } else {
                $this->processVideo($media);
            }

            Log::info('Media record created', [
                'media_id' => $mediaId,
                'type' => $type,
            ]);

            return response()->json([
                'success' => true,
                'message' => $type === Media::TYPE_PHOTO 
                    ? 'Photo uploaded successfully' 
                    : 'Video uploaded successfully. Processing will begin shortly.',
                'data' => $media->fresh()->toArray(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Media upload failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Media upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get single media item.
     */
    public function show(Media $media)
    {
        $this->authorize('view', $media->kid);

        return response()->json([
            'success' => true,
            'data' => $media->toArray(),
        ]);
    }

    /**
     * Update media metadata.
     */
    public function update(Request $request, Media $media)
    {
        $this->authorize('view', $media->kid);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        // Note: title/description are on moments, not media directly
        // This endpoint is for future metadata updates if needed

        return response()->json([
            'success' => true,
            'data' => $media->toArray(),
        ]);
    }

    /**
     * Delete media.
     */
    public function destroy(Media $media)
    {
        $this->authorize('delete', $media->kid);

        try {
            $media->deleteWithFiles();

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Media deletion failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete media',
            ], 500);
        }
    }

    /**
     * Serve a file from storage through authenticated proxy.
     */
    public function serveFile(Media $media, string $type)
    {
        $this->authorize('view', $media->kid);

        // Map type to path
        $pathMap = [
            'original' => $media->getRawFilePath(),
            'medium' => $media->getRawMediumUrl(),
            'thumbnail' => $media->getRawThumbnailUrl(),
            'hls' => $media->getRawHlsUrl(),
        ];

        if (!isset($pathMap[$type])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Use: original, medium, thumbnail, or hls',
            ], 400);
        }

        $path = $pathMap[$type];

        if (empty($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }

        try {
            $disk = Storage::disk('seaweedfs');

            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage',
                ], 404);
            }

            $fileContent = $disk->get($path);
            $fileSize = $disk->size($path);

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

            return response($fileContent, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Length', $fileSize)
                ->header('Cache-Control', 'private, max-age=31536000')
                ->header('Content-Disposition', 'inline');

        } catch (\Exception $e) {
            Log::error('Failed to serve file: ' . $e->getMessage(), [
                'media_id' => $media->id,
                'type' => $type,
                'path' => $path,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve file',
            ], 500);
        }
    }

    /**
     * Get list of uploaded identifiers for duplicate detection.
     */
    public function uploadedIdentifiers(Request $request, Kid $kid)
    {
        $this->authorize('view', $kid);

        $identifiers = Media::forKid($kid->id)
            ->where(function ($query) {
                $query->whereNotNull('device_asset_id')
                      ->orWhereNotNull('device_fingerprint');
            })
            ->get(['device_asset_id', 'device_fingerprint', 'file_size', 'original_filename'])
            ->map(function ($media) {
                return [
                    'device_asset_id' => $media->device_asset_id,
                    'device_fingerprint' => $media->device_fingerprint,
                    'file_size' => $media->file_size,
                    'filename' => $media->original_filename,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $identifiers,
        ]);
    }

    /**
     * Determine media type from MIME type.
     */
    private function determineMediaType(string $mimeType): ?string
    {
        $photoMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/heic',
            'image/heif',
        ];

        $videoMimes = [
            'video/mp4',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska',
            'video/webm',
        ];

        if (in_array($mimeType, $photoMimes)) {
            return Media::TYPE_PHOTO;
        }

        if (in_array($mimeType, $videoMimes)) {
            return Media::TYPE_VIDEO;
        }

        return null;
    }

    /**
     * Build EXIF data array from validated request.
     */
    private function buildExifData(array $validated): array
    {
        return array_filter([
            'exif_date' => $validated['exif_date'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'camera_make' => $validated['camera_make'] ?? null,
            'camera_model' => $validated['camera_model'] ?? null,
            'orientation' => $validated['orientation'] ?? null,
        ], fn($v) => !is_null($v));
    }

    /**
     * Process photo: generate thumbnail and medium sizes.
     */
    private function processPhoto(Media $media, $file, string $basePath): void
    {
        try {
            $thumbnailPath = "{$basePath}/thumbnail.jpg";
            $mediumPath = "{$basePath}/medium.jpg";

            // Generate medium (1200px width)
            $mediumImage = Image::read($file->getRealPath())
                ->orient()
                ->scaleDown(width: 1200)
                ->toJpeg(quality: 85);

            Storage::disk('seaweedfs')->put($mediumPath, (string) $mediumImage, 'private');

            Log::info('Medium photo processed', ['path' => $mediumPath]);

            // Generate thumbnail (300x300 cover)
            $thumbnailImage = Image::read($file->getRealPath())
                ->orient()
                ->cover(300, 300)
                ->toJpeg(quality: 80);

            Storage::disk('seaweedfs')->put($thumbnailPath, (string) $thumbnailImage, 'private');

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
     * Process video: dispatch to queue job.
     */
    private function processVideo(Media $media): void
    {
        // Dispatch job to process video (transcode to HLS, generate thumbnail)
        ProcessVideoJob::dispatch($media->id);
        
        Log::info('Video processing job dispatched', ['media_id' => $media->id]);
    }

    /**
     * Generate fingerprint for duplicate detection.
     */
    private function generateFingerprint($file): string
    {
        return md5_file($file->getRealPath());
    }
}
