<?php

namespace App\Providers;

use App\Models\Kid;
use App\Models\User;
use App\Models\Moment;
use App\Policies\KidPolicy;
use App\Policies\MomentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Kid::class => KidPolicy::class,
        Moment::class => MomentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('admin', fn (User $user) => $user->role === 'admin');
    }
}
