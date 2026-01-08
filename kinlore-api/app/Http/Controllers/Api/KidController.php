<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyKidRequest;
use App\Http\Requests\Api\IndexKidRequest;
use App\Http\Requests\Api\ShowKidRequest;
use App\Http\Requests\Api\StoreKidRequest;
use App\Http\Requests\Api\UpdateKidRequest;
use App\Models\Kid;
use App\Services\KidService;
use Illuminate\Http\JsonResponse;

class KidController extends Controller
{
    public function __construct(
        private KidService $kidService
    ) {}

    /**
     * Display a listing of the user's kids.
     */
    public function index(IndexKidRequest $request): JsonResponse
    {
        $kids = $this->kidService->getKidsForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $kids,
        ]);
    }

    /**
     * Store a newly created kid.
     */
    public function store(StoreKidRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function show(ShowKidRequest $request, Kid $kid): JsonResponse
    {
        $kidDetails = $this->kidService->getKidDetails($kid);

        return response()->json([
            'success' => true,
            'data' => $kidDetails,
        ]);
    }

    /**
     * Update the specified kid.
     */
    public function update(UpdateKidRequest $request, Kid $kid): JsonResponse
    {
        $validated = $request->validated();

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
    public function destroy(DestroyKidRequest $request, Kid $kid): JsonResponse
    {
        $this->kidService->deleteKid($kid);

        return response()->json([
            'success' => true,
            'message' => 'Kid deleted successfully',
        ]);
    }
}
