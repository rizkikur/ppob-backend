<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers');
            $table->foreignId('category_id')->constrained('product_categories');
            $table->string('sku_code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('product_type', 10); // prepaid | postpaid
            $table->bigInteger('base_price_cents');
            $table->bigInteger('admin_fee_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
