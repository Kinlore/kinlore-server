<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    private const int TOKEN_IDLE_DAYS = 90;

    #[\Override]
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->role === Role::Admin);

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('sync', fn (Request $request): Limit => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        // Named limiters count apart; plain throttle:N,M routes all share one counter per IP.
        foreach (['login' => 5, 'register' => 10, 'invitation-check' => 10, 'setup' => 5, 'invite-page' => 10] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($perMinute)->by($request->ip()));
        }

        // No fixed lifetime (the app has no refresh flow): a token ends after this many days without use.
        Sanctum::authenticateAccessTokensUsing(fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
            && ($token->last_used_at ?? $token->created_at)->gt(now()->subDays(self::TOKEN_IDLE_DAYS)));
    }
}
