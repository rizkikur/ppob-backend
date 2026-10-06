<?php

namespace App\Domain\Auth\Drivers;

use App\Domain\Auth\Contracts\OtpDriverInterface;
use App\Domain\Auth\Exceptions\OtpDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver OTP via Fonnte WhatsApp API.
 *
 * Dokumentasi Fonnte: https://fonnte.com/api
 * Konfigurasi di config/ppob.php → otp.fonnte_token
 *
 * HTTP POST ke https://api.fonnte.com/send
 * Header: Authorization: {token}
 * Body: target, message
 */
class FonnteOtpDriver implements OtpDriverInterface
{
    private string $token;

    private string $apiUrl;

    public function __construct()
    {
        $this->token = (string) (config('ppob.otp.fonnte_token') ?? config('ppob.otp.fonnte.token') ?? '');
        $this->apiUrl = (string) (config('ppob.otp.fonnte.api_url') ?? 'https://api.fonnte.com/send');
    }

    /**
     * {@inheritDoc}
     *
     * @throws OtpDeliveryException jika HTTP gagal atau response tidak sukses
     */
    public function send(string $phone, string $code): void
    {
        $message = "Kode OTP Anda: {$code}. Berlaku 5 menit.";

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => $this->token,
            ])->post($this->apiUrl, [
                'target' => $phone,
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::warning('Fonnte OTP send failed', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new OtpDeliveryException;
            }

            $data = $response->json();

            // Fonnte mengembalikan status=true jika berhasil
            if (! isset($data['status']) || $data['status'] !== true) {
                Log::warning('Fonnte OTP response not success', [
                    'phone' => $phone,
                    'response' => $data,
                ]);

                throw new OtpDeliveryException;
            }
        } catch (OtpDeliveryException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Fonnte OTP send exception', [
                'phone' => $phone,
                'message' => $e->getMessage(),
            ]);

            throw new OtpDeliveryException('Gagal terhubung ke layanan pengiriman OTP.');
        }
    }
}
