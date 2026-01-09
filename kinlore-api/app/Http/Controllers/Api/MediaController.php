<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyMediaRequest;
use App\Http\Requests\Api\IndexMediaRequest;
use App\Http\Requests\Api\ServeMediaFileRequest;
use App\Http\Requests\Api\ServeMediaHlsFileRequest;
use App\Http\Requests\Api\ShowMediaRequest;
use App\Http\Requests\Api\StoreMediaRequest;
use App\Http\Requests\Api\UploadedMediaIdentifiersRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Kid;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
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
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kid = Kid::findOrFail($validated['kid_id']);

        try {
            $result = $this->mediaService->upload(
                $request->user(),
                $kid,
                $request->file('file'),
                $validated
            );

            $type = $result['media']->type;

            return ApiResponse::success(
                [
                    'media' => $result['media']->fresh()->toArray(),
                    'moment' => $result['moment']->fresh()->toArray(),
                ],
                $type === Media::TYPE_PHOTO
                    ? 'Photo uploaded successfully'
                    : 'Video uploaded successfully. Processing will begin shortly.',
                201
            );

        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Media upload failed: ' . $e->getMessage());
            
            return ApiResponse::error(
                'Upload failed',
                500,
                [],
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error']
            );
        }
    }

    /**
     * List media with optional filters.
     */
    public function index(IndexMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $media = $this->mediaService->getMediaForUser($request->user(), $validated);

        return ApiResponse::success(
            $media->items(),
            meta: [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ]
        );
    }

    /**
     * Get a single media item.
     */
    public function show(ShowMediaRequest $request, Media $media): JsonResponse
    {
        return ApiResponse::success($media->toArray());
    }

    /**
     * Delete media.
     */
    public function destroy(DestroyMediaRequest $request, Media $media): JsonResponse
    {
        try {
            $this->mediaService->deleteMedia($media);

            return ApiResponse::success(message: 'Media deleted successfully');
        } catch (\Exception $e) {
            Log::error('Media deletion failed: ' . $e->getMessage());
            
            return ApiResponse::error('Failed to delete media', 500);
        }
    }

    /**
     * Serve file through authenticated proxy.
     */
    public function serveFile(ServeMediaFileRequest $request, Media $media, string $type): Response
    {
        try {
            $file = $this->mediaService->serveFile($media, $type);

            return response($file['content'], 200, [
                'Content-Type' => $file['mime_type'],
                'Content-Length' => $file['size'],
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
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
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Serve HLS segment files (public, no auth required).
     * Serves variant playlists and .ts segments for video streaming.
     */
    public function serveHlsFile(ServeMediaHlsFileRequest $request, Media $media, string $filename): Response
    {
        try {
            $validated = $request->validated();
            $filename = $validated['filename'];
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
            
            return ApiResponse::error('File not found', 404);
        }
    }

    /**
     * Get uploaded identifiers for duplicate detection.
     */
    public function uploadedIdentifiers(UploadedMediaIdentifiersRequest $request, Kid $kid): JsonResponse
    {
        $identifiers = $this->mediaService->getUploadedIdentifiers($kid);

        return ApiResponse::success($identifiers);
    }
}
