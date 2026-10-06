<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel transactions dengan partisi per bulan.
 * WAJIB menggunakan DB::statement (raw SQL) — bukan Schema Builder.
 * Lihat ERD.md untuk detail dan docs/security-design.md untuk penjelasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id');
                $table->foreignId('product_id');
                $table->foreignId('inquiry_id')->nullable();
                $table->string('customer_number', 50);
                $table->bigInteger('amount_cents');
                $table->bigInteger('sell_price_cents');
                $table->string('status', 20)->default('pending');
                $table->string('idempotency_key', 100);
                $table->string('provider_ref', 100)->nullable();
                $table->json('provider_response')->nullable();
                $table->string('failure_reason', 255)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['status', 'created_at']);
                $table->index('idempotency_key');
            });

            return;
        }

        // Buat tabel partisi utama (PostgreSQL)
        DB::statement("
            CREATE TABLE transactions (
                id               BIGSERIAL,
                user_id          BIGINT NOT NULL,
                product_id       BIGINT NOT NULL,
                inquiry_id       BIGINT,
                customer_number  VARCHAR(50) NOT NULL,
                amount_cents     BIGINT NOT NULL,
                sell_price_cents BIGINT NOT NULL,
                status           VARCHAR(20) NOT NULL DEFAULT 'pending',
                idempotency_key  VARCHAR(100) NOT NULL,
                provider_ref     VARCHAR(100),
                provider_response JSONB,
                failure_reason   VARCHAR(255),
                created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        ");

        // Seed partisi untuk 6 bulan ke depan
        $start = now()->startOfMonth();
        for ($i = 0; $i < 6; $i++) {
            $from = $start->copy()->addMonths($i)->format('Y-m-d');
            $to = $start->copy()->addMonths($i + 1)->format('Y-m-d');
            $suffix = $start->copy()->addMonths($i)->format('Y_m');
            DB::statement("
                CREATE TABLE IF NOT EXISTS transactions_{$suffix}
                PARTITION OF transactions
                FOR VALUES FROM ('{$from}') TO ('{$to}')
            ");
        }

        // Index per partisi
        DB::statement('CREATE INDEX idx_transactions_user_date ON transactions (user_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_transactions_status ON transactions (status, created_at)');
        DB::statement('CREATE INDEX idx_transactions_idempotency ON transactions (idempotency_key)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('transactions');

            return;
        }

        DB::statement('DROP TABLE IF EXISTS transactions CASCADE');
    }
};
