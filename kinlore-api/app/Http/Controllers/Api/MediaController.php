<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * Upload media (photo or video) with unified endpoint.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:512000', // 500MB
            'kid_id' => 'required|uuid|exists:kids,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'device_asset_id' => 'nullable|string|max:255',
            'exif_date' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'camera_make' => 'nullable|string|max:255',
            'camera_model' => 'nullable|string|max:255',
            'orientation' => 'nullable|integer|between:1,8',
        ]);

        $kid = Kid::findOrFail($validated['kid_id']);
        $this->authorize('view', $kid);

        try {
            $result = $this->mediaService->upload(
                $request->user(),
                $kid,
                $request->file('file'),
                $validated
            );

            $type = $result['media']->type;

            return response()->json([
                'success' => true,
                'message' => $type === Media::TYPE_PHOTO 
                    ? 'Photo uploaded successfully' 
                    : 'Video uploaded successfully. Processing will begin shortly.',
                'data' => [
                    'media' => $result['media']->fresh()->toArray(),
                    'moment' => $result['moment']->fresh()->toArray(),
                ],
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Media upload failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * List media with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kid_id' => 'nullable|uuid|exists:kids,id',
            'type' => 'nullable|in:photo,video',
            'status' => 'nullable|in:uploading,processing,completed,failed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $media = $this->mediaService->getMediaForUser($request->user(), $validated);

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
     * Get a single media item.
     */
    public function show(Media $media): JsonResponse
    {
        $this->authorize('view', $media->kid);

        return response()->json([
            'success' => true,
            'data' => $media->toArray(),
        ]);
    }

    /**
     * Delete media.
     */
    public function destroy(Media $media): JsonResponse
    {
        $this->authorize('view', $media->kid);

        try {
            $this->mediaService->deleteMedia($media);

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Media deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete media',
            ], 500);
        }
    }

    /**
     * Serve file through authenticated proxy.
     */
    public function serveFile(Media $media, string $type): Response
    {
        $this->authorize('view', $media->kid);

        try {
            $file = $this->mediaService->serveFile($media, $type);

            return response($file['content'], 200, [
                'Content-Type' => $file['mime_type'],
                'Content-Length' => $file['size'],
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Serve HLS master playlist (public, no auth required).
     * iOS/Android video players cannot inject auth headers.
     */
    public function serveHlsPublic(Media $media): Response
    {
        try {
            $file = $this->mediaService->serveFile($media, 'hls');

            return response($file['content'], 200, [
                'Content-Type' => $file['mime_type'],
                'Content-Length' => $file['size'],
                'Cache-Control' => 'public, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Serve HLS segment files (public, no auth required).
     * Serves variant playlists and .ts segments for video streaming.
     */
    public function serveHlsFile(Media $media, string $filename): Response
    {
        try {
            $file = $this->mediaService->serveHlsFile($media, $filename);

            return response($file['content'], 200, [
                'Content-Type' => $file['mime_type'],
                'Content-Length' => $file['size'],
                'Cache-Control' => 'public, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('HLS file not found', [
                'media_id' => $media->id,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get uploaded identifiers for duplicate detection.
     */
    public function uploadedIdentifiers(Request $request, Kid $kid): JsonResponse
    {
        $this->authorize('view', $kid);

        $identifiers = $this->mediaService->getUploadedIdentifiers($kid);

        return response()->json([
            'success' => true,
            'data' => $identifiers,
        ]);
    }
}
