<?php

namespace App\Providers;

use App\Domain\Admin\Models\Admin;
use App\Domain\Shared\Http\ApiResponse;
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

        // Dual-Key Rate Limiter untuk Endpoint Login & Registrasi User
        RateLimiter::for('api-auth-login', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            $ip = $request->ip();

            $limits = [
                // Batas 1: Maksimal 10 request per menit per IP
                Limit::perMinute(10)
                    ->by($ip)
                    ->response(function () {
                        return ApiResponse::error(
                            'TOO_MANY_ATTEMPTS',
                            'Terlalu banyak percobaan login dari jaringan Anda. Silakan coba lagi dalam 1 menit.',
                            429
                        );
                    }),
            ];

            // Batas 2: Maksimal 5 percobaan gagal per 5 menit per nomor HP (Distributed Brute Force Protection)
            if ($phone !== '') {
                $limits[] = Limit::perMinutes(5, 5)
                    ->by($phone)
                    ->response(function () {
                        return ApiResponse::error(
                            'TOO_MANY_ATTEMPTS',
                            'Akun ini menerima terlalu banyak percobaan login. Akses ditahan sementara selama 5 menit demi keamanan.',
                            429
                        );
                    });
            }

            return $limits;
        });

        // Dual-Key Rate Limiter untuk Pengiriman OTP
        RateLimiter::for('api-auth-otp', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            $ip = $request->ip();

            $limits = [
                // Maksimal 5 permintaan OTP per menit per IP
                Limit::perMinute(5)
                    ->by($ip)
                    ->response(function () {
                        return ApiResponse::error(
                            'OTP_RATE_LIMIT_EXCEEDED',
                            'Terlalu banyak permintaan OTP dari jaringan Anda. Silakan tunggu sebentar.',
                            429
                        );
                    }),
            ];

            if ($phone !== '') {
                // Maksimal 3 permintaan OTP per 10 menit per nomor HP
                $limits[] = Limit::perMinutes(10, 3)
                    ->by($phone)
                    ->response(function () {
                        return ApiResponse::error(
                            'OTP_RATE_LIMIT_EXCEEDED',
                            'Batas permintaan OTP untuk nomor ini telah tercapai (maks 3x per 10 menit).',
                            429
                        );
                    });
            }

            return $limits;
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
