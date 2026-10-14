<?php

namespace App\Providers;

use App\Domain\Admin\Models\Admin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinutes(5, 5)
                ->by($request->ip())
                ->response(function (Request $request) {
                    return back()->withInput($request->only('email', 'remember'))->withErrors([
                        'email' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 5 menit.',
                    ]);
                });
        });

        // Gates for Admin Backoffice Panel
        Gate::define('access-finance', function ($admin) {
            return $admin instanceof Admin && ($admin->isSuperAdmin() || $admin->isFinance());
        });

        Gate::define('manage-admins', function ($admin) {
            return $admin instanceof Admin && $admin->isSuperAdmin();
        });

        Gate::define('manage-partners', function ($admin) {
            return $admin instanceof Admin && $admin->isSuperAdmin();
        });

        Gate::define('rotate-partner-keys', function ($admin) {
            return $admin instanceof Admin && $admin->isSuperAdmin();
        });

        Gate::define('toggle-providers', function ($admin) {
            return $admin instanceof Admin;
        });

        Gate::define('refund-transactions', function ($admin) {
            return $admin instanceof Admin;
        });

        Gate::define('view-audit-logs', function ($admin) {
            return $admin instanceof Admin;
        });
    }
}
