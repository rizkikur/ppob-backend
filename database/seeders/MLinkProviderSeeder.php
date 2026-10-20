<?php

namespace Database\Seeders;

use App\Domain\Ppob\Services\ProviderErrorMappingService;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;
use Illuminate\Database\Seeder;

class MLinkProviderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat atau update Provider M-Link Gateway
        $provider = Provider::updateOrCreate(
            ['code' => 'mlink'],
            [
                'name' => 'M-Link Gateway',
                'callback_slug' => 'mlink',
                'driver' => 'MLinkGatewayDriver',
                'queue_name' => 'supplier_mlink',
                'max_workers' => 25,
                'rate_limit_per_minute' => 120,
                'timeout_seconds' => 60,
                'priority' => 1,
                'supports_balance_api' => true,
                'balance_alert_threshold_cents' => 50000000, // Rp 500.000
                'is_active' => true,
                'config' => [
                    'client_id' => env('PPOB_MLINK_CLIENT_ID', 'xymetri'),
                    'password' => env('PPOB_MLINK_PASSWORD', 'test_password'),
                    'secret_key' => env('PPOB_MLINK_SECRET_KEY', 'test_secret_key'),
                    'api_url' => env('PPOB_MLINK_API_URL', 'https://gateway.m-link.id/json'),
                    'webhook_secret' => env('PPOB_MLINK_WEBHOOK_SECRET', 'mlink_secret_key'),
                    'supports_callback' => true,
                    'supports_check_status' => true,
                ],
            ]
        );

        // 2. Daftar 28 Kode Respon (RC) Resmi M-Link Gateway dipetakan ke Kode Internal Sistem
        $mappings = [
            // 000: Transaksi Sukses
            [
                'upstream_code' => '000',
                'internal_code' => ProviderErrorMappingService::CODE_SUCCESS,
                'custom_message' => 'Transaksi berhasil diproses oleh M-Link Gateway.',
                'is_retryable' => false,
            ],
            // 030: Transaksi Pending
            [
                'upstream_code' => '030',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_SUSPECT_PENDING,
                'custom_message' => 'Transaksi sedang diproses di sisi biller (Pending).',
                'is_retryable' => true,
            ],
            // 003: Internal Processing Timeout
            [
                'upstream_code' => '003',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_TIMEOUT,
                'custom_message' => 'Koneksi ke gateway biller kehabisan waktu (Timeout).',
                'is_retryable' => true,
            ],
            // 005: Kode produk tidak dikenal
            [
                'upstream_code' => '005',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Kode produk tidak dikenal oleh sistem biller.',
                'is_retryable' => false,
            ],
            // 006: Gangguan koneksi biller
            [
                'upstream_code' => '006',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Gangguan koneksi biller supplier.',
                'is_retryable' => true,
            ],
            // 007: Nomor pelanggan tidak ditemukan / salah
            [
                'upstream_code' => '007',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_DEST_INVALID,
                'custom_message' => 'Nomor pelanggan tujuan tidak ditemukan atau salah.',
                'is_retryable' => false,
            ],
            // 008: Internal Error
            [
                'upstream_code' => '008',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Terjadi kesalahan sistem internal pada gateway biller.',
                'is_retryable' => true,
            ],
            // 009: Sistem dalam proses maintanance
            [
                'upstream_code' => '009',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Sistem biller sedang dalam proses pemeliharaan berkala (Maintenance).',
                'is_retryable' => true,
            ],
            // 011: Data tidak ditemukan (API Cek Status Transaksi)
            [
                'upstream_code' => '011',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Data transaksi tidak ditemukan pada sistem supplier.',
                'is_retryable' => false,
            ],
            // 012: Nomor pelanggan expired
            [
                'upstream_code' => '012',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_DEST_INVALID,
                'custom_message' => 'Nomor pelanggan telah expired / masa aktif habis.',
                'is_retryable' => false,
            ],
            // 013: Nomor diblokir
            [
                'upstream_code' => '013',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_DEST_INVALID,
                'custom_message' => 'Nomor pelanggan diblokir oleh operator.',
                'is_retryable' => false,
            ],
            // 014: Gangguan transaksi pada biller
            [
                'upstream_code' => '014',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Gangguan pemrosesan transaksi pada pihak biller.',
                'is_retryable' => true,
            ],
            // 015: Stok kuota habis
            [
                'upstream_code' => '015',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_OUT_OF_STOCK,
                'custom_message' => 'Stok produk pada jalur supplier saat ini sedang habis.',
                'is_retryable' => true,
            ],
            // 018: Saldo deposit tidak cukup
            [
                'upstream_code' => '018',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_INSUFFICIENT_BALANCE,
                'custom_message' => 'Saldo deposit akun pada supplier tidak mencukupi.',
                'is_retryable' => true,
            ],
            // 019: Harga produk belum di set
            [
                'upstream_code' => '019',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Harga produk belum diatur pada sistem supplier.',
                'is_retryable' => false,
            ],
            // 021: Transaksi telah digagalkan dan saldo di-refund
            [
                'upstream_code' => '021',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Transaksi digagalkan oleh biller dan saldo telah direfund.',
                'is_retryable' => false,
            ],
            // 022: Kode produk sementara ditutup
            [
                'upstream_code' => '022',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_OUT_OF_STOCK,
                'custom_message' => 'Kode produk sementara ditutup oleh pihak operator.',
                'is_retryable' => true,
            ],
            // 023: Gagal biller (tidak spesifik menjelaskan penyebab kegagalan)
            [
                'upstream_code' => '023',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Biller mengembalikan status gagal tanpa rincian spesifik.',
                'is_retryable' => true,
            ],
            // 024: Tagihan tidak tersedia (paskabayar)
            [
                'upstream_code' => '024',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Tagihan paskabayar tidak tersedia / belum terbit.',
                'is_retryable' => false,
            ],
            // 025: Layanan tidak tersedia
            [
                'upstream_code' => '025',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Layanan transaksi sedang tidak tersedia.',
                'is_retryable' => true,
            ],
            // 026: Tagihan telah dibayar (paskabayar)
            [
                'upstream_code' => '026',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_LIMIT_EXCEEDED,
                'custom_message' => 'Tagihan paskabayar telah dibayar / sudah lunas.',
                'is_retryable' => false,
            ],
            // 027: Limit harian telah tercapai
            [
                'upstream_code' => '027',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_LIMIT_EXCEEDED,
                'custom_message' => 'Limit transaksi harian untuk nomor ini telah tercapai.',
                'is_retryable' => false,
            ],
            // 031: Signature pada request tidak valid
            [
                'upstream_code' => '031',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Signature keamanan request ke supplier tidak valid.',
                'is_retryable' => false,
            ],
            // 033: Akun diblokir
            [
                'upstream_code' => '033',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Akun pada gateway supplier terblokir.',
                'is_retryable' => false,
            ],
            // 034: Biller menolak perulangan transaksi nomor dan atau denom yang sama
            [
                'upstream_code' => '034',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_LIMIT_EXCEEDED,
                'custom_message' => 'Biller menolak perulangan transaksi untuk nomor dan denom yang sama dalam rentang pendek.',
                'is_retryable' => false,
            ],
            // 035: Sedang proses cutt off pada biller
            [
                'upstream_code' => '035',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN,
                'custom_message' => 'Sedang berlangsung proses cut-off harian pada sistem biller.',
                'is_retryable' => true,
            ],
            // 036: Akun user melampaui batas maksimal ketentuan yang ditetapkan operator
            [
                'upstream_code' => '036',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_LIMIT_EXCEEDED,
                'custom_message' => 'Akun pelanggan melampaui batas maksimal ketentuan operator.',
                'is_retryable' => false,
            ],
            // 037: Nilai Pembayaran tidak valid
            [
                'upstream_code' => '037',
                'internal_code' => ProviderErrorMappingService::CODE_ERR_GENERAL,
                'custom_message' => 'Nilai pembayaran yang diajukan tidak valid.',
                'is_retryable' => false,
            ],
        ];

        foreach ($mappings as $m) {
            ProviderErrorMapping::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'upstream_code' => $m['upstream_code'],
                ],
                [
                    'internal_code' => $m['internal_code'],
                    'custom_message' => $m['custom_message'],
                    'is_retryable' => $m['is_retryable'],
                ]
            );
        }
    }
}
