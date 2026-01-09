<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckMomentFileStatusRequest;
use App\Http\Requests\Api\DestroyMomentRequest;
use App\Http\Requests\Api\IndexMomentRequest;
use App\Http\Requests\Api\ServeMomentFileRequest;
use App\Http\Requests\Api\ServeMomentHlsFileRequest;
use App\Http\Requests\Api\ShowMomentRequest;
use App\Http\Requests\Api\StoreMomentRequest;
use App\Http\Requests\Api\UpdateMomentRequest;
use App\Http\Requests\Api\UploadedMomentIdentifiersRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Kid;
use App\Models\Moment;
use App\Services\MomentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified controller for all moment types (photo, video, story, milestone).
 */
class MomentController extends Controller
{
    public function __construct(
        private MomentService $momentService
    ) {}

    /**
     * List moments with optional filters.
     */
    public function index(IndexMomentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $moments = $this->momentService->getMomentsForUser($request->user(), $validated);

        return ApiResponse::success(
            $moments->items(),
            meta: [
                'current_page' => $moments->currentPage(),
                'last_page' => $moments->lastPage(),
                'per_page' => $moments->perPage(),
                'total' => $moments->total(),
            ]
        );
    }

    /**
     * Create a moment (unified endpoint for all types).
     *
     * Accepts:
     * - photo/video: multipart form with file upload
     * - story: JSON with title, text
     * - milestone: JSON with milestone data
     */
    public function store(StoreMomentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kid = Kid::findOrFail($validated['kid_id']);

        try {
            $result = $this->momentService->create(
                $request->user(),
                $kid,
                $validated,
                $request->file('file')
            );

            $response = [
                'moment' => $result['moment']->fresh()->toArray(),
            ];

            // Include media data for photo/video types
            if (isset($result['media'])) {
                $response['media'] = $result['media']->fresh()->toArray();
            }

            return ApiResponse::success(
                $response,
                $this->getSuccessMessage($result['moment']->moment_type),
                201
            );

        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Moment creation failed: ' . $e->getMessage());

            return ApiResponse::error(
                'Failed to create moment',
                500,
                [],
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error']
            );
        }
    }

    /**
     * Get a single moment.
     */
    public function show(ShowMomentRequest $request, Moment $moment): JsonResponse
    {
        return ApiResponse::success($this->momentService->getMoment($moment));
    }

    /**
     * Update a moment.
     */
    public function update(UpdateMomentRequest $request, Moment $moment): JsonResponse
    {
        $validated = $request->validated();

        try {
            $moment = $this->momentService->updateMoment($moment, $validated);

            return ApiResponse::success($moment->toArray(), 'Moment updated successfully');
        } catch (\Exception $e) {
            Log::error('Moment update failed: ' . $e->getMessage());

            return ApiResponse::error('Failed to update moment', 500);
        }
    }

    /**
     * Delete a moment.
     */
    public function destroy(DestroyMomentRequest $request, Moment $moment): JsonResponse
    {
        try {
            $this->momentService->deleteMoment($moment);

            return ApiResponse::success(message: 'Moment deleted successfully');
        } catch (\Exception $e) {
            Log::error('Moment deletion failed: ' . $e->getMessage());

            return ApiResponse::error('Failed to delete moment', 500);
        }
    }

    /**
     * Serve file through authenticated proxy.
     */
    public function serveFile(ServeMomentFileRequest $request, Moment $moment, string $type): Response
    {
        try {
            $file = $this->momentService->serveFile($moment, $type);

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
    public function serveHlsPublic(Moment $moment): Response
    {
        try {
            $file = $this->momentService->serveFile($moment, 'hls');

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
    public function serveHlsFile(ServeMomentHlsFileRequest $request, Moment $moment, string $filename): Response
    {
        try {
            $validated = $request->validated();
            $filename = $validated['filename'];
            $file = $this->momentService->serveHlsFile($moment, $filename);

            return response($file['content'], 200, [
                'Content-Type' => $file['mime_type'],
                'Content-Length' => $file['size'],
                'Cache-Control' => 'public, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('HLS file not found', [
                'moment_id' => $moment->id,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            
            return ApiResponse::error('File not found', 404);
        }
    }

    /**
     * Diagnostic endpoint to check file status.
     */
    public function checkFileStatus(CheckMomentFileStatusRequest $request, Moment $moment): JsonResponse
    {
        return ApiResponse::success($this->momentService->checkFileStatus($moment));
    }

    /**
     * Get uploaded identifiers for duplicate detection.
     */
    public function uploadedIdentifiers(UploadedMomentIdentifiersRequest $request, Kid $kid): JsonResponse
    {
        $identifiers = $this->momentService->getUploadedIdentifiers($kid);

        return ApiResponse::success($identifiers);
    }

    /**
     * Get success message based on moment type.
     */
    private function getSuccessMessage(string $type): string
    {
        return match($type) {
            'photo' => 'Photo uploaded successfully',
            'video' => 'Video uploaded and processed successfully',
            'story' => 'Story created successfully',
            'milestone' => 'Milestone created successfully',
            default => 'Moment created successfully',
        };
    }
}
