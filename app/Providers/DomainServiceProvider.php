<?php

namespace App\Providers;

use App\Domain\Auth\Contracts\OtpDriverInterface;
use App\Domain\Auth\Contracts\OtpSenderInterface;
use App\Domain\Auth\Drivers\FonnteOtpDriver;
use App\Domain\Auth\Drivers\WaBizOtpDriver;
use App\Domain\Auth\Models\User;
use App\Domain\Inquiry\Services\InquiryService;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Transaction\Services\TransactionService;
use App\Domain\Wallet\Drivers\FakeGatewayDriver;
use App\Domain\Wallet\Drivers\MidtransGatewayDriver;
use App\Domain\Wallet\Drivers\XenditGatewayDriver;
use App\Domain\Wallet\Services\TopupService;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\ServiceProvider;

/**
 * DomainServiceProvider — pendaftaran semua binding interface → implementasi.
 *
 * Setiap domain yang punya interface contract WAJIB didaftarkan di sini.
 * Ini adalah satu-satunya tempat "keputusan implementasi" diputuskan.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ─── Auth: OTP Driver (OtpDriverInterface — Phase 1) ──────────────────
        $this->app->bind(OtpDriverInterface::class, function () {
            return match (config('ppob.otp.driver')) {
                'wabiz' => $this->app->make(WaBizOtpDriver::class),
                default => $this->app->make(FonnteOtpDriver::class),
            };
        });

        // ─── Auth: OTP Sender (OtpSenderInterface — legacy alias) ─────────────
        $this->app->bind(OtpSenderInterface::class, function () {
            return match (config('ppob.otp.driver')) {
                'wabiz' => $this->app->make(WaBizOtpDriver::class),
                default => $this->app->make(FonnteOtpDriver::class),
            };
        });

        // ─── Wallet: Services & Payment Gateway ──────────────────────────────
        $this->app->singleton(WalletService::class);
        $this->app->singleton(TopupService::class);
        $this->app->singleton(FakeGatewayDriver::class);

        // Driver dipilih secara dinamis di TopupService berdasarkan request field
        // Daftarkan semua driver yang tersedia
        $this->app->bind('gateway.midtrans', MidtransGatewayDriver::class);
        $this->app->bind('gateway.xendit', XenditGatewayDriver::class);

        // ─── Inquiry & PPOB Services ──────────────────────────────────────────
        $this->app->singleton(CircuitBreakerService::class);
        $this->app->singleton(PpobService::class);
        $this->app->singleton(InquiryService::class);
        $this->app->singleton(TransactionService::class);
    }

    public function boot(): void
    {
        // Daftarkan model User domain sebagai provider autentikasi Sanctum
        config(['auth.providers.users.model' => User::class]);
    }
}
