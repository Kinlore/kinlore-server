<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateFamilyRequest;
use App\Http\Responses\ApiResponse;
use App\Services\FamilyService;
use Illuminate\Http\JsonResponse;

class FamilyController extends Controller
{
    /** Admin only (route middleware). Members get the new name with their next sync pull. */
    public function update(UpdateFamilyRequest $request, FamilyService $family): JsonResponse
    {
        return ApiResponse::success(['family_name' => $family->rename($request->validated('name'))]);
    }
}
