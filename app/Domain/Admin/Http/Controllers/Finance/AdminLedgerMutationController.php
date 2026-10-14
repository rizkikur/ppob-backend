<?php

namespace App\Domain\Admin\Http\Controllers\Finance;

use App\Domain\Admin\Http\Requests\ExportLedgerCsvRequest;
use App\Domain\Admin\Services\AdminFinanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLedgerMutationController extends Controller
{
    public function __construct(
        protected AdminFinanceService $financeService
    ) {}

    /**
     * Display paginated double-entry wallet mutations ledger.
     */
    public function index(Request $request): View
    {
        $filters = [
            'type' => $request->input('type'),
            'reference_type' => $request->input('reference_type'),
            'search' => $request->input('search'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $mutations = $this->financeService->getLedgerMutations($filters, 25);

        return view('admin.finance.ledger.index', [
            'mutations' => $mutations,
            'filters' => $filters,
        ]);
    }

    /**
     * Stream CSV export of ledger mutations.
     */
    public function exportCsv(ExportLedgerCsvRequest $request): StreamedResponse
    {
        return $this->financeService->streamExportLedgerCsv($request->validated());
    }
}
