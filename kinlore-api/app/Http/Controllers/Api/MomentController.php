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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified controller for all moment types (photo, video, story, milestone, growth).
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
                'moment' => $this->momentService->getMoment($result['moment'], $request->user()),
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
        return ApiResponse::success($this->momentService->getMoment($moment, $request->user()));
    }

    /**
     * Update a moment.
     */
    public function update(UpdateMomentRequest $request, Moment $moment): JsonResponse
    {
        $validated = $request->validated();

        try {
            $moment = $this->momentService->updateMoment($moment, $validated);

            return ApiResponse::success($this->momentService->getMoment($moment, $request->user()), 'Moment updated successfully');
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
            $headers = [
                'Content-Type' => $file['mime_type'],
                'Content-Length' => $file['size'],
                'Cache-Control' => 'private, max-age=3600',
            ];

            if (isset($file['stream'])) {
                return response()->stream(function () use ($file) {
                    fpassthru($file['stream']);
                    fclose($file['stream']);
                }, 200, $headers);
            }

            return response($file['content'], 200, $headers);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Signed HLS URL for the video player, valid for 6 hours.
     */
    public function playback(ShowMomentRequest $request, Moment $moment): JsonResponse
    {
        if (!$moment->isVideo()) {
            return ApiResponse::error('This moment is not a video', 404);
        }

        if (empty($moment->getRawOriginal('hls_url'))) {
            return ApiResponse::error('The video is not ready yet', 409);
        }

        return ApiResponse::success($this->momentService->playback($moment));
    }

    /**
     * Serve HLS master playlist (signed URL, no auth header).
     * iOS/Android video players cannot inject auth headers.
     */
    public function serveHlsPublic(Request $request, Moment $moment): Response
    {
        try {
            $file = $this->momentService->serveSignedHls($moment, null, (int) $request->query('expires'));

            return $this->hlsResponse($file);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Serve HLS variant playlists and .ts segments (signed URL, no auth header).
     */
    public function serveHlsFile(ServeMomentHlsFileRequest $request, Moment $moment, string $filename): Response
    {
        try {
            $file = $this->momentService->serveSignedHls($moment, $request->validated('filename'), (int) $request->query('expires'));

            return $this->hlsResponse($file);
        } catch (\RuntimeException $e) {
            Log::warning('HLS file not found', [
                'moment_id' => $moment->id,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            
            return ApiResponse::error('File not found', 404);
        }
    }

    private function hlsResponse(array $file): Response
    {
        return response($file['content'], 200, [
            'Content-Type' => $file['mime_type'],
            'Content-Length' => $file['size'],
            'Cache-Control' => 'private, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
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
            'video' => 'Video uploaded, processing started',
            'story' => 'Story created successfully',
            'milestone' => 'Milestone created successfully',
            'growth' => 'Growth measurement created successfully',
            default => 'Moment created successfully',
        };
    }
}
