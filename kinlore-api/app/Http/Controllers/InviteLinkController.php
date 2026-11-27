<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\FamilyService;
use App\Support\PublicUrl;
use Illuminate\Http\Response;

/**
 * Public HTML page behind `invite_url`, for people who get the link in a message app.
 */
class InviteLinkController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly FamilyService $family,
    ) {}

    public function show(string $token): Response
    {
        $invitation = $this->authService->findPendingInvitation($token);

        return response()->view('invite', [
            // Only the holder of a valid invitation sees the family name.
            'instanceName' => $invitation ? $this->family->name() : config('app.name'),
            'invitation' => $invitation,
            'server' => PublicUrl::base(),
        ], $invitation ? 200 : 404);
    }
}
