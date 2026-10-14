<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Partner\Jobs\DeliverWebhookJob;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerLog;
use App\Domain\Partner\Models\PartnerProductPrice;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPartnerManagementService
{
    public function __construct(
        protected AdminAuditLogService $auditLogService,
        protected WalletService $walletService
    ) {}

    /**
     * Get paginated partners with metrics.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function getPartnersWithMetrics(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Partner::query()
            ->with(['user.wallet'])
            ->withCount(['logs', 'webhookDeliveries', 'productPrices']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('api_key', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Summary statistics for Partner Dashboard Bar.
     *
     * @return array{total_partners: int, active_partners: int, total_deposit_cents: int, failed_webhooks_count: int, total_api_calls_today: int}
     */
    public function getPartnerSummaryMetrics(): array
    {
        $totalPartners = Partner::count();
        $activePartners = Partner::where('is_active', true)->count();

        // Calculate total partner wallet balance
        $partnerUserIds = Partner::whereNotNull('user_id')->pluck('user_id');
        $totalDepositCents = (int) Wallet::whereIn('user_id', $partnerUserIds)->sum('balance_cents');

        $failedWebhooks = WebhookDelivery::whereIn('status', [
            WebhookDelivery::STATUS_FAILED,
            WebhookDelivery::STATUS_FAILED_PERMANENT,
        ])->count();

        $apiCallsToday = PartnerLog::whereDate('created_at', today())->count();

        return [
            'total_partners' => $totalPartners,
            'active_partners' => $activePartners,
            'total_deposit_cents' => $totalDepositCents,
            'failed_webhooks_count' => $failedWebhooks,
            'total_api_calls_today' => $apiCallsToday,
        ];
    }

    /**
     * Register a new partner and generate credentials (Stripe-style).
     *
     * @return array{partner: Partner, plain_secret: string}
     */
    public function createPartner(array $data, Admin $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $apiKey = 'pk_live_'.bin2hex(random_bytes(16));
            $plainSecret = 'sk_live_'.bin2hex(random_bytes(24));

            $allowedIps = null;
            if (! empty($data['allowed_ips'])) {
                $rawIps = preg_split('/[\r\n,]+/', (string) $data['allowed_ips']);
                $cleaned = array_values(array_filter(array_map('trim', $rawIps)));
                if (! empty($cleaned)) {
                    $allowedIps = $cleaned;
                }
            }

            /** @var Partner $partner */
            $partner = Partner::create([
                'name' => $data['name'],
                'api_key' => $apiKey,
                'secret' => encrypt($plainSecret),
                'allowed_ips' => $allowedIps,
                'is_active' => true,
                'rate_limit_rpm' => (int) ($data['rate_limit_rpm'] ?? 60),
                'protocol' => $data['protocol'] ?? 'json',
                'response_mode' => $data['response_mode'] ?? 'sync',
                'response_timeout_ms' => (int) ($data['response_timeout_ms'] ?? 5000),
                'callback_url' => $data['callback_url'] ?? null,
                'callback_secret' => ! empty($data['callback_url']) ? encrypt('whsec_'.bin2hex(random_bytes(16))) : null,
            ]);

            // Create associated User and Wallet
            $user = $partner->getOrCreateUser();
            if (! empty($data['email'])) {
                $user->update(['email' => $data['email']]);
            }
            if (! empty($data['phone'])) {
                $user->update(['phone' => $data['phone']]);
            }

            // Optional initial deposit
            if (! empty($data['initial_balance']) && (float) $data['initial_balance'] > 0) {
                $initialCents = (int) round((float) $data['initial_balance'] * 100);
                $this->walletService->credit(
                    user: $user,
                    amount: Money::fromCents($initialCents),
                    referenceType: 'topup',
                    referenceId: null,
                    note: 'Setoran deposit awal mitra baru'
                );
            }

            $this->auditLogService->log(
                admin: $admin,
                action: 'partner.created',
                target: $partner,
                changes: [
                    'name' => $partner->name,
                    'api_key' => $partner->api_key,
                    'protocol' => $partner->protocol,
                ],
                reason: "Registrasi mitra B2B baru: {$partner->name}"
            );

            return [
                'partner' => $partner,
                'plain_secret' => $plainSecret,
            ];
        });
    }

    /**
     * Update partner profile & connection settings.
     */
    public function updatePartner(Partner $partner, array $data, Admin $admin): Partner
    {
        return DB::transaction(function () use ($partner, $data, $admin) {
            $old = $partner->only([
                'name', 'rate_limit_rpm', 'protocol', 'response_mode', 'response_timeout_ms', 'callback_url', 'is_active',
            ]);

            $partner->update([
                'name' => $data['name'],
                'rate_limit_rpm' => (int) $data['rate_limit_rpm'],
                'protocol' => $data['protocol'],
                'response_mode' => $data['response_mode'],
                'response_timeout_ms' => (int) $data['response_timeout_ms'],
                'callback_url' => $data['callback_url'] ?? null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $partner->is_active,
            ]);

            $this->auditLogService->log(
                admin: $admin,
                action: 'partner.updated',
                target: $partner,
                changes: [
                    'old' => $old,
                    'new' => $partner->fresh()->only(array_keys($old)),
                ],
                reason: "Pembaruan profil konfigurasi mitra {$partner->name}"
            );

            return $partner;
        });
    }

    /**
     * Emergency suspend / unsuspend partner.
     */
    public function togglePartnerStatus(Partner $partner, Admin $admin, ?string $reason = null): bool
    {
        $newStatus = ! $partner->is_active;
        $partner->update(['is_active' => $newStatus]);

        Cache::forget("partner:auth:{$partner->api_key}");

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.status_toggled',
            target: $partner,
            changes: ['is_active' => $newStatus],
            reason: $reason ?? ($newStatus ? "Aktifkan kembali mitra {$partner->name}" : "Suspend akses mitra {$partner->name}")
        );

        return $newStatus;
    }

    /**
     * Rotate Public API Key.
     */
    public function rotateApiKey(Partner $partner, Admin $admin, ?string $reason = null): string
    {
        $oldKey = $partner->api_key;
        $newKey = 'pk_live_'.bin2hex(random_bytes(16));

        $partner->update(['api_key' => $newKey]);
        Cache::forget("partner:auth:{$oldKey}");

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.api_key_rotated',
            target: $partner,
            changes: ['old_key' => $oldKey, 'new_key' => $newKey],
            reason: $reason ?? "Rotasi Public API Key mitra {$partner->name}"
        );

        return $newKey;
    }

    /**
     * Rotate API Secret (returns Plaintext Secret ONE TIME).
     */
    public function rotateApiSecret(Partner $partner, Admin $admin, ?string $reason = null): string
    {
        $plainSecret = 'sk_live_'.bin2hex(random_bytes(24));
        $partner->update(['secret' => encrypt($plainSecret)]);

        Cache::forget("partner:auth:{$partner->api_key}");

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.api_secret_rotated',
            target: $partner,
            changes: ['rotated_at' => now()->toIso8601String()],
            reason: $reason ?? "Rotasi API Secret mitra {$partner->name}"
        );

        return $plainSecret;
    }

    /**
     * Add IP or CIDR to whitelist.
     */
    public function addIpWhitelist(Partner $partner, string $ip, Admin $admin): void
    {
        $ip = trim($ip);
        $allowed = $partner->allowed_ips ?? [];

        if (! in_array($ip, $allowed, true)) {
            $allowed[] = $ip;
            $partner->update(['allowed_ips' => array_values($allowed)]);
            Cache::forget("partner:ip:{$partner->id}");

            $this->auditLogService->log(
                admin: $admin,
                action: 'partner.ip_whitelist_added',
                target: $partner,
                changes: ['added_ip' => $ip, 'total_ips' => count($allowed)],
                reason: "Menambahkan IP {$ip} ke whitelist mitra {$partner->name}"
            );
        }
    }

    /**
     * Remove IP or CIDR from whitelist.
     */
    public function removeIpWhitelist(Partner $partner, string $ip, Admin $admin): void
    {
        $ip = trim($ip);
        $allowed = $partner->allowed_ips ?? [];

        $filtered = array_values(array_filter($allowed, fn ($i) => $i !== $ip));

        $partner->update(['allowed_ips' => ! empty($filtered) ? $filtered : null]);
        Cache::forget("partner:ip:{$partner->id}");

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.ip_whitelist_removed',
            target: $partner,
            changes: ['removed_ip' => $ip, 'remaining_ips' => count($filtered)],
            reason: "Menghapus IP {$ip} dari whitelist mitra {$partner->name}"
        );
    }

    /**
     * Adjust partner deposit balance (credit / debit) via WalletService.
     */
    public function adjustBalance(Partner $partner, string $type, int $amountCents, Admin $admin, string $note): WalletMutation
    {
        $user = $partner->getOrCreateUser();
        $amount = Money::fromCents($amountCents);

        if ($type === 'credit') {
            $mutation = $this->walletService->credit(
                user: $user,
                amount: $amount,
                referenceType: 'adjustment',
                referenceId: null,
                note: "Manual Adjustment Credit oleh {$admin->name}: {$note}"
            );
        } else {
            $mutation = $this->walletService->debit(
                user: $user,
                amount: $amount,
                referenceType: 'adjustment',
                referenceId: null,
                note: "Manual Adjustment Debit oleh {$admin->name}: {$note}"
            );
        }

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.balance_adjusted',
            target: $partner,
            changes: [
                'type' => $type,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $mutation->balance_after_cents,
            ],
            reason: "Penyesuaian saldo mitra {$partner->name}: {$note}"
        );

        return $mutation;
    }

    /**
     * Update custom pricing for a SKU with Anti-Negative Margin guard.
     */
    public function updateCustomPricing(Partner $partner, int $productId, int $sellPriceCents, bool $isActive, Admin $admin): PartnerProductPrice
    {
        /** @var Product $product */
        $product = Product::findOrFail($productId);

        $basePriceCents = $product->base_price_cents?->toCents() ?? 0;
        if ($sellPriceCents < $basePriceCents) {
            throw ValidationException::withMessages([
                'sell_price' => [
                    'Harga jual khusus (Rp '.number_format($sellPriceCents / 100, 0, ',', '.').') tidak boleh di bawah HPP modal (Rp '.number_format($basePriceCents / 100, 0, ',', '.').')!',
                ],
            ]);
        }

        /** @var PartnerProductPrice $customPrice */
        $customPrice = PartnerProductPrice::updateOrCreate(
            [
                'partner_id' => $partner->id,
                'product_id' => $product->id,
            ],
            [
                'sell_price_cents' => $sellPriceCents,
                'is_active' => $isActive,
            ]
        );

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.custom_pricing_updated',
            target: $partner,
            changes: [
                'product' => $product->sku_code,
                'sell_price_cents' => $sellPriceCents,
                'is_active' => $isActive,
            ],
            reason: "Pengaturan harga khusus SKU {$product->sku_code} untuk mitra {$partner->name}"
        );

        return $customPrice;
    }

    /**
     * Trigger manual replay/retry of a webhook delivery.
     */
    public function retryWebhookDelivery(WebhookDelivery $delivery, Admin $admin): void
    {
        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => now(),
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        $this->auditLogService->log(
            admin: $admin,
            action: 'partner.webhook_retry_dispatched',
            target: $delivery,
            changes: ['delivery_id' => $delivery->id, 'partner_id' => $delivery->partner_id],
            reason: "Manual replay webhook delivery #{$delivery->id} ke {$delivery->callback_url}"
        );
    }
}
