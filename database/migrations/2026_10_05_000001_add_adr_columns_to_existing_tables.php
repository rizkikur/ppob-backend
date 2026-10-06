<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Tambahkan kolom dari ADR-002, ADR-005, ADR-006, ADR-007, ADR-009
 * ke tabel yang sudah ada (providers, transactions, partners, users).
 *
 * Referensi:
 *   ADR-002 → providers: queue_name, max_workers, rate_limit_per_minute, timeout_seconds, priority
 *   ADR-005 → transactions: supplier_id, original_supplier_id, is_failover
 *   ADR-006 → users: role | partners: protocol
 *   ADR-007 → partners: response_mode, response_timeout_ms, callback_url, callback_secret
 *   ADR-009 → providers: supports_balance_api, balance_alert_threshold_cents
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── providers (ADR-002 + ADR-009) ──────────────────────────────────────
        Schema::table('providers', function (Blueprint $table) {
            $table->string('queue_name', 50)->nullable()->after('driver');
            $table->unsignedSmallInteger('max_workers')->default(10)->after('queue_name');
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(60)->after('max_workers');
            $table->unsignedSmallInteger('timeout_seconds')->default(30)->after('rate_limit_per_minute');
            $table->unsignedTinyInteger('priority')->default(1)->after('timeout_seconds');
            $table->boolean('supports_balance_api')->default(false)->after('priority');
            $table->bigInteger('balance_alert_threshold_cents')->default(0)->after('supports_balance_api');
        });

        // ── users (ADR-006) ────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('is_verified');
            // Nilai valid: user | admin | super_admin
        });

        // ── partners (ADR-006 + ADR-007) ───────────────────────────────────────
        Schema::table('partners', function (Blueprint $table) {
            $table->string('protocol', 20)->default('json')->after('rate_limit_rpm');
            // Nilai valid: json | otomax | irs
            $table->string('response_mode', 10)->default('sync')->after('protocol');
            // Nilai valid: sync | async
            $table->unsignedInteger('response_timeout_ms')->default(5000)->after('response_mode');
            $table->string('callback_url', 500)->nullable()->after('response_timeout_ms');
            $table->string('callback_secret', 255)->nullable()->after('callback_url');
        });

        // ── transactions (ADR-005) ─────────────────────────────────────────────
        // Karena transactions di-partisi (raw DDL di pgsql), gunakan DB::statement
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->bigInteger('supplier_id')->nullable();
                $table->bigInteger('original_supplier_id')->nullable();
                $table->boolean('is_failover')->default(false);
                $table->bigInteger('partner_id')->nullable();
            });
        } else {
            DB::statement('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS supplier_id BIGINT');
            DB::statement('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS original_supplier_id BIGINT');
            DB::statement('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS is_failover BOOLEAN NOT NULL DEFAULT false');
            DB::statement('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS partner_id BIGINT');
        }
        // partner_id: null = transaksi dari mobile user langsung, isi = dari partner H2H
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn([
                'queue_name', 'max_workers', 'rate_limit_per_minute',
                'timeout_seconds', 'priority', 'supports_balance_api',
                'balance_alert_threshold_cents',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'protocol', 'response_mode', 'response_timeout_ms',
                'callback_url', 'callback_secret',
            ]);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn(['supplier_id', 'original_supplier_id', 'is_failover', 'partner_id']);
            });
        } else {
            DB::statement('ALTER TABLE transactions DROP COLUMN IF EXISTS supplier_id');
            DB::statement('ALTER TABLE transactions DROP COLUMN IF EXISTS original_supplier_id');
            DB::statement('ALTER TABLE transactions DROP COLUMN IF EXISTS is_failover');
            DB::statement('ALTER TABLE transactions DROP COLUMN IF EXISTS partner_id');
        }
    }
};
