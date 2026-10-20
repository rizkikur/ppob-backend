<?php

namespace App\Domain\Ppob\Services;

use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;
use App\Domain\Product\Models\SystemResponseCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ProviderErrorMappingService
{
    /**
     * Cache TTL in seconds (24 hours).
     */
    public const CACHE_TTL_SECONDS = 86400;

    /**
     * Cache key prefixes.
     */
    public const CACHE_KEY_SYSTEM_DEFINITIONS = 'ppob:rc:system_definitions';
    public const CACHE_KEY_PROVIDER_PREFIX = 'ppob:rc:provider:';

    /**
     * Kamus Standar Kode Error Internal Sistem PPOB (Backward Compatibility Constants).
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
     * Menggunakan Redis Cache untuk kecepatan sub-millisecond, fallback ke database dan array konstan.
     *
     * @return array<string, array{rc: string, label: string, default_message: string, status_type: string, is_retryable: bool, is_system: bool}>
     */
    public static function getStandardErrorDefinitions(): array
    {
        return Cache::remember(self::CACHE_KEY_SYSTEM_DEFINITIONS, self::CACHE_TTL_SECONDS, function () {
            if (Schema::hasTable('system_response_codes')) {
                $rows = SystemResponseCode::query()->orderBy('rc')->get();
                if ($rows->isNotEmpty()) {
                    $definitions = [];
                    foreach ($rows as $row) {
                        $definitions[$row->slug] = [
                            'rc' => $row->rc,
                            'label' => $row->name,
                            'default_message' => $row->default_message,
                            'status_type' => $row->status_type,
                            'is_retryable' => (bool) $row->is_retryable,
                            'is_system' => (bool) $row->is_system,
                        ];
                    }
                    return $definitions;
                }
            }

            return self::getFallbackDefinitions();
        });
    }

    /**
     * Resolusi Response Code (RC) Upstream ke Kode Error & Pesan Internal.
     * Menggunakan dictionary in-memory cache Redis per supplier, menghemat query database di setiap transaksi.
     *
     * @return array{rc: string, internal_code: string, status_type: string, message: string, is_retryable: bool, is_mapped: bool}
     */
    public function resolve(Provider|int $provider, ?string $upstreamCode, ?string $fallbackMessage = null): array
    {
        $providerId = $provider instanceof Provider ? $provider->id : $provider;
        $definitions = self::getStandardErrorDefinitions();
        $defaultGeneral = $definitions[self::CODE_ERR_GENERAL] ?? [
            'rc' => '05',
            'status_type' => 'FAILED',
            'default_message' => 'Kendala umum dari gateway provider.',
            'is_retryable' => false,
        ];

        if ($upstreamCode === null || $upstreamCode === '') {
            return [
                'rc' => $defaultGeneral['rc'] ?? '05',
                'internal_code' => self::CODE_ERR_GENERAL,
                'status_type' => $defaultGeneral['status_type'] ?? 'FAILED',
                'message' => $fallbackMessage ?: ($defaultGeneral['default_message'] ?? 'Kendala umum dari gateway provider.'),
                'is_retryable' => false,
                'is_mapped' => false,
            ];
        }

        // Ambil dictionary mapping supplier dari Redis cache
        $cachedMappings = $this->getProviderMappingsFromCache($providerId);
        $mapping = $cachedMappings[(string) $upstreamCode] ?? null;

        if ($mapping) {
            $def = $definitions[$mapping['internal_code']] ?? null;
            $defaultMsg = $def['default_message'] ?? 'Terjadi kendala pada provider.';
            $statusType = $def['status_type'] ?? 'FAILED';
            $rc = $def['rc'] ?? '05';

            return [
                'rc' => $rc,
                'internal_code' => $mapping['internal_code'],
                'status_type' => $statusType,
                'message' => $mapping['custom_message'] ?: $defaultMsg,
                'is_retryable' => (bool) $mapping['is_retryable'],
                'is_mapped' => true,
            ];
        }

        // Fallback jika belum dipetakan secara spesifik di database
        return [
            'rc' => $defaultGeneral['rc'] ?? '05',
            'internal_code' => self::CODE_ERR_GENERAL,
            'status_type' => $defaultGeneral['status_type'] ?? 'FAILED',
            'message' => $fallbackMessage ?: "Provider error [RC: {$upstreamCode}]",
            'is_retryable' => false,
            'is_mapped' => false,
        ];
    }

    /**
     * Dapatkan mapping error supplier dari Redis Cache.
     *
     * @return array<string, array{id: int, upstream_code: string, internal_code: string, custom_message: ?string, is_retryable: bool}>
     */
    public function getProviderMappingsFromCache(int $providerId): array
    {
        return Cache::remember(self::CACHE_KEY_PROVIDER_PREFIX . $providerId, self::CACHE_TTL_SECONDS, function () use ($providerId) {
            return ProviderErrorMapping::query()
                ->where('provider_id', $providerId)
                ->get()
                ->keyBy('upstream_code')
                ->map(fn(ProviderErrorMapping $m) => [
                    'id' => $m->id,
                    'upstream_code' => (string) $m->upstream_code,
                    'internal_code' => $m->internal_code,
                    'custom_message' => $m->custom_message,
                    'is_retryable' => (bool) $m->is_retryable,
                ])
                ->toArray();
        });
    }

    /**
     * Invalidate (Hapus) cache mapping untuk provider tertentu saat admin melakukan update/tambah/hapus.
     */
    public static function clearProviderCache(Provider|int $provider): void
    {
        $providerId = $provider instanceof Provider ? $provider->id : $provider;
        Cache::forget(self::CACHE_KEY_PROVIDER_PREFIX . $providerId);
    }

    /**
     * Invalidate (Hapus) cache standar kode error internal saat admin melakukan update/tambah/hapus.
     */
    public static function clearSystemCache(): void
    {
        Cache::forget(self::CACHE_KEY_SYSTEM_DEFINITIONS);
    }

    /**
     * Invalidate seluruh cache response code (baik system definitions maupun seluruh provider).
     */
    public static function clearAllCache(): void
    {
        self::clearSystemCache();
        // Clear all provider mappings if known
        if (Schema::hasTable('providers')) {
            Provider::query()->pluck('id')->each(function ($id) {
                Cache::forget(self::CACHE_KEY_PROVIDER_PREFIX . $id);
            });
        }
    }

    /**
     * Fallback definitions jika database belum termigrasi.
     */
    protected static function getFallbackDefinitions(): array
    {
        return [
            self::CODE_SUCCESS => [
                'rc' => '00',
                'label' => 'Sukses (Transaksi Berhasil)',
                'default_message' => 'Transaksi berhasil diproses.',
                'status_type' => 'SUCCESS',
                'is_retryable' => false,
                'is_system' => true,
            ],
            self::CODE_ERR_DEST_INVALID => [
                'rc' => '13',
                'label' => 'Nomor Tujuan Salah / Tidak Aktif',
                'default_message' => 'Nomor tujuan tidak valid, salah, atau telah kedaluwarsa.',
                'status_type' => 'FAILED',
                'is_retryable' => false,
                'is_system' => true,
            ],
            self::CODE_ERR_PROVIDER_DOWN => [
                'rc' => '05',
                'label' => 'Gangguan Biller / Operator Down',
                'default_message' => 'Sistem operator sedang mengalami gangguan atau pemeliharaan berkala.',
                'status_type' => 'FAILED',
                'is_retryable' => true,
                'is_system' => true,
            ],
            self::CODE_ERR_OUT_OF_STOCK => [
                'rc' => '42',
                'label' => 'Stok Produk Habis',
                'default_message' => 'Stok produk pada jalur supplier saat ini sedang habis.',
                'status_type' => 'FAILED',
                'is_retryable' => true,
                'is_system' => true,
            ],
            self::CODE_ERR_INSUFFICIENT_BALANCE => [
                'rc' => '51',
                'label' => 'Saldo Supplier Tidak Cukup',
                'default_message' => 'Saldo akun pada jalur supplier tidak mencukupi untuk memproses transaksi.',
                'status_type' => 'FAILED',
                'is_retryable' => true,
                'is_system' => true,
            ],
            self::CODE_ERR_LIMIT_EXCEEDED => [
                'rc' => '57',
                'label' => 'Limit / Kuota Transaksi Terlampaui',
                'default_message' => 'Nomor tujuan telah mencapai batas maksimal transaksi untuk hari ini.',
                'status_type' => 'FAILED',
                'is_retryable' => false,
                'is_system' => true,
            ],
            self::CODE_ERR_TIMEOUT => [
                'rc' => '68',
                'label' => 'Upstream Timeout / Kehabisan Waktu',
                'default_message' => 'Koneksi ke gateway supplier kehabisan waktu tanpa respon pasti.',
                'status_type' => 'PENDING',
                'is_retryable' => true,
                'is_system' => true,
            ],
            self::CODE_ERR_SUSPECT_PENDING => [
                'rc' => '99',
                'label' => 'Pending / Antrean Upstream',
                'default_message' => 'Transaksi sedang dalam antrean pemrosesan oleh operator.',
                'status_type' => 'PENDING',
                'is_retryable' => true,
                'is_system' => true,
            ],
            self::CODE_ERR_GENERAL => [
                'rc' => '05',
                'label' => 'Kendala Umum / Provider Error',
                'default_message' => 'Terjadi kendala saat memproses transaksi ke upstream.',
                'status_type' => 'FAILED',
                'is_retryable' => false,
                'is_system' => true,
            ],
        ];
    }
}
