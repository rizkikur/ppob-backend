<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code', 10);
            $table->string('type', 30);
            $table->smallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['phone', 'type', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
