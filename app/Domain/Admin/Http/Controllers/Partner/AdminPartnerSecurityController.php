<?php

namespace App\Domain\Admin\Http\Controllers\Partner;

use App\Domain\Admin\Http\Requests\AddIpWhitelistRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminPartnerManagementService;
use App\Domain\Partner\Models\Partner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminPartnerSecurityController extends Controller
{
    public function __construct(
        protected AdminPartnerManagementService $partnerService
    ) {}

    /**
     * Rotate public API key for the partner.
     */
    public function rotateApiKey(Partner $partner, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $newKey = $this->partnerService->rotateApiKey(
            partner: $partner,
            admin: $admin,
            reason: $request->input('reason')
        );

        return redirect()
            ->back()
            ->with('success', "API Key publik berhasil di-regenerasi: {$newKey}");
    }

    /**
     * Rotate API Secret (Stripe style One-Time Flash).
     */
    public function rotateApiSecret(Partner $partner, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $plainSecret = $this->partnerService->rotateApiSecret(
            partner: $partner,
            admin: $admin,
            reason: $request->input('reason')
        );

        session()->flash('newly_generated_secret', $plainSecret);
        session()->flash('newly_generated_key', $partner->api_key);
        session()->flash('newly_generated_name', $partner->name);

        return redirect()
            ->back()
            ->with('success', 'API Secret berhasil di-rotate! Salin kunci rahasia baru ini sekarang.');
    }

    /**
     * Add IP or CIDR to partner whitelist.
     */
    public function addIp(Partner $partner, AddIpWhitelistRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->addIpWhitelist(
            partner: $partner,
            ip: $request->validated('ip'),
            admin: $admin
        );

        return redirect()
            ->back()
            ->with('success', "Alamat IP {$request->validated('ip')} berhasil ditambahkan ke whitelist.");
    }

    /**
     * Remove IP from partner whitelist.
     */
    public function removeIp(Partner $partner, string $ip): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->removeIpWhitelist(
            partner: $partner,
            ip: urldecode($ip),
            admin: $admin
        );

        return redirect()
            ->back()
            ->with('success', "Alamat IP {$ip} berhasil dihapus dari whitelist.");
    }
}
