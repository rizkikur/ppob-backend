<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pin_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('purpose', 50);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'used_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_verification_tokens');
    }
};
