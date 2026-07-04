<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Grant admins every ability without explicitly assigning each permission.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(UserRole::Admin->value) ? true : null;
        });

        // Every password rule that uses Password::defaults() (profile change, reset)
        // requires at least 8 characters with mixed case, a number, and a symbol.
        Password::defaults(function (): Password {
            return Password::min(8)->mixedCase()->numbers()->symbols();
        });
    }
}
