<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\SetupRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Web pages for the owner: the home page and the first-admin setup (docs/API_CONTRACT.md section 3).
 */
class SetupController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function home(): View|RedirectResponse
    {
        if (!User::exists()) {
            return redirect('/setup');
        }

        return view('home', $this->pageData());
    }

    public function show(): View|RedirectResponse
    {
        if (User::exists()) {
            return redirect('/');
        }

        return view('setup', $this->pageData() + [
            'setupTokenRequired' => filled(config('app.setup_token')),
        ]);
    }

    public function store(SetupRequest $request): View|RedirectResponse
    {
        // Quick exit for a second POST; createFirstAdmin re-checks inside the lock.
        if (User::exists()) {
            return redirect('/');
        }

        $user = $this->authService->createFirstAdmin($request->validated());

        return view('setup-done', $this->pageData() + ['email' => $user->email]);
    }

    /**
     * @return array{instanceName: string, server: string}
     */
    private function pageData(): array
    {
        return [
            'instanceName' => config('app.name'),
            'server' => config('app.url') ?: url('/'),
        ];
    }
}
