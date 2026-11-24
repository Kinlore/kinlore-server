<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Web\SetupRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\FamilyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Web pages for the owner: the home page and the first-admin setup (docs/API_CONTRACT.md section 3).
 */
class SetupController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly FamilyService $family,
    ) {}

    public function home(): View|RedirectResponse
    {
        if (! User::exists()) {
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

        // The new admin sees the family name; the public pages keep APP_NAME.
        return view('setup-done', ['instanceName' => $this->family->name(), 'email' => $user->email] + $this->pageData());
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
