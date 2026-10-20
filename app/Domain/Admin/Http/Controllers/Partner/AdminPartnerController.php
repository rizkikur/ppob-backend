<?php

namespace App\Domain\Admin\Http\Controllers\Partner;

use App\Domain\Admin\Http\Requests\AdjustPartnerBalanceRequest;
use App\Domain\Admin\Http\Requests\StorePartnerRequest;
use App\Domain\Admin\Http\Requests\StorePartnerRoutingRuleRequest;
use App\Domain\Admin\Http\Requests\UpdatePartnerPricingRequest;
use App\Domain\Admin\Http\Requests\UpdatePartnerRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminPartnerManagementService;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerRoutingRule;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPartnerController extends Controller
{
    public function __construct(
        protected AdminPartnerManagementService $partnerService
    ) {}

    /**
     * List all B2B Partners with search, status filters, and summary metrics.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        $partners = $this->partnerService->getPartnersWithMetrics($filters, 15);
        $metrics = $this->partnerService->getPartnerSummaryMetrics();

        return view('admin.partners.index', [
            'partners' => $partners,
            'metrics' => $metrics,
            'filters' => $filters,
        ]);
    }

    /**
     * Form to create a new B2B Partner.
     */
    public function create(): View
    {
        return view('admin.partners.create');
    }

    /**
     * Store new partner, generate credentials and flash One-Time Secret.
     */
    public function store(StorePartnerRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $result = $this->partnerService->createPartner($request->validated(), $admin);
        $partner = $result['partner'];
        $plainSecret = $result['plain_secret'];

        // Flash One-Time Secret to session (Stripe pattern)
        session()->flash('newly_generated_secret', $plainSecret);
        session()->flash('newly_generated_key', $partner->api_key);
        session()->flash('newly_generated_name', $partner->name);

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', "Mitra {$partner->name} berhasil didaftarkan. Harap simpan Kunci Rahasia (Secret) sekarang!");
    }

    /**
     * Show detailed partner information, API credentials, IP Whitelists, Webhook logs, and balances.
     */
    public function show(Partner $partner, Request $request): View
    {
        $partner->loadMissing([
            'user.wallet.mutations' => fn ($q) => $q->latest()->limit(15),
            'productPrices.product',
            'routingRules.preferredProvider',
            'webhookDeliveries' => fn ($q) => $q->latest()->limit(30),
            'logs' => fn ($q) => $q->latest()->limit(30),
        ]);

        $partnerUser = $partner->getOrCreateUser();
        $balance = $partner->getBalance();

        // Products for custom pricing dropdown
        $availableProducts = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku_code', 'base_price_cents', 'customer_price_cents']);

        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();
        $providers = Provider::where('is_active', true)->orderBy('name')->get();

        return view('admin.partners.show', [
            'partner' => $partner,
            'partnerUser' => $partnerUser,
            'balance' => $balance,
            'availableProducts' => $availableProducts,
            'categories' => $categories,
            'providers' => $providers,
        ]);
    }

    /**
     * Form to edit partner profile and protocol settings.
     */
    public function edit(Partner $partner): View
    {
        return view('admin.partners.edit', [
            'partner' => $partner,
        ]);
    }

    /**
     * Update partner details.
     */
    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->updatePartner($partner, $request->validated(), $admin);

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', "Profil mitra {$partner->name} berhasil diperbarui.");
    }

    /**
     * Toggle active/suspend status of a partner.
     */
    public function toggleStatus(Partner $partner, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $newStatus = $this->partnerService->togglePartnerStatus(
            partner: $partner,
            admin: $admin,
            reason: $request->input('reason')
        );

        $statusText = $newStatus ? 'diaktifkan kembali' : 'ditangguhkan (SUSPENDED)';

        return redirect()
            ->back()
            ->with('success', "Akses mitra {$partner->name} berhasil {$statusText}.");
    }

    /**
     * Manually adjust partner deposit balance (Credit / Debit).
     */
    public function adjustBalance(Partner $partner, AdjustPartnerBalanceRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $type = $request->validated('type');
        $amountCents = (int) round((float) $request->validated('amount') * 100);
        $note = $request->validated('note');

        try {
            $this->partnerService->adjustBalance(
                partner: $partner,
                type: $type,
                amountCents: $amountCents,
                admin: $admin,
                note: $note
            );

            $typeLabel = $type === 'credit' ? 'Penambahan' : 'Pemotongan';

            return redirect()
                ->back()
                ->with('success', "{$typeLabel} saldo mitra {$partner->name} sebesar Rp ".number_format($amountCents / 100, 0, ',', '.').' berhasil diproses.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Update or set custom price for a specific SKU.
     */
    public function updatePricing(Partner $partner, UpdatePartnerPricingRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $productId = (int) $request->validated('product_id');
        $sellPriceCents = (int) round((float) $request->validated('sell_price') * 100);
        $isActive = $request->boolean('is_active', true);

        $this->partnerService->updateCustomPricing(
            partner: $partner,
            productId: $productId,
            sellPriceCents: $sellPriceCents,
            isActive: $isActive,
            admin: $admin
        );

        return redirect()
            ->back()
            ->with('success', 'Harga khusus produk untuk mitra berhasil disimpan.');
    }

    /**
     * Store or update routing and failover rule for this partner.
     */
    public function storeRoutingRule(Partner $partner, StorePartnerRoutingRuleRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->addOrUpdateRoutingRule($partner, $request->validated(), $admin);

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Aturan failover routing mitra berhasil disimpan.');
    }

    /**
     * Remove a routing rule from this partner.
     */
    public function destroyRoutingRule(Partner $partner, PartnerRoutingRule $rule): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->deleteRoutingRule($partner, $rule, $admin);

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Aturan failover routing mitra berhasil dihapus.');
    }

    /**
     * Delete custom price override for a product.
     */
    public function destroyPricing(Partner $partner, Product $product): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->deleteCustomPricing($partner, $product->id, $admin);

        return redirect()
            ->back()
            ->with('success', "Override harga khusus untuk SKU {$product->sku_code} berhasil dihapus.");
    }
}
