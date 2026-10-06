<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_tier_id')->constrained('user_tiers')->cascadeOnDelete();
            $table->bigInteger('sell_price_cents');
            $table->timestamps();
            $table->unique(['product_id', 'user_tier_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_tier_prices ADD CONSTRAINT tier_price_positive CHECK (sell_price_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tier_prices');
    }
};
