<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah callback_slug ke tabel providers jika belum ada
        Schema::table('providers', function (Blueprint $table) {
            $table->string('callback_slug', 50)->nullable()->unique()->after('code');
        });

        // 2. Tabel provider_error_mappings (Mapping Response Code Upstream -> Kode Error Internal)
        Schema::create('provider_error_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('upstream_code', 50);
            $table->string('internal_code', 50);
            $table->string('custom_message', 255)->nullable();
            $table->boolean('is_retryable')->default(false);
            $table->timestamps();

            $table->unique(['provider_id', 'upstream_code']);
            $table->index(['provider_id', 'internal_code']);
        });

        // 3. Tabel provider_products (Katalog Produk / Pricelist langsung dari Upstream Supplier)
        Schema::create('provider_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('supplier_sku', 50);
            $table->string('name', 150);
            $table->string('category_name', 50)->nullable();
            $table->bigInteger('cost_cents')->default(0);
            $table->string('status', 20)->default('active'); // active | empty | maintenance
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'supplier_sku']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_products');
        Schema::dropIfExists('provider_error_mappings');

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('callback_slug');
        });
    }
};
