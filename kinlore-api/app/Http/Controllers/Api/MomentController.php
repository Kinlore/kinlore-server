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
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kid_id' => 'nullable|uuid|exists:kids,id',
            'moment_type' => 'nullable|in:photo,video,story,milestone',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $moments = $this->momentService->getMomentsForUser($request->user(), $validated);

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
     * Create a moment (unified endpoint for all types).
     *
     * Accepts:
     * - photo/video: multipart form with file upload
     * - story: JSON with title, text
     * - milestone: JSON with milestone data
     */
    public function store(Request $request): JsonResponse
    {
        // Determine validation rules based on content
        $hasFile = $request->hasFile('file');
        $momentType = $request->input('moment_type');

        // Base validation
        $rules = [
            'kid_id' => 'required|uuid|exists:kids,id',
            'moment_type' => 'nullable|in:photo,video,story,milestone',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
        ];

        // Add type-specific validation
        if ($hasFile || in_array($momentType, ['photo', 'video', null])) {
            $rules['file'] = 'required_without:moment_type|file|max:512000';
            $rules['device_asset_id'] = 'nullable|string|max:255';
            $rules['exif_date'] = 'nullable|date';
            $rules['latitude'] = 'nullable|numeric|between:-90,90';
            $rules['longitude'] = 'nullable|numeric|between:-180,180';
            $rules['camera_make'] = 'nullable|string|max:255';
            $rules['camera_model'] = 'nullable|string|max:255';
            $rules['orientation'] = 'nullable|integer|between:1,8';
        }

        if ($momentType === 'story') {
            $rules['title'] = 'required|string|max:255';
            $rules['text'] = 'required|string|max:50000';
            $rules['icon_image_id'] = 'nullable|uuid|exists:media,id';
        }

        if ($momentType === 'milestone') {
            $rules['predefined_milestone_id'] = 'nullable|string|exists:predefined_milestones,id';
            $rules['custom_title'] = 'required_without:predefined_milestone_id|nullable|string|max:255';
            $rules['custom_description'] = 'nullable|string|max:5000';
            $rules['milestone_date'] = 'required|date';
            $rules['notes'] = 'nullable|string|max:5000';
            $rules['icon_image_id'] = 'nullable|uuid|exists:media,id';
        }

        $validated = $request->validate($rules);

        $kid = Kid::findOrFail($validated['kid_id']);
        $this->authorize('view', $kid);

        // Validate icon image ownership if provided
        if (!empty($validated['icon_image_id'])) {
            if (!$this->momentService->validateIconImage($request->user(), $validated['icon_image_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid icon image',
                ], 422);
            }
        }

        // Validate predefined milestone if provided
        if (!empty($validated['predefined_milestone_id'])) {
            if (!$this->momentService->validatePredefinedMilestone($validated['predefined_milestone_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid predefined milestone',
                ], 422);
            }
        }

        try {
            $result = $this->momentService->create(
                $request->user(),
                $kid,
                $validated,
                $request->file('file')
            );

            $response = [
                'success' => true,
                'message' => $this->getSuccessMessage($result['moment']->moment_type),
                'data' => [
                    'moment' => $result['moment']->fresh()->toArray(),
                ],
            ];

            // Include related data based on type
            if (isset($result['media'])) {
                $response['data']['media'] = $result['media']->fresh()->toArray();
            }
            if (isset($result['story'])) {
                $response['data']['story'] = $result['story']->fresh()->toArray();
            }
            if (isset($result['milestone'])) {
                $response['data']['milestone'] = $result['milestone']->fresh()->toArray();
            }

            return response()->json($response, 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Moment creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create moment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a single moment.
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
     * Update a moment.
     */
    public function update(Request $request, Moment $moment): JsonResponse
    {
        $this->authorize('view', $moment->kid);

        $rules = [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
        ];

        // Add type-specific update rules
        if ($moment->moment_type === 'story') {
            $rules['text'] = 'nullable|string|max:50000';
        }

        if ($moment->moment_type === 'milestone') {
            $rules['custom_title'] = 'nullable|string|max:255';
            $rules['custom_description'] = 'nullable|string|max:5000';
            $rules['milestone_date'] = 'nullable|date';
            $rules['notes'] = 'nullable|string|max:5000';
        }

        $validated = $request->validate($rules);

        try {
            $moment = $this->momentService->updateMoment($moment, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Moment updated successfully',
                'data' => $moment->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Moment update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update moment',
            ], 500);
        }
    }

    /**
     * Delete a moment.
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
            Log::error('Moment deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete moment',
            ], 500);
        }
    }

    /**
     * Serve file through authenticated proxy.
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
     * Diagnostic endpoint to check file status.
     */
    public function checkFileStatus(Moment $moment): JsonResponse
    {
        $this->authorize('view', $moment->kid);

        return response()->json([
            'success' => true,
            'data' => $this->momentService->checkFileStatus($moment),
        ]);
    }

    /**
     * Get uploaded identifiers for duplicate detection.
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

    /**
     * Get success message based on moment type.
     */
    private function getSuccessMessage(string $type): string
    {
        return match($type) {
            'photo' => 'Photo uploaded successfully',
            'video' => 'Video uploaded successfully. Processing will begin shortly.',
            'story' => 'Story created successfully',
            'milestone' => 'Milestone created successfully',
            default => 'Moment created successfully',
        };
    }
}
