<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat tabel master operators
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('icon_url', 255)->nullable();
            $table->json('prefix_rules')->nullable(); // Contoh: ["0811","0812","0813","0821","0822"]
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('sort_order');
        });

        // 2. Tambah operator_id ke tabel products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('category_id')->constrained('operators')->nullOnDelete();
        });

        // 3. Tambah supplier_sku ke tabel product_supplier_routes
        Schema::table('product_supplier_routes', function (Blueprint $table) {
            $table->string('supplier_sku', 50)->nullable()->after('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_supplier_routes', function (Blueprint $table) {
            $table->dropColumn('supplier_sku');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn('operator_id');
        });

        Schema::dropIfExists('operators');
    }
};
