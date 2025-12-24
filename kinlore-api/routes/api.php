<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KidController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\PredefinedMilestoneController;
use App\Http\Controllers\Api\UserMilestoneController;
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
    | Media Routes (Unified Photo/Video)
    |--------------------------------------------------------------------------
    */
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'store']);
        Route::get('/', [MediaController::class, 'index']);
        Route::get('/{media}', [MediaController::class, 'show']);
        Route::patch('/{media}', [MediaController::class, 'update']);
        Route::delete('/{media}', [MediaController::class, 'destroy']);
        Route::get('/{media}/file/{type}', [MediaController::class, 'serveFile'])
            ->where('type', 'original|medium|thumbnail|hls');
    });
    
    // Uploaded identifiers per kid (for duplicate detection)
    Route::get('/kids/{kid}/media/uploaded-identifiers', [MediaController::class, 'uploadedIdentifiers']);

    /*
    |--------------------------------------------------------------------------
    | Kids Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('kids', KidController::class);

    /*
    |--------------------------------------------------------------------------
    | Moments Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('kids/{kid}')->group(function () {
        Route::get('/moments', [PhotoController::class, 'index']);
        Route::post('/moments', [PhotoController::class, 'store']);
        Route::get('/moments/uploaded-identifiers', [PhotoController::class, 'uploadedIdentifiers']);
    });
    
    Route::get('/moments/{moment}', [PhotoController::class, 'show']);
    Route::get('/moments/{moment}/file/{type}', [PhotoController::class, 'serveFile'])
        ->where('type', 'original|medium|thumbnail');
    Route::get('/moments/{moment}/file-status', [PhotoController::class, 'checkFileStatus']);
    Route::delete('/moments/{moment}', [PhotoController::class, 'destroy']);
    
    // Moment Interactions
    Route::post('/moments/{moment}/like', [MomentInteractionController::class, 'like']);
    Route::delete('/moments/{moment}/like', [MomentInteractionController::class, 'unlike']);
    Route::get('/moments/{moment}/comments', [MomentInteractionController::class, 'index']);
    Route::post('/moments/{moment}/comments', [MomentInteractionController::class, 'comment']);
    Route::delete('/interactions/{interaction}', [MomentInteractionController::class, 'deleteComment']);

    /*
    |--------------------------------------------------------------------------
    | Stories Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('stories', StoryController::class);

    /*
    |--------------------------------------------------------------------------
    | Milestones Routes
    |--------------------------------------------------------------------------
    */
    // Predefined milestones (read-only, system-defined)
    Route::prefix('milestones/predefined')->group(function () {
        Route::get('/', [PredefinedMilestoneController::class, 'index']);
        Route::get('/{id}', [PredefinedMilestoneController::class, 'show']);
    });
    
    // User milestones (user's milestone achievements)
    Route::apiResource('milestones', UserMilestoneController::class)
        ->parameters(['milestones' => 'userMilestone']);

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
| Legacy Routes (Without v1 prefix - for backward compatibility)
| These routes maintain compatibility with existing clients
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Get current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Kids routes (legacy names to avoid conflict with v1 routes)
    Route::apiResource('kids', KidController::class)->names([
        'index' => 'legacy.kids.index',
        'store' => 'legacy.kids.store',
        'show' => 'legacy.kids.show',
        'update' => 'legacy.kids.update',
        'destroy' => 'legacy.kids.destroy',
    ]);

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
    Route::post('/moments/{moment}/like', [MomentInteractionController::class, 'like']);
    Route::delete('/moments/{moment}/like', [MomentInteractionController::class, 'unlike']);
    Route::get('/moments/{moment}/comments', [MomentInteractionController::class, 'index']);
    Route::post('/moments/{moment}/comments', [MomentInteractionController::class, 'comment']);
    Route::delete('/interactions/{interaction}', [MomentInteractionController::class, 'deleteComment']);

    // Video routes (legacy)
    Route::post('/videos/upload', [VideoController::class, 'upload']);
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{video}', [VideoController::class, 'show']);
    Route::get('/videos/{video}/status', [VideoController::class, 'status']);
    Route::delete('/videos/{video}', [VideoController::class, 'destroy']);
    Route::get('/videos/{video}/hls/{filename}', [VideoController::class, 'serveHLS'])
        ->where('filename', '.*');
    Route::get('/videos/{video}/thumbnail', [VideoController::class, 'serveThumbnail']);

    // Sync routes (WatermelonDB)
    Route::post('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
});