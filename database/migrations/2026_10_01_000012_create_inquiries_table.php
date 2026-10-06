<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('product_id')->constrained('products');
            $table->string('customer_number', 50);
            $table->bigInteger('amount_cents');
            $table->bigInteger('admin_fee_cents')->default(0);
            $table->string('inquiry_ref', 100)->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
