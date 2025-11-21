<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\DartMatch;
use App\Models\League;
use App\Models\User;
use App\Policies\DartMatchPolicy;
use App\Policies\LeaguePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        DartMatch::class => DartMatchPolicy::class,
        League::class => LeaguePolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }
}
