<?php

namespace App\Domain\Admin\Http\Controllers\Partner;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminPartnerManagementService;
use App\Domain\Partner\Models\Partner;
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

    /**
     * Send simulated ping test to partner callback URL.
     */
    public function ping(Partner $partner): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        try {
            $result = $this->partnerService->pingWebhook($partner, $admin);

            if ($result['success']) {
                return redirect()
                    ->back()
                    ->with('success', "Test Ping Webhook BERHASIL! Server mitra merespons HTTP {$result['status_code']} dalam {$result['latency_ms']}ms.");
            }

            return redirect()
                ->back()
                ->with('error', "Test Ping Webhook GAGAL! Server mitra mengembalikan HTTP {$result['status_code']} ({$result['latency_ms']}ms). Respon: {$result['response_body']}");
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', "Test Ping Webhook GAGAL: {$e->getMessage()}");
        }
    }
}
