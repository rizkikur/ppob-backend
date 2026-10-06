<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('endpoint', 100);
            $table->string('method', 10);
            $table->jsonb('request_body')->nullable();
            $table->smallInteger('response_code');
            $table->integer('duration_ms');
            $table->string('ip_address', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['partner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_logs');
    }
};
