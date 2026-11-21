<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\BuildExportJob;
use App\Models\Export;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Library export (docs/API_CONTRACT.md section 8). index and store are admin only (routes),
 * download is a signed URL handed out by index.
 */
class ExportController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Export::orderByDesc('created_at')->orderByDesc('id')->get()->map->toArray()->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (Export::whereIn('status', [Export::STATUS_PENDING, Export::STATUS_PROCESSING])->exists()) {
            return ApiResponse::error('An export is already running', 409);
        }

        $export = Export::create(['user_id' => $request->user()->id, 'status' => Export::STATUS_PENDING]);

        BuildExportJob::dispatch($export->id);

        return ApiResponse::success($export->fresh()->toArray(), 'Export started', 202);
    }

    public function download(Export $export): Response
    {
        // Only admins create exports; a family owner who lost the download permission cannot use old links.
        if ($export->user && !$export->user->hasPermission('download')) {
            return ApiResponse::error('You do not have permission to download.', 403);
        }

        $path = $export->fullPath();

        if ($export->status !== Export::STATUS_READY || !$path || !is_file($path)) {
            return ApiResponse::error('Export not found', 404);
        }

        return response()->download($path, 'kinlore-export-' . $export->created_at->format('Y-m-d') . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
