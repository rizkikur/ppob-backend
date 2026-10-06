<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Tabel baru dari ADR-006
 *   - partner_product_prices: Harga jual flat per partner per produk
 *
 * Referensi: docs/architecture-decisions.md § ADR-006
 *
 * Catatan desain:
 *   Tabel ini TERPISAH dari product_tier_prices (harga user tier biasa).
 *   Partner H2H punya pricing sendiri yang dikonfigurasi admin secara manual.
 *   Kalau tidak ada row untuk partner+produk tertentu, fallback ke harga tier partner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->bigInteger('sell_price_cents')->unsigned();
            // Harga jual final yang diberikan ke partner ini untuk produk ini
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['partner_id', 'product_id']);
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_product_prices');
    }
};
