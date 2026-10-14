<?php

namespace App\Domain\Admin\Http\Controllers\Finance;

use App\Domain\Admin\Services\AdminReconciliationService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminReconciliationController extends Controller
{
    public function __construct(
        protected AdminReconciliationService $reconciliationService
    ) {}

    /**
     * Display provider balance reconciliation dashboard.
     */
    public function index(): View
    {
        $reconciliations = $this->reconciliationService->compareProviderBalances();

        return view('admin.finance.reconciliation.index', [
            'reconciliations' => $reconciliations,
        ]);
    }
}
