<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_products', function (Blueprint $table) {
            $table->string('inquiry_url', 255)->nullable()->after('status');
            $table->string('payment_url', 255)->nullable()->after('inquiry_url');
        });
    }

    public function down(): void
    {
        Schema::table('provider_products', function (Blueprint $table) {
            $table->dropColumn(['inquiry_url', 'payment_url']);
        });
    }
};
