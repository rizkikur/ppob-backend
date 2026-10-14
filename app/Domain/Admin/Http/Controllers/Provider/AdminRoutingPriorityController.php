<?php

namespace App\Domain\Admin\Http\Controllers\Provider;

use App\Domain\Admin\Http\Requests\UpdateRoutingPriorityRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProviderManagementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AdminRoutingPriorityController extends Controller
{
    public function __construct(
        protected AdminProviderManagementService $providerService
    ) {}

    /**
     * Update routing priority order for providers or specific product routes.
     */
    public function update(UpdateRoutingPriorityRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->updateRoutingPriorities(
            priorities: $request->validated('priorities'),
            admin: $admin,
            productId: $request->validated('product_id'),
            reason: $request->validated('reason')
        );

        return redirect()
            ->back()
            ->with('success', 'Urutan prioritas failover routing provider berhasil diperbarui.');
    }
}
