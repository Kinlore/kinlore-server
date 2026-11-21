<?php

use App\Http\Controllers\InviteLinkController;
use Illuminate\Support\Facades\Route;

Route::any('/', function () {
    //return view('welcome');
    return '';
});

// Public page behind invitation links shared in message apps
Route::get('/invite/{token}', [InviteLinkController::class, 'show'])
    ->middleware('throttle:10,1');
