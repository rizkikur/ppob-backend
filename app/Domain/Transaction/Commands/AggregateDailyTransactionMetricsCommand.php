<?php

namespace App\Domain\Transaction\Commands;

use App\Domain\Transaction\Models\DailyTransactionMetric;
use App\Domain\Transaction\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateDailyTransactionMetricsCommand extends Command
{
    protected $signature = 'metrics:aggregate-transactions
                            {--date= : Tanggal spesifik (YYYY-MM-DD)}
                            {--days=7 : Jumlah hari ke belakang jika tanggal tidak ditentukan}';

    protected $description = 'Agregasi ulang data tabel daily_transaction_metrics dari riwayat transaksi';

    public function handle(): int
    {
        $specificDate = $this->option('date');

        if ($specificDate) {
            $startDate = Carbon::parse($specificDate)->startOfDay();
            $endDate = Carbon::parse($specificDate)->endOfDay();
            $this->info("Mengagregasi metrik untuk tanggal: {$startDate->toDateString()}");
        } else {
            $days = max(1, (int) $this->option('days'));
            $startDate = Carbon::today()->subDays($days - 1)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
            $this->info("Mengagregasi metrik dari {$startDate->toDateString()} hingga {$endDate->toDateString()} ({$days} hari)...");
        }

        // Hapus metrik lama pada range tersebut agar agregasi bersih
        DailyTransactionMetric::whereBetween('metric_date', [
            $startDate->toDateString(),
            $endDate->toDateString(),
        ])->delete();

        // Ambil transaksi dalam chunk dan kelompokkan berdasarkan tanggal dan jam
        $transactions = Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->get(['status', 'amount_cents', 'sell_price_cents', 'created_at']);

        if ($transactions->isEmpty()) {
            $this->info('Tidak ada transaksi yang ditemukan pada rentang waktu ini.');
            return self::SUCCESS;
        }

        $grouped = [];

        foreach ($transactions as $tx) {
            $date = $tx->created_at->format('Y-m-d');
            $hour = (int) $tx->created_at->format('H');
            $key = "{$date}_{$hour}";

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'metric_date' => $date,
                    'hour' => $hour,
                    'total_count' => 0,
                    'success_count' => 0,
                    'failed_count' => 0,
                    'pending_count' => 0,
                    'gmv_cents' => 0,
                    'cost_cents' => 0,
                    'profit_cents' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $grouped[$key]['total_count']++;

            $sellPriceCents = (int) (is_object($tx->sell_price_cents) ? $tx->sell_price_cents->toCents() : $tx->getAttributes()['sell_price_cents']);
            $amountCents = (int) (is_object($tx->amount_cents) ? $tx->amount_cents->toCents() : $tx->getAttributes()['amount_cents']);

            if ($tx->status === Transaction::STATUS_SUCCESS) {
                $grouped[$key]['success_count']++;
                $grouped[$key]['gmv_cents'] += $sellPriceCents;
                $grouped[$key]['cost_cents'] += $amountCents;
                $grouped[$key]['profit_cents'] += ($sellPriceCents - $amountCents);
            } elseif ($tx->status === Transaction::STATUS_FAILED) {
                $grouped[$key]['failed_count']++;
            } elseif (in_array($tx->status, [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING], true)) {
                $grouped[$key]['pending_count']++;
            }
        }

        foreach ($grouped as $row) {
            DailyTransactionMetric::create($row);
        }

        $this->info('Berhasil mengagregasi ' . count($grouped) . ' jam record ke dalam tabel daily_transaction_metrics.');

        return self::SUCCESS;
    }
}
