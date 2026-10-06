<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Tabel baru dari ADR-009 + ADR-010
 *   - supplier_balances        : Monitoring saldo deposit di masing-masing supplier
 *   - reconciliation_reports   : Ringkasan hasil rekonsiliasi harian per supplier
 *   - reconciliation_discrepancies : Detail ketidakcocokan rekonsiliasi
 *
 * Referensi: docs/architecture-decisions.md § ADR-009, ADR-010
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── supplier_balances (ADR-009) ────────────────────────────────────────
        Schema::create('supplier_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->bigInteger('balance_cents'); // bisa negatif jika manual entry salah
            $table->string('source', 10)->default('manual');
            // Nilai valid: api | manual
            $table->timestamp('checked_at');
            $table->text('raw_response')->nullable(); // response mentah dari API supplier
            $table->timestamps();

            $table->index(['provider_id', 'checked_at']);
            // Ambil saldo terbaru: ORDER BY checked_at DESC LIMIT 1
        });

        // ── reconciliation_reports (ADR-010) ──────────────────────────────────
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->date('report_date');
            $table->string('status', 20)->default('running');
            // Nilai valid: running | clean | discrepancy | error
            $table->unsignedInteger('total_transactions_local')->default(0);
            $table->unsignedInteger('total_transactions_supplier')->default(0);
            $table->unsignedInteger('discrepancy_count')->default(0);
            $table->bigInteger('discrepancy_amount_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'report_date']);
            $table->index('report_date');
        });

        // ── reconciliation_discrepancies (ADR-010) ────────────────────────────
        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_report_id')
                ->constrained('reconciliation_reports')
                ->cascadeOnDelete();
            $table->bigInteger('transaction_id')->unsigned()->nullable();
            // null = ada di supplier tapi tidak ada di kita
            $table->string('supplier_ref', 100)->nullable();
            // null = ada di kita tapi tidak ada di supplier
            $table->string('discrepancy_type', 30);
            // Nilai: missing_local | missing_supplier | amount_mismatch | status_mismatch
            $table->bigInteger('local_amount_cents')->nullable();
            $table->bigInteger('supplier_amount_cents')->nullable();
            $table->string('local_status', 20)->nullable();
            $table->string('supplier_status', 20)->nullable();
            $table->string('resolution', 20)->default('unresolved');
            // Nilai: unresolved | resolved_manual | resolved_auto | ignored
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index('reconciliation_report_id');
            $table->index('resolution');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
        Schema::dropIfExists('reconciliation_reports');
        Schema::dropIfExists('supplier_balances');
    }
};
