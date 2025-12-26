<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Services\KidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KidController extends Controller
{
    public function __construct(
        private KidService $kidService
    ) {}

    /**
     * Display a listing of the user's kids.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Kid::class);

        $kids = $this->kidService->getKidsForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $kids,
        ]);
    }

    /**
     * Store a newly created kid.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Kid::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'avatar_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $kid = $this->kidService->createKid($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kid created successfully',
            'data' => $this->kidService->formatKid($kid),
        ], 201);
    }

    /**
     * Display the specified kid.
     */
    public function show(Kid $kid): JsonResponse
    {
        $this->authorize('view', $kid);

        $kidDetails = $this->kidService->getKidDetails($kid);

        return response()->json([
            'success' => true,
            'data' => $kidDetails,
        ]);
    }

    /**
     * Update the specified kid.
     */
    public function update(Request $request, Kid $kid): JsonResponse
    {
        $this->authorize('update', $kid);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|before:today',
            'avatar_color' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $kid = $this->kidService->updateKid($kid, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kid updated successfully',
            'data' => $this->kidService->formatKid($kid),
        ]);
    }

    /**
     * Remove the specified kid.
     */
    public function destroy(Kid $kid): JsonResponse
    {
        $this->authorize('delete', $kid);

        $this->kidService->deleteKid($kid);

        return response()->json([
            'success' => true,
            'message' => 'Kid deleted successfully',
        ]);
    }
}
