<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 20);
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('reference_type', 50);
            $table->bigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
        // Trigger append-only (hanya untuk PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE OR REPLACE FUNCTION prevent_wallet_mutations_modification()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    RAISE EXCEPTION 'wallet_mutations is append-only: UPDATE and DELETE are not allowed';
                END;
                \$\$ LANGUAGE plpgsql;
            ");
            DB::statement('
                CREATE TRIGGER wallet_mutations_immutable
                    BEFORE UPDATE OR DELETE ON wallet_mutations
                    FOR EACH ROW EXECUTE FUNCTION prevent_wallet_mutations_modification();
            ');
            // amount harus positif
            DB::statement('ALTER TABLE wallet_mutations ADD CONSTRAINT wallet_mutations_amount_positive CHECK (amount_cents > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS wallet_mutations_immutable ON wallet_mutations');
            DB::statement('DROP FUNCTION IF EXISTS prevent_wallet_mutations_modification()');
        }
        Schema::dropIfExists('wallet_mutations');
    }
};
