<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('action', 100);             // misal: 'transaction.refund', 'admin.login'
            $table->string('target_type', 100)->nullable(); // misal: 'Transaction', 'Provider'
            $table->string('target_id', 100)->nullable();
            $table->json('changes')->nullable();       // ['old' => [...], 'new' => [...]]
            $table->text('reason')->nullable();        // Alasan aksi (wajib untuk financial refund/override)
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent(); // APPEND ONLY: TIDAK ADA updated_at

            $table->index(['admin_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
