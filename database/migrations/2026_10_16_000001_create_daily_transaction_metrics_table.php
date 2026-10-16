<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_transaction_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_date', 10); // YYYY-MM-DD
            $table->unsignedTinyInteger('hour'); // 0 - 23
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedBigInteger('gmv_cents')->default(0);
            $table->unsignedBigInteger('cost_cents')->default(0);
            $table->bigInteger('profit_cents')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'hour'], 'uq_daily_trx_metric_date_hour');
            $table->index('metric_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_transaction_metrics');
    }
};
