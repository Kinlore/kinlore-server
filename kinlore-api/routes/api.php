<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KidController;
use App\Http\Controllers\Api\MomentController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\MomentInteractionController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ServerMemberController;
use App\Http\Controllers\Api\InvitationController;
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
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute
    
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
| Public HLS Streaming Routes (No Authentication Required)
|--------------------------------------------------------------------------
| iOS/Android video players cannot inject auth headers into HLS requests.
| These routes serve HLS files without authentication for streaming.
*/
Route::prefix('v1')->group(function () {
    // HLS master playlist
    Route::get('/moments/{moment}/file/hls', [MomentController::class, 'serveHlsPublic']);
    // HLS segment files (variant playlists and .ts segments)
    Route::get('/moments/{moment}/file/{filename}', [MomentController::class, 'serveHlsFile'])
        ->where('filename', '.*\.(m3u8|ts)$');
    
    // Media HLS routes
    Route::get('/media/{media}/file/hls', [MediaController::class, 'serveHlsPublic']);
    Route::get('/media/{media}/file/{filename}', [MediaController::class, 'serveHlsFile'])
        ->where('filename', '.*\.(m3u8|ts)$');
    
    // Public invitation validation (rate limited)
    Route::get('/invitations/validate/{token}', [InvitationController::class, 'validate'])
        ->middleware('throttle:20,1'); // 20 requests per minute
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
    | Server & Invitation Routes
    |--------------------------------------------------------------------------
    */
    // User's servers
    Route::get('/user/servers', [ServerController::class, 'userServers']);
    
    // Server management
    Route::apiResource('servers', ServerController::class);
    
    // Server members
    Route::get('/servers/{server}/members', [ServerMemberController::class, 'index']);
    Route::delete('/servers/{server}/members/{member}', [ServerMemberController::class, 'destroy']);
    
    // Invitations
    Route::get('/servers/{server}/invitations', [InvitationController::class, 'index']);
    Route::post('/servers/{server}/invitations', [InvitationController::class, 'store']);
    Route::delete('/servers/{server}/invitations/{invitation}', [InvitationController::class, 'destroy']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

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
    Route::get('moments', [MomentController::class, 'index']);
    Route::post('moments', [MomentController::class, 'store'])
        ->middleware('throttle:10,1'); // 10 uploads per minute
    Route::get('moments/{moment}', [MomentController::class, 'show']);
    Route::put('moments/{moment}', [MomentController::class, 'update']);
    Route::delete('moments/{moment}', [MomentController::class, 'destroy']);
    Route::get('/moments/{moment}/file/{type}', [MomentController::class, 'serveFile'])
        ->where('type', 'original|medium|thumbnail');
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
            ->where('type', 'original|medium|thumbnail');
    });
    
    Route::get('/kids/{kid}/media/uploaded-identifiers', [MediaController::class, 'uploadedIdentifiers']);

    /*
    |--------------------------------------------------------------------------
    | Milestones Routes (Predefined list)
    |--------------------------------------------------------------------------
    */
    Route::prefix('milestones')->group(function () {
        Route::get('/', [MilestoneController::class, 'index']);
        Route::get('/{id}', [MilestoneController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sync Routes (WatermelonDB)
    |--------------------------------------------------------------------------
    */
    Route::post('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
});