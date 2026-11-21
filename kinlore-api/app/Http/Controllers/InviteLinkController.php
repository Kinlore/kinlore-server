<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Response;

/**
 * Public HTML page behind `invite_url`, for people who get the link in a message app.
 */
class InviteLinkController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function show(string $token): Response
    {
        $invitation = $this->authService->findPendingInvitation($token);

        return response()->view('invite', [
            'instanceName' => config('app.name'),
            'invitation' => $invitation,
            'server' => config('app.url'),
        ], $invitation ? 200 : 404);
    }
}
