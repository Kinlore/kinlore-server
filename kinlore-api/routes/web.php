<?php

declare(strict_types=1);

use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// Owner pages: the first admin is created here in a browser, never from the app
Route::get('/', [SetupController::class, 'home']);
Route::get('/setup', [SetupController::class, 'show']);
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:setup');

// Public page behind invitation links shared in message apps
Route::get('/invite/{token}', [InviteLinkController::class, 'show'])
    ->middleware('throttle:invite-page');
