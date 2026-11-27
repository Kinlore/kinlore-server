<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Web\SetupRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\FamilyService;
use App\Support\PublicUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Web pages for the owner: the home page and the first-admin setup (docs/reference/api.md section 3).
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

        return view('setup', $this->pageData());
    }

    public function store(SetupRequest $request): View|RedirectResponse
    {
        // Quick exit for a second POST; createFirstAdmin re-checks inside the lock.
        if (User::exists()) {
            return redirect('/');
        }

        $data = $request->validated();
        $user = $this->authService->createFirstAdmin([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'family_name' => $data['friendly_name'] ?? null,
            'setup_token' => $data['setup_token'],
        ]);

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
            'server' => PublicUrl::base(),
        ];
    }
}
