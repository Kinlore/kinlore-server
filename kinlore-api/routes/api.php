<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KidController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\CustomMilestoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'kinlore-api',
    ]);
});

Route::get('/health/detailed', [HealthController::class, 'detailed'])->name('health.detailed')->middleware('auth:sanctum');

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
    });
});


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Get current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Kids routes
    Route::apiResource('kids', KidController::class);

    // Moments routes (scoped to kid)
    Route::prefix('kids/{kid}')->group(function () {
        Route::get('/moments', [PhotoController::class, 'index']);
        Route::post('/moments', [PhotoController::class, 'store']);
        Route::get('/moments/uploaded-identifiers', [PhotoController::class, 'uploadedIdentifiers']);
    });
    
    // Moment management
    Route::get('/moments/{moment}', [PhotoController::class, 'show']);
    Route::get('/moments/{moment}/file/{type}', [PhotoController::class, 'serveFile'])
        ->where('type', 'original|medium|thumbnail');
    Route::get('/moments/{moment}/file-status', [PhotoController::class, 'checkFileStatus']);
    Route::delete('/moments/{moment}', [PhotoController::class, 'destroy']);
    
    // Moment Interactions
    Route::post('/moments/{moment}/like', [\App\Http\Controllers\Api\MomentInteractionController::class, 'like']);
    Route::delete('/moments/{moment}/like', [\App\Http\Controllers\Api\MomentInteractionController::class, 'unlike']);
    Route::get('/moments/{moment}/comments', [\App\Http\Controllers\Api\MomentInteractionController::class, 'index']);
    Route::post('/moments/{moment}/comments', [\App\Http\Controllers\Api\MomentInteractionController::class, 'comment']);
    Route::delete('/interactions/{interaction}', [\App\Http\Controllers\Api\MomentInteractionController::class, 'deleteComment']);

    // Video routes
    Route::post('/videos/upload', [VideoController::class, 'upload']);
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{video}', [VideoController::class, 'show']);
    Route::get('/videos/{video}/status', [VideoController::class, 'status']);
    Route::delete('/videos/{video}', [VideoController::class, 'destroy']);
    Route::get('/videos/{video}/hls/{filename}', [VideoController::class, 'serveHLS'])
        ->where('filename', '.*');
    Route::get('/videos/{video}/thumbnail', [VideoController::class, 'serveThumbnail']);

    // Milestone routes (predefined/system milestones)
    Route::get('/milestones', [MilestoneController::class, 'index']);
    Route::get('/milestones/stage/{stageKey}', [MilestoneController::class, 'byStage']);

    // Custom milestone routes (user-created) - must come before /{identifier}
    Route::get('/milestones/custom', [CustomMilestoneController::class, 'index']);
    Route::post('/milestones/custom', [CustomMilestoneController::class, 'store']);
    Route::get('/milestones/custom/{customMilestone}', [CustomMilestoneController::class, 'show']);
    Route::put('/milestones/custom/{customMilestone}', [CustomMilestoneController::class, 'update']);
    Route::delete('/milestones/custom/{customMilestone}', [CustomMilestoneController::class, 'destroy']);

    // Get specific milestone by ID (after custom routes to avoid conflicts)
    Route::get('/milestones/{identifier}', [MilestoneController::class, 'show']);

    // Sync routes (WatermelonDB)
    Route::post('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
});