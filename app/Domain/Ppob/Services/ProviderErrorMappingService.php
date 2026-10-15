<?php

namespace App\Domain\Ppob\Services;

use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;

class ProviderErrorMappingService
{
    /**
     * Kamus Standar Kode Error Internal Sistem PPOB.
     */
    public const CODE_SUCCESS = 'SUCCESS';
    public const CODE_ERR_DEST_INVALID = 'ERR_DEST_INVALID';
    public const CODE_ERR_PROVIDER_DOWN = 'ERR_PROVIDER_DOWN';
    public const CODE_ERR_OUT_OF_STOCK = 'ERR_OUT_OF_STOCK';
    public const CODE_ERR_INSUFFICIENT_BALANCE = 'ERR_INSUFFICIENT_BALANCE';
    public const CODE_ERR_LIMIT_EXCEEDED = 'ERR_LIMIT_EXCEEDED';
    public const CODE_ERR_TIMEOUT = 'ERR_TIMEOUT';
    public const CODE_ERR_SUSPECT_PENDING = 'ERR_SUSPECT_PENDING';
    public const CODE_ERR_GENERAL = 'ERR_GENERAL';

    /**
     * Daftar seluruh standar kode error beserta label & pesan default ramah pengguna.
     *
     * @return array<string, array{label: string, default_message: string, is_retryable: bool}>
     */
    public static function getStandardErrorDefinitions(): array
    {
        return [
            self::CODE_SUCCESS => [
                'label' => 'Sukses (Transaksi Berhasil)',
                'default_message' => 'Transaksi berhasil diproses.',
                'is_retryable' => false,
            ],
            self::CODE_ERR_DEST_INVALID => [
                'label' => 'Nomor Tujuan Salah / Tidak Aktif',
                'default_message' => 'Nomor tujuan tidak valid, salah, atau telah kedaluwarsa.',
                'is_retryable' => false,
            ],
            self::CODE_ERR_PROVIDER_DOWN => [
                'label' => 'Gangguan Biller / Operator Down',
                'default_message' => 'Sistem operator sedang mengalami gangguan atau pemeliharaan berkala.',
                'is_retryable' => true,
            ],
            self::CODE_ERR_OUT_OF_STOCK => [
                'label' => 'Stok Produk Habis',
                'default_message' => 'Stok produk pada jalur supplier saat ini sedang habis.',
                'is_retryable' => true,
            ],
            self::CODE_ERR_INSUFFICIENT_BALANCE => [
                'label' => 'Saldo Supplier Tidak Cukup',
                'default_message' => 'Saldo akun pada jalur supplier tidak mencukupi untuk memproses transaksi.',
                'is_retryable' => true,
            ],
            self::CODE_ERR_LIMIT_EXCEEDED => [
                'label' => 'Limit / Kuota Transaksi Terlampaui',
                'default_message' => 'Nomor tujuan telah mencapai batas maksimal transaksi untuk hari ini.',
                'is_retryable' => false,
            ],
            self::CODE_ERR_TIMEOUT => [
                'label' => 'Upstream Timeout / Kehabisan Waktu',
                'default_message' => 'Koneksi ke gateway supplier kehabisan waktu tanpa respon pasti.',
                'is_retryable' => true,
            ],
            self::CODE_ERR_SUSPECT_PENDING => [
                'label' => 'Pending / Antrean Upstream',
                'default_message' => 'Transaksi sedang dalam antrean pemrosesan oleh operator.',
                'is_retryable' => true,
            ],
            self::CODE_ERR_GENERAL => [
                'label' => 'Kendala Umum / Provider Error',
                'default_message' => 'Terjadi kendala saat memproses transaksi ke upstream.',
                'is_retryable' => false,
            ],
        ];
    }

    /**
     * Resolusi Response Code (RC) Upstream ke Kode Error & Pesan Internal.
     *
     * @return array{internal_code: string, message: string, is_retryable: bool, is_mapped: bool}
     */
    public function resolve(Provider|int $provider, ?string $upstreamCode, ?string $fallbackMessage = null): array
    {
        $providerId = $provider instanceof Provider ? $provider->id : $provider;

        if ($upstreamCode === null || $upstreamCode === '') {
            return [
                'internal_code' => self::CODE_ERR_GENERAL,
                'message' => $fallbackMessage ?: 'Kendala umum dari gateway provider.',
                'is_retryable' => false,
                'is_mapped' => false,
            ];
        }

        /** @var ProviderErrorMapping|null $mapping */
        $mapping = ProviderErrorMapping::query()
            ->where('provider_id', $providerId)
            ->where('upstream_code', (string) $upstreamCode)
            ->first();

        if ($mapping) {
            $definitions = self::getStandardErrorDefinitions();
            $defaultMsg = $definitions[$mapping->internal_code]['default_message'] ?? 'Terjadi kendala pada provider.';

            return [
                'internal_code' => $mapping->internal_code,
                'message' => $mapping->custom_message ?: $defaultMsg,
                'is_retryable' => $mapping->is_retryable,
                'is_mapped' => true,
            ];
        }

        // Fallback jika belum dipetakan secara spesifik di database
        return [
            'internal_code' => self::CODE_ERR_GENERAL,
            'message' => $fallbackMessage ?: "Provider error [RC: {$upstreamCode}]",
            'is_retryable' => false,
            'is_mapped' => false,
        ];
    }
}
