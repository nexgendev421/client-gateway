<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('device-activation', fn (Request $request): Limit => Limit::perMinute(5)->by('device-activation|'.$request->ip()));
        RateLimiter::for('device-credentials', fn (Request $request): Limit => Limit::perMinute(10)->by('device-credentials|'.$request->ip()));
        RateLimiter::for('device-api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            'device-api|'.($request->attributes->get('device')?->getKey() ?? $request->ip()),
        ));

        Gate::before(function (User $user): ?bool {
            if (! $user->isActiveAdmin()) {
                return false;
            }

            return $user->role === AdminRole::SuperAdmin ? true : null;
        });

        Gate::define('view-router', fn (User $user): bool => in_array($user->role, [AdminRole::Support, AdminRole::ReadOnly], true));
        Gate::define('manage-router', fn (User $user): bool => false);
    }
}
