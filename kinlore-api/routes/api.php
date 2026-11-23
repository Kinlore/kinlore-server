<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\KidController;
use App\Http\Controllers\Api\LookbackController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\MomentController;
use App\Http\Controllers\Api\MomentInteractionController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Routes (Public)
|--------------------------------------------------------------------------
*/
Route::get('/health', fn (): JsonResponse => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
    'service' => 'kinlore-api',
]));

Route::get('/health/detailed', [HealthController::class, 'detailed'])
    ->name('health.detailed')
    ->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::get('/setup-status', [AuthController::class, 'setupStatus']);
    Route::get('/invitations/{token}', [AuthController::class, 'checkInvitation'])
        ->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
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
| Signed Routes (No Authorization Header)
|--------------------------------------------------------------------------
| Video players cannot send auth headers with HLS requests, and a browser
| download of an export cannot either. These routes need a valid, unexpired
| signature instead (see docs/API_CONTRACT.md, sections 6 and 8).
*/
// No per-minute API limit here: a video player fetches many segments, and the signature already limits access.
Route::prefix('v1')->middleware('signed')->withoutMiddleware('throttle:api')->group(function () {
    Route::get('/moments/{moment}/file/hls', [MomentController::class, 'serveHlsPublic'])
        ->name('moments.hls.master');
    Route::get('/moments/{moment}/file/{filename}', [MomentController::class, 'serveHlsFile'])
        ->where('filename', '.*\.(m3u8|ts)$')
        ->name('moments.hls.file');

    Route::get('/exports/{export}/download', [ExportController::class, 'download'])
        ->name('exports.download');
});

/*
|--------------------------------------------------------------------------
| API v1 Routes (Protected)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

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
        ->middleware('throttle:60,1'); // bulk uploads from the gallery
    Route::get('moments/{moment}', [MomentController::class, 'show']);
    Route::put('moments/{moment}', [MomentController::class, 'update']);
    Route::delete('moments/{moment}', [MomentController::class, 'destroy']);
    // A timeline loads many thumbnails at once, so image files skip the per-minute API limit.
    Route::get('/moments/{moment}/file/{type}', [MomentController::class, 'serveFile'])
        ->where('type', 'original|medium|thumbnail')
        ->withoutMiddleware('throttle:api');
    Route::get('/moments/{moment}/file-status', [MomentController::class, 'checkFileStatus']);
    Route::get('/moments/{moment}/playback', [MomentController::class, 'playback']);

    // Look-back: on this day, then and now
    Route::get('/lookback', [LookbackController::class, 'index']);

    // Uploaded identifiers per kid (for duplicate detection)
    Route::get('/kids/{kid}/moments/uploaded-identifiers', [MomentController::class, 'uploadedIdentifiers']);

    // Invitations (instance admin only)
    Route::middleware('can:admin')->group(function () {
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::patch('/members/{user}', [MemberController::class, 'update']);
        Route::delete('/members/{user}', [MemberController::class, 'destroy']);

        Route::get('/exports', [ExportController::class, 'index']);
        Route::post('/exports', [ExportController::class, 'store']);
    });

    // Moment Interactions
    Route::post('/moments/{moment}/like', [MomentInteractionController::class, 'like']);
    Route::delete('/moments/{moment}/like', [MomentInteractionController::class, 'unlike']);
    Route::get('/moments/{moment}/comments', [MomentInteractionController::class, 'index']);
    Route::post('/moments/{moment}/comments', [MomentInteractionController::class, 'comment']);
    Route::delete('/interactions/{interaction}', [MomentInteractionController::class, 'deleteComment']);

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
    | Sync Routes (mobile offline database)
    |--------------------------------------------------------------------------
    */
    Route::post('/sync/pull', [SyncController::class, 'pull'])
        ->middleware('throttle:sync')
        ->withoutMiddleware('throttle:api');
    Route::post('/sync/push', [SyncController::class, 'push'])
        ->middleware('throttle:sync')
        ->withoutMiddleware('throttle:api');
});
