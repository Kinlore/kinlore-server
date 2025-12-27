<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KidController;
use App\Http\Controllers\Api\MomentController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PredefinedMilestoneController;
use App\Http\Controllers\Api\MomentInteractionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Routes (Public)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'kinlore-api',
    ]);
});

Route::get('/health/detailed', [HealthController::class, 'detailed'])
    ->name('health.detailed')
    ->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| API v1 Routes (Protected)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    
    // Get current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    /*
    |--------------------------------------------------------------------------
    | Kids Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('kids', KidController::class);

    /*
    |--------------------------------------------------------------------------
    | Unified Moments Routes (Photo, Video, Story, Milestone)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('moments', MomentController::class);
    Route::get('/moments/{moment}/file/{type}', [MomentController::class, 'serveFile'])
        ->where('type', 'original|medium|thumbnail|hls');
    Route::get('/moments/{moment}/file-status', [MomentController::class, 'checkFileStatus']);
    
    // Uploaded identifiers per kid (for duplicate detection)
    Route::get('/kids/{kid}/moments/uploaded-identifiers', [MomentController::class, 'uploadedIdentifiers']);
    
    // Moment Interactions
    Route::post('/moments/{moment}/like', [MomentInteractionController::class, 'like']);
    Route::delete('/moments/{moment}/like', [MomentInteractionController::class, 'unlike']);
    Route::get('/moments/{moment}/comments', [MomentInteractionController::class, 'index']);
    Route::post('/moments/{moment}/comments', [MomentInteractionController::class, 'comment']);
    Route::delete('/interactions/{interaction}', [MomentInteractionController::class, 'deleteComment']);

    /*
    |--------------------------------------------------------------------------
    | Media Routes (Raw media management - kept for direct media access)
    |--------------------------------------------------------------------------
    */
    Route::prefix('media')->group(function () {
        Route::get('/', [MediaController::class, 'index']);
        Route::get('/{media}', [MediaController::class, 'show']);
        Route::delete('/{media}', [MediaController::class, 'destroy']);
        Route::get('/{media}/file/{type}', [MediaController::class, 'serveFile'])
            ->where('type', 'original|medium|thumbnail|hls');
    });
    
    Route::get('/kids/{kid}/media/uploaded-identifiers', [MediaController::class, 'uploadedIdentifiers']);

    /*
    |--------------------------------------------------------------------------
    | Predefined Milestones (Read-only)
    |--------------------------------------------------------------------------
    */
    Route::prefix('milestones/predefined')->group(function () {
        Route::get('/', [PredefinedMilestoneController::class, 'index']);
        Route::get('/{id}', [PredefinedMilestoneController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sync Routes (WatermelonDB)
    |--------------------------------------------------------------------------
    */
    Route::post('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
});

/*
|--------------------------------------------------------------------------
| Legacy Routes (Backward compatibility - will be deprecated)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());

    // Legacy Kids
    Route::apiResource('kids', KidController::class)->names([
        'index' => 'legacy.kids.index',
        'store' => 'legacy.kids.store',
        'show' => 'legacy.kids.show',
        'update' => 'legacy.kids.update',
        'destroy' => 'legacy.kids.destroy',
    ]);

    // Legacy Moments (scoped to kid)
    Route::prefix('kids/{kid}')->group(function () {
        Route::get('/moments', [MomentController::class, 'index']);
        Route::post('/moments', [MomentController::class, 'store']);
        Route::get('/moments/uploaded-identifiers', [MomentController::class, 'uploadedIdentifiers']);
    });
    
    Route::get('/moments/{moment}', [MomentController::class, 'show']);
    Route::get('/moments/{moment}/file/{type}', [MomentController::class, 'serveFile'])
        ->where('type', 'original|medium|thumbnail');
    Route::get('/moments/{moment}/file-status', [MomentController::class, 'checkFileStatus']);
    Route::delete('/moments/{moment}', [MomentController::class, 'destroy']);
    
    // Moment Interactions
    Route::post('/moments/{moment}/like', [MomentInteractionController::class, 'like']);
    Route::delete('/moments/{moment}/like', [MomentInteractionController::class, 'unlike']);
    Route::get('/moments/{moment}/comments', [MomentInteractionController::class, 'index']);
    Route::post('/moments/{moment}/comments', [MomentInteractionController::class, 'comment']);
    Route::delete('/interactions/{interaction}', [MomentInteractionController::class, 'deleteComment']);

    // Sync routes
    Route::post('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
});