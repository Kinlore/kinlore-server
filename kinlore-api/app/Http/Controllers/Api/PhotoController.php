<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Moment;
use App\Services\MomentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PhotoController extends Controller
{
    public function __construct(
        private MomentService $momentService
    ) {}

    /**
     * Display a listing of moments for a kid.
     */
    public function index(Request $request, Kid $kid): JsonResponse
    {
        $this->authorize('view', $kid);

        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'device_asset_id' => 'nullable|string',
            'moment_type' => 'nullable|string|in:photo,video,story,milestone',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        $moments = $this->momentService->getMomentsForKid($kid, $validated);

        return response()->json([
            'success' => true,
            'data' => $moments->items(),
            'meta' => [
                'current_page' => $moments->currentPage(),
                'last_page' => $moments->lastPage(),
                'per_page' => $moments->perPage(),
                'total' => $moments->total(),
            ],
        ]);
    }

    /**
     * Display the specified moment.
     */
    public function show(Moment $moment): JsonResponse
    {
        $this->authorize('view', $moment->kid);

        return response()->json([
            'success' => true,
            'data' => $this->momentService->getMoment($moment),
        ]);
    }

    /**
     * Remove the specified moment.
     */
    public function destroy(Moment $moment): JsonResponse
    {
        $this->authorize('view', $moment->kid);

        try {
            $this->momentService->deleteMoment($moment);

            return response()->json([
                'success' => true,
                'message' => 'Moment deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Moment deletion failed', [
                'moment_id' => $moment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete moment',
            ], 500);
        }
    }

    /**
     * Serve a file from storage through authenticated proxy.
     */
    public function serveFile(Moment $moment, string $type): Response
    {
        $this->authorize('view', $moment->kid);

        try {
            $file = $this->momentService->serveFile($moment, $type);

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
     * Diagnostic endpoint to check file storage status.
     */
    public function checkFileStatus(Moment $moment): JsonResponse
    {
        $this->authorize('view', $moment->kid);

        $status = $this->momentService->checkFileStatus($moment);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Get list of uploaded identifiers for duplicate detection.
     */
    public function uploadedIdentifiers(Request $request, Kid $kid): JsonResponse
    {
        $this->authorize('view', $kid);

        $identifiers = $this->momentService->getUploadedIdentifiers($kid);

        return response()->json([
            'success' => true,
            'data' => $identifiers,
        ]);
    }
}
