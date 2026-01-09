<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestroyKidRequest;
use App\Http\Requests\Api\IndexKidRequest;
use App\Http\Requests\Api\ShowKidRequest;
use App\Http\Requests\Api\StoreKidRequest;
use App\Http\Requests\Api\UpdateKidRequest;
use App\Http\Responses\ApiResponse;
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

        return ApiResponse::success($kids);
    }

    /**
     * Store a newly created kid.
     */
    public function store(StoreKidRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kid = $this->kidService->createKid($request->user(), $validated);

        return ApiResponse::success(
            $this->kidService->formatKid($kid),
            'Kid created successfully',
            201
        );
    }

    /**
     * Display the specified kid.
     */
    public function show(ShowKidRequest $request, Kid $kid): JsonResponse
    {
        $kidDetails = $this->kidService->getKidDetails($kid);

        return ApiResponse::success($kidDetails);
    }

    /**
     * Update the specified kid.
     */
    public function update(UpdateKidRequest $request, Kid $kid): JsonResponse
    {
        $validated = $request->validated();

        $kid = $this->kidService->updateKid($kid, $validated);

        return ApiResponse::success(
            $this->kidService->formatKid($kid),
            'Kid updated successfully'
        );
    }

    /**
     * Remove the specified kid.
     */
    public function destroy(DestroyKidRequest $request, Kid $kid): JsonResponse
    {
        $this->kidService->deleteKid($kid);

        return ApiResponse::success(message: 'Kid deleted successfully');
    }
}
