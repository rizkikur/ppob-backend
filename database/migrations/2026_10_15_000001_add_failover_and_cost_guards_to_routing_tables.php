<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan allow_failover ke products
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allow_failover')->default(true)->after('is_active');
        });

        // 2. Tambahkan cost_cents ke product_supplier_routes untuk modal spesifik supplier
        Schema::table('product_supplier_routes', function (Blueprint $table) {
            $table->bigInteger('cost_cents')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_failover');
        });

        Schema::table('product_supplier_routes', function (Blueprint $table) {
            $table->dropColumn('cost_cents');
        });
    }
};
