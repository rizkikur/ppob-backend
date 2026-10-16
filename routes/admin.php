<?php

use App\Domain\Admin\Http\Controllers\AuditLog\AdminAuditLogController;
use App\Domain\Admin\Http\Controllers\Auth\AdminLoginController;
use App\Domain\Admin\Http\Controllers\Auth\AdminTwoFactorController;
use App\Domain\Admin\Http\Controllers\Dashboard\AdminDashboardController;
use App\Domain\Admin\Http\Controllers\Finance\AdminLedgerMutationController;
use App\Domain\Admin\Http\Controllers\Finance\AdminReconciliationController;
use App\Domain\Admin\Http\Controllers\Finance\AdminTopupApprovalController;
use App\Domain\Admin\Http\Controllers\Finance\AdminWalletAdjustmentController;
use App\Domain\Admin\Http\Controllers\Partner\AdminPartnerController;
use App\Domain\Admin\Http\Controllers\Partner\AdminPartnerSecurityController;
use App\Domain\Admin\Http\Controllers\Partner\AdminPartnerWebhookController;
use App\Domain\Admin\Http\Controllers\Product\AdminCategoryController;
use App\Domain\Admin\Http\Controllers\Product\AdminOperatorController;
use App\Domain\Admin\Http\Controllers\Product\AdminProductController;
use App\Domain\Admin\Http\Controllers\Product\AdminProductPriceController;
use App\Domain\Admin\Http\Controllers\Profile\AdminProfileController;
use App\Domain\Admin\Http\Controllers\Provider\AdminCircuitBreakerController;
use App\Domain\Admin\Http\Controllers\Provider\AdminProviderController;
use App\Domain\Admin\Http\Controllers\Provider\AdminRoutingPriorityController;
use App\Domain\Admin\Http\Controllers\Security\ConfirmPasswordController;
use App\Domain\Admin\Http\Controllers\Transaction\AdminTransactionController;
use App\Domain\Admin\Http\Controllers\Transaction\AdminTransactionExportController;
use App\Domain\Admin\Http\Controllers\Transaction\AdminTransactionRefundController;
use App\Domain\Admin\Http\Controllers\Transaction\AdminTransactionRequeryController;
use App\Domain\Admin\Http\Controllers\User\AdminUserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$prefix = config('admin.prefix', 'office');
$domain = config('admin.domain');

$adminRoute = Route::prefix($prefix)->name('admin.');

if (! empty($domain)) {
    $adminRoute->domain($domain);
}

$adminRoute->group(function () {
    // Admin Root / Landing
    Route::get('/', function () {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('admin.login');
    })->name('index');

    // Guest Routes
    Route::middleware(['guest:admin'])->group(function () {
        Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'login'])
            ->middleware('throttle:admin-login')
            ->name('login.submit');
    });

    // 2FA Challenge Route
    Route::middleware(['auth:admin'])->group(function () {
        Route::get('/two-factor', [AdminTwoFactorController::class, 'showForm'])->name('two-factor.form');
        Route::post('/two-factor', [AdminTwoFactorController::class, 'verify'])->name('two-factor.verify');
    });

    // Authenticated Admin Routes
    Route::middleware(['auth:admin', 'admin.2fa', 'admin.csp'])->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/metrics', [AdminDashboardController::class, 'metrics'])->name('dashboard.metrics');
        Route::get('/dashboard/stream', [AdminDashboardController::class, 'stream'])->name('dashboard.stream');

        // Transactions Management & Manual Interventions
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [AdminTransactionController::class, 'index'])->name('index');
            Route::get('/stream', [AdminTransactionController::class, 'stream'])->name('stream');

            // Asynchronous CSV Export & Tracking
            Route::post('/export/async', [AdminTransactionExportController::class, 'store'])->name('export.async');
            Route::get('/export/status/{job}', [AdminTransactionExportController::class, 'status'])->name('export.status');
            Route::get('/export/download/{job}', [AdminTransactionExportController::class, 'download'])->name('export.download');
            Route::get('/export/recent', [AdminTransactionExportController::class, 'recent'])->name('export.recent');

            Route::get('/{transaction}', [AdminTransactionController::class, 'show'])->name('show');
            Route::post('/{transaction}/requery', [AdminTransactionRequeryController::class, 'requery'])->name('requery');
            Route::post('/{transaction}/override-status', [AdminTransactionController::class, 'overrideStatus'])->name('override');
            Route::post('/{transaction}/refund', [AdminTransactionRefundController::class, 'refund'])
                ->middleware('password.confirm:admin.password.confirm')
                ->name('refund');
            Route::post('/{transaction}/retry-webhook', [AdminTransactionController::class, 'retryWebhook'])
                ->name('webhook.retry');
        });

        // Products & Categories & Operators Catalog Management
        Route::resource('categories', AdminCategoryController::class)->except(['show', 'create', 'edit']);
        Route::post('operators/detect', [AdminOperatorController::class, 'detect'])->name('operators.detect');
        Route::resource('operators', AdminOperatorController::class)->except(['show', 'create', 'edit']);
        Route::post('products/sync-provider', [AdminProductController::class, 'syncPricelist'])->name('products.sync');
        Route::post('products/bulk/status', [AdminProductController::class, 'bulkStatus'])->name('products.bulk.status');
        Route::post('products/bulk/prices', [AdminProductPriceController::class, 'bulkUpdatePrices'])->name('products.bulk.prices');
        Route::post('products/{product}/toggle', [AdminProductController::class, 'toggleStatus'])->name('products.toggle');
        Route::post('products/{product}/routes', [AdminProductController::class, 'updateRoutes'])->name('products.routes.update');
        Route::resource('products', AdminProductController::class);
        Route::put('products/{product}/prices', [AdminProductPriceController::class, 'updatePrices'])
            ->name('products.prices.update');

        // Providers & Routing Management
        Route::prefix('providers')->name('providers.')->group(function () {
            Route::get('/', [AdminProviderController::class, 'index'])->name('index');
            Route::get('/{provider}', [AdminProviderController::class, 'show'])->name('show');
            Route::put('/{provider}', [AdminProviderController::class, 'update'])->name('update');
            Route::post('/{provider}/ping', [AdminProviderController::class, 'ping'])->name('ping');
            Route::post('/{provider}/check-balance', [AdminProviderController::class, 'checkBalance'])->name('check_balance');
            Route::post('/{provider}/sync-products', [AdminProviderController::class, 'syncUpstreamProducts'])->name('sync_products');
            Route::post('/{provider}/error-mappings', [AdminProviderController::class, 'storeErrorMapping'])->name('error_mappings.store');
            Route::delete('/{provider}/error-mappings/{mapping}', [AdminProviderController::class, 'destroyErrorMapping'])->name('error_mappings.destroy');
            Route::post('/{provider}/toggle', [AdminProviderController::class, 'toggleStatus'])->name('toggle');
            Route::post('/{provider}/circuit-breaker/reset', [AdminCircuitBreakerController::class, 'reset'])
                ->name('circuit_breaker.reset');
            Route::put('/{provider}/circuit-breaker/config', [AdminCircuitBreakerController::class, 'updateConfig'])
                ->name('circuit_breaker.config');
        });
        Route::post('routing/priority', [AdminRoutingPriorityController::class, 'update'])
            ->name('routing.priority.update');

        // Partners Management (B2B / H2H)
        Route::prefix('partners')->name('partners.')->group(function () {
            Route::get('/', [AdminPartnerController::class, 'index'])->name('index');
            Route::get('/create', [AdminPartnerController::class, 'create'])->name('create');
            Route::post('/', [AdminPartnerController::class, 'store'])->name('store');
            Route::get('/{partner}', [AdminPartnerController::class, 'show'])->name('show');
            Route::get('/{partner}/edit', [AdminPartnerController::class, 'edit'])->name('edit');
            Route::put('/{partner}', [AdminPartnerController::class, 'update'])->name('update');
            Route::post('/{partner}/toggle', [AdminPartnerController::class, 'toggleStatus'])->name('toggle');
            Route::post('/{partner}/adjust-balance', [AdminPartnerController::class, 'adjustBalance'])->name('adjust_balance');
            Route::post('/{partner}/pricing', [AdminPartnerController::class, 'updatePricing'])->name('pricing.update');
            Route::delete('/{partner}/pricing/{product}', [AdminPartnerController::class, 'destroyPricing'])->name('pricing.destroy');

            // Security & Credentials (Password Confirmation required for rotation)
            Route::middleware(['password.confirm:admin.password.confirm'])->group(function () {
                Route::post('/{partner}/rotate-api-key', [AdminPartnerSecurityController::class, 'rotateApiKey'])
                    ->name('rotate_key');
                Route::post('/{partner}/rotate-api-secret', [AdminPartnerSecurityController::class, 'rotateApiSecret'])
                    ->name('rotate_secret');
            });

            // IP Whitelisting
            Route::post('/{partner}/ip-whitelist', [AdminPartnerSecurityController::class, 'addIp'])
                ->name('ip.store');
            Route::delete('/{partner}/ip-whitelist/{ip}', [AdminPartnerSecurityController::class, 'removeIp'])
                ->name('ip.destroy');

            // Webhook Ping Test & Retry
            Route::post('/{partner}/webhooks/ping', [AdminPartnerWebhookController::class, 'ping'])
                ->name('webhooks.ping');
            Route::post('/webhooks/{delivery}/retry', [AdminPartnerWebhookController::class, 'retry'])
                ->name('webhooks.retry');

            // Partner Routing & Failover Rules (ADR-004)
            Route::post('/{partner}/routing-rules', [AdminPartnerController::class, 'storeRoutingRule'])
                ->name('routing_rules.store');
            Route::delete('/{partner}/routing-rules/{rule}', [AdminPartnerController::class, 'destroyRoutingRule'])
                ->name('routing_rules.destroy');
        });

        // Mobile App Users Management (B2C / Agen Retail)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
            Route::post('/{user}/toggle', [AdminUserController::class, 'toggleStatus'])->name('toggle');
            Route::post('/{user}/toggle-kyc', [AdminUserController::class, 'toggleKyc'])->name('toggle_kyc');
            Route::post('/{user}/tier', [AdminUserController::class, 'updateTier'])->name('tier.update');
            Route::post('/{user}/reset-pin', [AdminUserController::class, 'resetPin'])->name('reset_pin');
            Route::post('/{user}/adjust-balance', [AdminUserController::class, 'adjustBalance'])->name('adjust_balance');
        });

        // Finance, Topup Approvals, Double-Entry Ledger, & Reconciliation (Finance & Super Admin only)
        Route::middleware(['can:access-finance'])->prefix('finance')->name('finance.')->group(function () {
            // Topup Approvals
            Route::get('topups', [AdminTopupApprovalController::class, 'index'])->name('topups.index');
            Route::get('topups/{topup}', [AdminTopupApprovalController::class, 'show'])->name('topups.show');

            // Ledger & Export
            Route::get('ledger', [AdminLedgerMutationController::class, 'index'])->name('ledger.index');
            Route::get('ledger/export', [AdminLedgerMutationController::class, 'exportCsv'])->name('ledger.export');

            // Provider Reconciliation
            Route::get('reconciliation', [AdminReconciliationController::class, 'index'])->name('reconciliation.index');

            // Sensitive Financial Interventions (Requires Password Confirmation)
            Route::middleware(['password.confirm:admin.password.confirm'])->group(function () {
                Route::post('topups/{topup}/approve', [AdminTopupApprovalController::class, 'approve'])->name('topups.approve');
                Route::post('topups/{topup}/reject', [AdminTopupApprovalController::class, 'reject'])->name('topups.reject');

                Route::get('adjustments/create', [AdminWalletAdjustmentController::class, 'create'])->name('adjustments.create');
                Route::post('adjustments', [AdminWalletAdjustmentController::class, 'store'])->name('adjustments.store');
            });
        });

        // System & Security Audit Logs (Read-only)
        Route::prefix('audit-logs')->name('audit_logs.')->group(function () {
            Route::get('/', [AdminAuditLogController::class, 'index'])->name('index');
            Route::get('/{auditLog}', [AdminAuditLogController::class, 'show'])->name('show');
        });

        // Profile & 2FA Setup (Self-Service)
        Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
        Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/2fa/enable', [AdminProfileController::class, 'enableTwoFactor'])->name('profile.2fa.enable');
        Route::get('/profile/2fa/setup', [AdminProfileController::class, 'showTwoFactorSetup'])->name('profile.2fa.setup');
        Route::post('/profile/2fa/confirm', [AdminProfileController::class, 'confirmTwoFactor'])->name('profile.2fa.confirm');
        Route::delete('/profile/2fa/disable', [AdminProfileController::class, 'disableTwoFactor'])->name('profile.2fa.disable');

        // Password Confirm Routes
        Route::get('/confirm-password', [ConfirmPasswordController::class, 'showConfirmForm'])->name('password.confirm');
        Route::post('/confirm-password', [ConfirmPasswordController::class, 'confirm'])->name('password.confirm.submit');

        // Sensitive Protected Action (Requires recent password confirmation)
        Route::middleware(['password.confirm:admin.password.confirm'])->get('/security/test-protected-action', function () {
            return response()->json(['status' => 'confirmed_access_granted']);
        })->name('security.test');
    });
});
