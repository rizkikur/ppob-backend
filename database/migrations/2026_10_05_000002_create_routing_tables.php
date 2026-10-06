<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Tabel baru dari ADR-004
 *   - partner_routing_rules : Aturan failover/routing per klien per kategori
 *   - product_supplier_routes: Daftar supplier yang bisa layani produk tertentu + prioritas
 *
 * Referensi: docs/architecture-decisions.md § ADR-004
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── product_supplier_routes ────────────────────────────────────────────
        // Daftar supplier mana saja yang bisa melayani suatu produk, beserta prioritasnya.
        Schema::create('product_supplier_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->unsignedTinyInteger('priority')->default(1); // 1 = utama, makin besar makin rendah
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'provider_id']);
            $table->index(['product_id', 'priority']);
        });

        // ── partner_routing_rules ──────────────────────────────────────────────
        // Aturan per klien: apakah boleh failover? ke supplier mana?
        Schema::create('partner_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('category_code', 30)->nullable();
            // null = rule global untuk semua kategori
            $table->foreignId('preferred_provider_id')->nullable()->constrained('providers')->nullOnDelete();
            // null = bebas pilih supplier prioritas tertinggi
            $table->boolean('allow_failover')->default(false);
            $table->string('failover_policy', 20)->default('none');
            // Nilai valid: none | same_category | any
            $table->timestamps();

            // Lookup: paling spesifik dulu (category_code not null), lalu global (null)
            $table->unique(['partner_id', 'category_code']);
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_routing_rules');
        Schema::dropIfExists('product_supplier_routes');
    }
};
