<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->bigInteger('balance_cents')->default(0);
            $table->timestamps();
        });
        // Saldo tidak boleh negatif
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_non_negative CHECK (balance_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
