<?php

namespace App\Domain\Admin\Http\Controllers\Partner;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminPartnerManagementService;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AdminPartnerWebhookController extends Controller
{
    public function __construct(
        protected AdminPartnerManagementService $partnerService
    ) {}

    /**
     * Trigger manual retry/replay of a failed webhook callback.
     */
    public function retry(WebhookDelivery $delivery): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->partnerService->retryWebhookDelivery($delivery, $admin);

        return redirect()
            ->back()
            ->with('success', "Pengiriman ulang webhook #{$delivery->id} berhasil dimasukkan ke antrean.");
    }
}
