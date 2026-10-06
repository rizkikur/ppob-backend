<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->bigInteger('amount_cents');
            $table->string('method', 30);
            $table->string('payment_gateway', 30)->nullable();
            $table->string('gateway_ref', 100)->nullable()->index();
            $table->jsonb('gateway_payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->bigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE topup_requests ADD CONSTRAINT topup_amount_positive CHECK (amount_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_requests');
    }
};
