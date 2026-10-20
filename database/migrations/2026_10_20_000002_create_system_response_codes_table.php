<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_response_codes', function (Blueprint $table) {
            $table->id();
            $table->string('rc', 10)->unique()->comment('Kode respon numerik / 2 digit standar PPOB ISO 8583 (e.g. 00, 13, 14, 42, 51, 68)');
            $table->string('slug', 50)->unique()->comment('Identifier internal sistem (e.g. SUCCESS, ERR_DEST_INVALID)');
            $table->string('name', 100)->comment('Nama / label kode error');
            $table->string('status_type', 20)->default('FAILED')->comment('Status transaksi: SUCCESS, FAILED, PENDING');
            $table->string('default_message', 255)->comment('Pesan ramah pengguna yang tampil ke klien');
            $table->boolean('is_retryable')->default(false)->comment('Apakah sistem boleh auto failover / switch ke supplier cadangan');
            $table->boolean('is_system')->default(false)->comment('Kode baku bawaan sistem inti');
            $table->timestamps();

            $table->index('status_type');
        });

        // Seed default initial standard PPOB response codes
        $now = now();
        DB::table('system_response_codes')->insert([
            [
                'rc' => '00',
                'slug' => 'SUCCESS',
                'name' => 'Sukses (Transaksi Berhasil)',
                'status_type' => 'SUCCESS',
                'default_message' => 'Transaksi berhasil diproses.',
                'is_retryable' => false,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '05',
                'slug' => 'ERR_GENERAL',
                'name' => 'Kendala Umum / Provider Error',
                'status_type' => 'FAILED',
                'default_message' => 'Terjadi kendala saat memproses transaksi ke upstream.',
                'is_retryable' => false,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '91',
                'slug' => 'ERR_PROVIDER_DOWN',
                'name' => 'Gangguan Biller / Operator Down',
                'status_type' => 'FAILED',
                'default_message' => 'Sistem operator sedang mengalami gangguan atau pemeliharaan berkala.',
                'is_retryable' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '13',
                'slug' => 'ERR_DEST_INVALID',
                'name' => 'Nomor Tujuan Salah / Tidak Aktif',
                'status_type' => 'FAILED',
                'default_message' => 'Nomor tujuan tidak valid, salah, atau telah kedaluwarsa.',
                'is_retryable' => false,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '14',
                'slug' => 'ERR_CUSTOMER_NOT_FOUND',
                'name' => 'ID Pelanggan / Tagihan Tidak Ditemukan',
                'status_type' => 'FAILED',
                'default_message' => 'ID Pelanggan tidak terdaftar atau data tagihan tidak ditemukan.',
                'is_retryable' => false,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '42',
                'slug' => 'ERR_OUT_OF_STOCK',
                'name' => 'Stok Produk Habis / Gangguan Biller',
                'status_type' => 'FAILED',
                'default_message' => 'Stok produk pada jalur supplier saat ini sedang habis.',
                'is_retryable' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '51',
                'slug' => 'ERR_INSUFFICIENT_BALANCE',
                'name' => 'Saldo Supplier Tidak Cukup',
                'status_type' => 'FAILED',
                'default_message' => 'Saldo akun pada jalur supplier tidak mencukupi untuk memproses transaksi.',
                'is_retryable' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '57',
                'slug' => 'ERR_LIMIT_EXCEEDED',
                'name' => 'Limit / Kuota Transaksi Terlampaui',
                'status_type' => 'FAILED',
                'default_message' => 'Nomor tujuan telah mencapai batas maksimal transaksi untuk hari ini.',
                'is_retryable' => false,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '68',
                'slug' => 'ERR_TIMEOUT',
                'name' => 'Upstream Timeout / Kehabisan Waktu',
                'status_type' => 'PENDING',
                'default_message' => 'Koneksi ke gateway supplier kehabisan waktu tanpa respon pasti.',
                'is_retryable' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '88',
                'slug' => 'ERR_BILL_ALREADY_PAID',
                'name' => 'Tagihan Sudah Terbayar (Lunas)',
                'status_type' => 'FAILED',
                'default_message' => 'Tagihan periode ini sudah dibayar sebelumnya.',
                'is_retryable' => false,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '94',
                'slug' => 'ERR_DUPLICATE_ORDER',
                'name' => 'Transaksi Duplikat / Replay Request',
                'status_type' => 'FAILED',
                'default_message' => 'Permintaan transaksi terdeteksi duplikat dengan id referensi yang sama.',
                'is_retryable' => false,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rc' => '99',
                'slug' => 'ERR_SUSPECT_PENDING',
                'name' => 'Pending / Antrean Upstream',
                'status_type' => 'PENDING',
                'default_message' => 'Transaksi sedang dalam antrean pemrosesan oleh operator.',
                'is_retryable' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_response_codes');
    }
};
