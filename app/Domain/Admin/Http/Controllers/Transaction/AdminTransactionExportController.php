<?php

namespace App\Domain\Admin\Http\Controllers\Transaction;

use App\Domain\Admin\Http\Requests\TransactionFilterRequest;
use App\Domain\Admin\Jobs\ExportTransactionsJob;
use App\Domain\Admin\Models\TransactionExportJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTransactionExportController extends Controller
{
    /**
     * Jadwalkan export transaksi asinkron di latar belakang.
     */
    public function store(TransactionFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        unset($filters['export']);

        $dateStart = $filters['date_start'] ?? now()->format('Ymd');
        $dateEnd = $filters['date_end'] ?? now()->format('Ymd');
        $filename = "transaksi-ppob-{$dateStart}-sd-{$dateEnd}-" . now()->format('His') . '.csv';

        $exportJob = TransactionExportJob::create([
            'admin_id' => $request->user('admin')->id,
            'filename' => $filename,
            'filters' => $filters,
            'status' => TransactionExportJob::STATUS_PENDING,
        ]);

        ExportTransactionsJob::dispatch($exportJob);

        return response()->json([
            'success' => true,
            'message' => 'Proses export transaksi berhasil dijadwalkan di latar belakang.',
            'job' => [
                'id' => $exportJob->id,
                'filename' => $exportJob->filename,
                'status' => $exportJob->status,
                'progress_pct' => 0,
                'created_at' => $exportJob->created_at->format('Y-m-d H:i:s'),
            ],
        ], 202);
    }

    /**
     * Cek status dan progres job export transaksi.
     */
    public function status(Request $request, TransactionExportJob $job): JsonResponse
    {
        $admin = $request->user('admin');
        if ($job->admin_id !== $admin->id && ! $admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'success' => true,
            'job' => [
                'id' => $job->id,
                'filename' => $job->filename,
                'status' => $job->status,
                'total_rows' => $job->total_rows,
                'processed_rows' => $job->processed_rows,
                'progress_pct' => $job->getProgressPercentage(),
                'formatted_size' => $job->formatted_file_size,
                'error_message' => $job->error_message,
                'is_completed' => $job->isCompleted(),
                'is_failed' => $job->isFailed(),
                'download_url' => $job->isCompleted() ? route('admin.transactions.export.download', $job) : null,
            ],
        ]);
    }

    /**
     * Download file CSV hasil export yang telah selesai.
     */
    public function download(Request $request, TransactionExportJob $job): StreamedResponse|JsonResponse
    {
        $admin = $request->user('admin');
        if ($job->admin_id !== $admin->id && ! $admin->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki hak untuk mengunduh file ini.');
        }

        if (! $job->isCompleted() || ! $job->file_path) {
            abort(404, 'File export belum selesai diproses.');
        }

        if ($job->isExpired()) {
            abort(410, 'File export telah kedaluwarsa.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($job->file_path)) {
            abort(404, 'File fisik export tidak ditemukan di server.');
        }

        return $disk->download(
            $job->file_path,
            $job->filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * Dapatkan daftar export terbaru milik admin yang sedang login.
     */
    public function recent(Request $request): JsonResponse
    {
        $jobs = TransactionExportJob::where('admin_id', $request->user('admin')->id)
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn (TransactionExportJob $j) => [
                'id' => $j->id,
                'filename' => $j->filename,
                'status' => $j->status,
                'total_rows' => $j->total_rows,
                'processed_rows' => $j->processed_rows,
                'progress_pct' => $j->getProgressPercentage(),
                'formatted_size' => $j->formatted_file_size,
                'is_completed' => $j->isCompleted(),
                'is_failed' => $j->isFailed(),
                'download_url' => $j->isCompleted() ? route('admin.transactions.export.download', $j) : null,
                'created_at' => $j->created_at->diffForHumans(),
            ]);

        return response()->json([
            'success' => true,
            'jobs' => $jobs,
        ]);
    }
}
