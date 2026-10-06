<?php

namespace App\Domain\Ppob\Contracts;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Interface wajib untuk semua driver provider PPOB.
 *
 * Setiap provider baru (Telkomsel, PLN, PDAM, dsb) WAJIB implement interface ini.
 *
 * Pendaftaran driver di DomainServiceProvider:
 *   $this->app->bind(PpobProviderInterface::class, TelkomselDriver::class);
 * (atau via factory/resolver berdasarkan kode provider)
 */
interface PpobProviderInterface
{
    /**
     * Inquiry tagihan (untuk produk postpaid).
     *
     * @param  string  $customerNumber  Nomor pelanggan
     * @param  string  $productCode  SKU atau kode produk provider
     * @return array{
     *     ref: string,
     *     amount_cents: int,
     *     customer_name: string,
     *     raw: array
     * }
     *
     * @throws BusinessException
     */
    public function inquiry(string $customerNumber, string $productCode): array;

    /**
     * Eksekusi pembayaran ke provider.
     *
     * @param  Money  $amount  Hanya relevan untuk postpaid
     * @param  string  $transactionRef  ID transaksi di sistem kita (untuk tracking)
     * @return array{
     *     status: 'success'|'pending'|'failed',
     *     provider_ref: string,
     *     raw: array
     * }
     *
     * @throws BusinessException
     */
    public function pay(
        string $customerNumber,
        string $productCode,
        Money $amount,
        string $transactionRef
    ): array;

    /**
     * Cek status transaksi (untuk provider async/polling).
     *
     * @return array{
     *     status: 'success'|'pending'|'failed',
     *     provider_ref: string,
     *     raw: array
     * }
     */
    public function checkStatus(string $providerRef): array;

    /**
     * Verifikasi signature webhook dari provider ini.
     *
     * @param  string  $rawBody  Raw request body dari webhook
     * @param  string  $signature  Header X-Signature dari webhook
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool;
}
