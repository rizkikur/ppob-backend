<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key_value', 100);
            $table->string('endpoint', 100);
            $table->smallInteger('response_code');
            $table->jsonb('response_body');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'key_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
