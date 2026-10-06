<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('api_key', 64)->unique();
            $table->string('secret', 255); // terenkripsi via Laravel encrypt()
            $table->jsonb('allowed_ips')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit_rpm')->default(60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
