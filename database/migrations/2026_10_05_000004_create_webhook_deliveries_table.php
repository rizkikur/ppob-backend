<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Tabel baru dari ADR-008
 *   - webhook_deliveries: Tracking pengiriman callback ke klien dengan exponential backoff retry
 *
 * Referensi: docs/architecture-decisions.md § ADR-008
 *
 * Flow:
 *   Transaksi selesai → DeliverWebhookJob dispatch → insert row di sini
 *   Kalau gagal → update attempt, hitung next_retry_at, re-dispatch dengan delay
 *   Setelah 5 kali gagal → status = failed_permanent, alert ke admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            // FK ke transactions tidak bisa pakai constrained() karena partitioned table
            $table->bigInteger('transaction_id')->unsigned();
            $table->unsignedTinyInteger('attempt')->default(1); // 1–5
            $table->string('status', 30)->default('pending');
            // Nilai valid: pending | delivered | failed | failed_permanent
            $table->string('callback_url', 500);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']); // untuk scheduler retry
            $table->index(['partner_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
