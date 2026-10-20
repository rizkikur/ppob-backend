<?php

namespace App\Domain\Ppob\Drivers;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Http\ApiResponse;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Driver M-Link Gateway (H2H-JSON V.1.0).
 *
 * Mengimplementasikan koneksi HTTP POST JSON dengan signature HMAC-SHA256,
 * pengambilan katalog produk (ProductInquiry), cek saldo (Balance), cek status (CekStatus),
 * serta transaksi Pulsa, Paket Data, E-Wallet, dan PLN Prepaid/Postpaid.
 */
class MLinkGatewayDriver implements PpobProviderInterface
{
    private static ?array $mockInquiryResult = null;

    private static ?array $mockPayResult = null;

    private static ?array $mockCheckStatusResult = null;

    private static ?array $mockPricelistResult = null;

    private static ?array $mockBalanceResult = null;

    private static bool $simulationMode = false;

    public static function setMockInquiry(?array $result): void
    {
        self::$mockInquiryResult = $result;
    }

    public static function setMockPay(?array $result): void
    {
        self::$mockPayResult = $result;
    }

    public static function setMockCheckStatus(?array $result): void
    {
        self::$mockCheckStatusResult = $result;
    }

    public static function setMockPricelist(?array $result): void
    {
        self::$mockPricelistResult = $result;
    }

    public static function setMockBalance(?array $result): void
    {
        self::$mockBalanceResult = $result;
    }

    public static function enableSimulation(bool $enabled = true): void
    {
        self::$simulationMode = $enabled;
    }

    /**
     * Generate signature sesuai spesifikasi M-Link Gateway:
     * Base64 Encoded of HMAC_SHA256(secret-key, client_id:req_id:password).
     */
    public function generateSignature(string $clientId, string $reqId, string $password, string $secretKey): string
    {
        $payload = $clientId . ':' . $reqId . ':' . $password;
        $hash = hash_hmac('sha256', $payload, $secretKey, true);

        return base64_encode($hash);
    }

    /**
     * Dapatkan konfigurasi Provider M-Link (dari model Provider di DB atau fallback ke config/env).
     *
     * @return array{client_id: string, password: string, secret_key: string, api_url: string, webhook_secret: string}
     */
    public function resolveConfig(?Provider $provider = null): array
    {
        $dbConfig = $provider?->config ?? [];

        $rawUrl = (string) ($dbConfig['base_url'] ?? $dbConfig['api_url'] ?? config('ppob.providers.mlink.api_url', 'https://gateway.m-link.id/json'));
        $port = ! empty($dbConfig['port']) ? (int) $dbConfig['port'] : null;

        // Otomatis gabungkan port & path /json jika terpisah
        if (! empty($rawUrl)) {
            $parsed = parse_url($rawUrl);
            if (isset($parsed['host'])) {
                $scheme = $parsed['scheme'] ?? 'http';
                $host = $parsed['host'];
                $effectivePort = $parsed['port'] ?? $port;
                $path = $parsed['path'] ?? '/json';
                if ($path === '' || $path === '/') {
                    $path = '/json';
                }

                $portStr = $effectivePort ? ":{$effectivePort}" : '';
                $rawUrl = "{$scheme}://{$host}{$portStr}{$path}";
            }
        }

        return [
            'client_id' => (string) ($dbConfig['username'] ?? $dbConfig['client_id'] ?? config('ppob.providers.mlink.client_id', 'xymetri')),
            'password' => (string) ($dbConfig['password'] ?? config('ppob.providers.mlink.password', 'provided-by-admin')),
            'secret_key' => (string) ($dbConfig['secret_key'] ?? $dbConfig['api_key'] ?? config('ppob.providers.mlink.secret_key', 'provided-by-admin')),
            'api_url' => $rawUrl,
            'webhook_secret' => (string) ($dbConfig['webhook_secret'] ?? config('ppob.providers.mlink.webhook_secret', 'mlink_secret_key')),
        ];
    }

    /**
     * Kirim request ke M-Link Gateway endpoint JSON.
     */
    public function sendRequest(string $action, array $body = [], ?string $reqId = null, ?Provider $provider = null): array
    {
        $config = $this->resolveConfig($provider);
        $reqId = $reqId ?: (string) (microtime(true) * 10000 . rand(100, 999));
        $reqDate = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');

        $signature = $this->generateSignature(
            $config['client_id'],
            $reqId,
            $config['password'],
            $config['secret_key']
        );

        $payload = [
            'header' => [
                'action' => $action,
                'client_id' => $config['client_id'],
                'req_id' => $reqId,
                'req_date' => $reqDate,
                'signature' => $signature,
            ],
            'body' => (object) $body,
        ];

        // Jalankan simulasi jika offline / testing / simulation mode aktif
        if (self::$simulationMode || empty($config['api_url']) || str_contains($config['api_url'], 'example.com') || app()->environment('testing')) {
            return $this->simulateResponse($action, $body, $reqId);
        }

        try {
            $response = Http::timeout(60)
                ->withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($config['api_url'], $payload);

            if (! $response->successful()) {
                Log::warning('MLink Gateway HTTP error', [
                    'action' => $action,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'header' => ['req_id' => $reqId, 'ref_id' => ''],
                    'body' => [
                        'status' => 'FAILED',
                        'rc' => '003',
                        'description' => 'Internal Processing Timeout',
                        'message' => 'HTTP request failed: ' . $response->status(),
                    ],
                ];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('MLink Gateway exception', ['action' => $action, 'error' => $e->getMessage()]);

            return [
                'header' => ['req_id' => $reqId, 'ref_id' => ''],
                'body' => [
                    'status' => 'FAILED',
                    'rc' => '003',
                    'description' => 'Internal Processing Timeout',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Ambil seluruh katalog produk dari upstream M-Link Gateway (ProductInquiry).
     *
     * @return array<int, array{supplier_sku: string, name: string, category_name: string, cost_cents: int, status: string, raw_data: array}>
     */
    public function getPricelist(Provider $provider): array
    {
        if (self::$mockPricelistResult !== null) {
            return self::$mockPricelistResult;
        }

        $res = $this->sendRequest('ProductInquiry', [], null, $provider);
        $products = $res['body']['data']['product'] ?? [];

        $items = [];
        foreach ($products as $p) {
            $unitPrice = (float) ($p['unit_price'] ?? 0);
            $items[] = [
                'supplier_sku' => (string) ($p['product_id'] ?? ''),
                'name' => (string) ($p['description'] ?? $p['product_id'] ?? ''),
                'category_name' => (string) ($p['operator'] ?? 'General'),
                'cost_cents' => (int) round($unitPrice * 100), // Rupiah ke Cents
                'status' => strtoupper((string) ($p['status'] ?? '')) === 'OPEN' ? 'active' : 'maintenance',
                'raw_data' => $p,
            ];
        }

        return $items;
    }

    /**
     * Cek sisa saldo deposit di M-Link Gateway (Balance).
     *
     * @return array{success: bool, balance_cents: int, balance_formatted: string, message: string, raw: array}
     */
    public function checkBalance(Provider $provider): array
    {
        if (self::$mockBalanceResult !== null) {
            return self::$mockBalanceResult;
        }

        $res = $this->sendRequest('Balance', [], null, $provider);
        $body = $res['body'] ?? [];
        $balanceRp = (int) ($body['balance'] ?? 0);
        $balanceCents = $balanceRp * 100;

        return [
            'success' => ($body['rc'] ?? '') === '000' || ($body['status'] ?? '') === 'SUCCESS',
            'balance_cents' => $balanceCents,
            'balance_formatted' => 'Rp ' . number_format($balanceRp, 0, ',', '.'),
            'message' => (string) ($body['message'] ?? 'Saldo deposit M-Link Gateway.'),
            'raw' => $res,
        ];
    }

    /**
     * Inquiry tagihan / akun (E-Wallet & PLN).
     */
    public function inquiry(string $customerNumber, string $productCode): array
    {
        if (self::$mockInquiryResult !== null) {
            return self::$mockInquiryResult;
        }

        $codeUpper = strtoupper($productCode);

        // A. Inquiry PLN Prepaid / Pascabayar
        if (str_starts_with($codeUpper, 'PLN')) {
            $res = $this->sendRequest('PLNPrepaidQuery', [
                'product_id' => $productCode,
                'cust_id' => $customerNumber,
            ]);

            $body = $res['body'] ?? [];
            $rc = $body['rc'] ?? '000';
            if ($rc !== '000' && ($body['status'] ?? '') !== 'SUCCESS') {
                throw new BusinessException(
                    'INQUIRY_FAILED',
                    "Inquiry PLN gagal [RC: {$rc}]: " . ($body['message'] ?? $body['description'] ?? 'Nomor tidak ditemukan')
                );
            }

            $unitPrice = (float) ($body['unit_price'] ?? 0);
            $nama = $body['data']['nama'] ?? $customerNumber;

            return [
                'ref' => (string) ($res['header']['ref_id'] ?? $customerNumber),
                'amount_cents' => (int) round($unitPrice * 100),
                'customer_name' => (string) $nama,
                'raw' => $body,
            ];
        }

        // B. Inquiry Akun E-Wallet (eWalletQuery)
        if (str_starts_with($codeUpper, 'DNA') || str_starts_with($codeUpper, 'GPC') || str_starts_with($codeUpper, 'OVO') || str_starts_with($codeUpper, 'DANA')) {
            $res = $this->sendRequest('eWalletQuery', [
                'product_id' => $productCode,
                'cust_id' => $customerNumber,
            ]);

            $body = $res['body'] ?? [];
            $rc = $body['rc'] ?? '000';
            if ($rc !== '000' && ($body['status'] ?? '') !== 'SUCCESS') {
                throw new BusinessException(
                    'INQUIRY_FAILED',
                    "Inquiry e-Wallet gagal [RC: {$rc}]: " . ($body['message'] ?? $body['description'] ?? 'Akun tidak valid')
                );
            }

            $unitPrice = (float) ($body['unit_price'] ?? 0);
            $nama = $body['data']['name'] ?? $customerNumber;

            return [
                'ref' => (string) ($res['header']['ref_id'] ?? $customerNumber),
                'amount_cents' => (int) round($unitPrice * 100),
                'customer_name' => (string) $nama,
                'raw' => $body,
            ];
        }

        // C. Produk Pulsa / Paket Data murni prabayar
        throw BusinessException::productTypeMismatch('postpaid', 'prepaid');
    }

    /**
     * Eksekusi transaksi pembayaran / topup ke M-Link Gateway.
     */
    public function pay(string $customerNumber, string $productCode, Money $amount, string $transactionRef): array
    {
        if (self::$mockPayResult !== null) {
            return self::$mockPayResult;
        }

        $codeUpper = strtoupper($productCode);
        $body = [
            'product_id' => $productCode,
            'cust_id' => $customerNumber,
        ];

        // Tentukan action sesuai kategori produk
        if (str_starts_with($codeUpper, 'PLN')) {
            $action = 'PLNPrepaidTopup';
        } elseif (str_starts_with($codeUpper, 'DNA') || str_starts_with($codeUpper, 'GPC') || str_starts_with($codeUpper, 'OVO')) {
            $action = 'eWalletPayment';
            // Dukungan open-denom jika amount bernilai khusus
            if ($amount->toCents() > 0) {
                $body['amount'] = (string) round($amount->toRupiah());
            }
        } else {
            // Pulsa, Paket Data, dsb
            $action = 'TopupRequest';
        }

        $res = $this->sendRequest($action, $body, $transactionRef);

        return $this->formatTransactionResponse($res, $transactionRef);
    }

    /**
     * Cek status transaksi ke M-Link Gateway via action CekStatus.
     */
    public function checkStatus(string $providerRef): array
    {
        if (self::$mockCheckStatusResult !== null) {
            return self::$mockCheckStatusResult;
        }

        $res = $this->sendRequest('CekStatus', [
            'order_req_id' => $providerRef,
        ]);

        return $this->formatTransactionResponse($res, $providerRef);
    }

    /**
     * Format respon standar internal dari payload M-Link Gateway.
     *
     * @return array{status: 'success'|'pending'|'failed', provider_ref: string, rc: ?string, code: ?string, message: string, raw: array}
     */
    private function formatTransactionResponse(array $res, string $fallbackRef): array
    {
        $header = $res['header'] ?? [];
        $body = $res['body'] ?? [];

        $rawRc = (string) ($body['rc'] ?? '');
        $statusStr = strtoupper((string) ($body['status'] ?? ''));
        $refId = (string) ($header['ref_id'] ?? $fallbackRef);

        // Standarisasi Status
        if ($rawRc === '000' || $statusStr === 'SUCCESS') {
            $status = 'success';
        } elseif ($rawRc === '030' || $statusStr === 'PENDING') {
            $status = 'pending';
        } else {
            $status = 'failed';
        }

        $sn = (string) ($body['serial_no'] ?? ($body['data']['token'] ?? ''));
        $message = (string) ($body['message'] ?? $body['description'] ?? 'Respon gateway supplier');

        return [
            'status' => $status,
            'provider_ref' => $refId ?: $fallbackRef,
            'rc' => $rawRc,
            'code' => $rawRc,
            'message' => $message,
            'raw' => [
                'rc' => $rawRc,
                'status' => $statusStr,
                'serial_number' => $sn,
                'unit_price' => $body['unit_price'] ?? null,
                'balance' => $body['balance'] ?? null,
                'ref_id' => $refId,
                'data' => $body['data'] ?? [],
                'description' => $body['description'] ?? null,
                'message' => $message,
            ],
        ];
    }

    /**
     * Verifikasi signature webhook dari M-Link Gateway.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('ppob.providers.mlink.webhook_secret') ?: 'mlink_secret_key';
        $expected = hash_hmac('sha256', $rawBody, $secret);

        $cleanSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $cleanSignature);
    }

    /**
     * Custom Webhook Callback Handler untuk endpoint /api/callback/mlink.
     */
    public function handleWebhook(Request $request, Provider $provider): JsonResponse
    {
        $payload = $request->all();
        $header = $payload['header'] ?? [];
        $body = $payload['body'] ?? $payload;

        $reqId = $header['req_id'] ?? $request->input('req_id') ?? $request->input('transaction_id');
        $refId = $header['ref_id'] ?? $request->input('ref_id') ?? $request->input('provider_ref');
        $rc = (string) ($body['rc'] ?? $request->input('rc', ''));
        $statusStr = strtoupper((string) ($body['status'] ?? $request->input('status', '')));
        $sn = (string) ($body['serial_no'] ?? $request->input('serial_no', ''));
        $message = (string) ($body['message'] ?? $request->input('message', 'Update status webhook'));

        // Cari transaksi terkait di sistem
        $tx = null;
        if ($reqId) {
            $tx = Transaction::where('idempotency_key', $reqId)
                ->orWhere('id', $reqId)
                ->orWhere('provider_ref', $reqId)
                ->first();
        }
        if (! $tx && $refId) {
            $tx = Transaction::where('provider_ref', $refId)
                ->orWhere('idempotency_key', $refId)
                ->first();
        }

        if ($tx) {
            /** @var TransactionService $txService */
            $txService = app(TransactionService::class);

            // Hanya update jika belum terminal
            if (! in_array($tx->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_REFUNDED], true)) {
                if ($rc === '000' || in_array($statusStr, ['SUCCESS', 'PAID', 'SETTLEMENT', 'COMPLETED'], true)) {
                    $raw = array_merge($body, ['serial_number' => $sn ?: $tx->serial_number]);
                    $txService->markSuccess($tx, (string) ($refId ?: $tx->provider_ref), $raw);
                } elseif ($rc !== '030' && in_array($statusStr, ['FAILED', 'FAILURE', 'CANCELLED'], true)) {
                    $resolvedError = app(\App\Domain\Ppob\Services\ProviderErrorMappingService::class)->resolve(
                        $provider,
                        $rc,
                        $message
                    );
                    $raw = array_merge($body, [
                        'internal_error_code' => $resolvedError['internal_code'],
                        'upstream_rc' => $rc,
                    ]);
                    $txService->failAndRefund($tx, $resolvedError['message'], $raw);
                }
            }
        }

        return ApiResponse::success([
            'message' => 'Callback M-Link Gateway berhasil diproses',
            'req_id' => $reqId,
            'ref_id' => $refId,
        ]);
    }

    /**
     * Generator simulasi pengujian berdasarkan dokumen resmi QA M-Link Gateway.
     */
    private function simulateResponse(string $action, array $body, string $reqId): array
    {
        $refId = 'REF' . time() . rand(100, 999);
        $productId = (string) ($body['product_id'] ?? '');
        $custId = (string) ($body['cust_id'] ?? '');

        // 1. ProductInquiry
        if ($action === 'ProductInquiry') {
            return [
                'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                'body' => [
                    'product_id' => '',
                    'cust_id' => '',
                    'status' => 'SUCCESS',
                    'rc' => '000',
                    'description' => 'Transaction Success',
                    'message' => 'Please see data field',
                    'unit_price' => '0',
                    'balance_used' => '0',
                    'balance' => '6740781',
                    'serial_no' => '',
                    'data' => [
                        'product' => [
                            [
                                'operator' => 'DANA',
                                'product_id' => 'DNA10',
                                'denom' => '10000',
                                'description' => 'Saldo Dana 10rb',
                                'unit_price' => '10000',
                                'status' => 'OPEN',
                            ],
                            [
                                'operator' => 'Telkomsel',
                                'product_id' => 'TS10L3',
                                'denom' => '10000',
                                'description' => 'Telkomsel 10K L3',
                                'unit_price' => '10100',
                                'status' => 'OPEN',
                            ],
                            [
                                'operator' => 'PLN',
                                'product_id' => 'PLN20',
                                'denom' => '20000',
                                'description' => 'PLN Token 20K',
                                'unit_price' => '20500',
                                'status' => 'OPEN',
                            ],
                        ],
                    ],
                ],
            ];
        }

        // 2. Balance
        if ($action === 'Balance') {
            return [
                'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                'body' => [
                    'product_id' => '',
                    'cust_id' => '',
                    'status' => 'SUCCESS',
                    'rc' => '000',
                    'description' => 'Success',
                    'message' => 'Your balance: 500.001',
                    'unit_price' => '0',
                    'balance_used' => '0',
                    'balance' => '500001',
                    'serial_no' => '',
                    'data' => [],
                ],
            ];
        }

        // 3. CekStatus
        if ($action === 'CekStatus') {
            $orderReqId = (string) ($body['order_req_id'] ?? '');

            return [
                'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                'body' => [
                    'product_id' => $productId,
                    'cust_id' => $custId,
                    'status' => 'SUCCESS',
                    'rc' => '000',
                    'description' => 'Transaction Success',
                    'message' => 'Status checked successfully',
                    'serial_no' => 'SN-MLINK-STATUS-' . time(),
                    'unit_price' => '10100',
                    'balance_used' => '10100',
                    'balance' => '489901',
                    'data' => [],
                ],
            ];
        }

        // 4. Skenario Unit Test QA M-Link:
        // A. Pulsa (TS10L3 / TS5L3)
        if ($productId === 'TS10L3' || $productId === 'TS5L3') {
            return match ($custId) {
                '081380312001' => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'product_id' => $productId,
                        'cust_id' => $custId,
                        'status' => 'FAILED',
                        'rc' => '007',
                        'description' => 'Nomor pelanggan tidak ditemukan / salah',
                        'message' => 'Gagal - Nomor salah',
                        'serial_no' => '',
                    ],
                ],
                '081380312002' => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'product_id' => $productId,
                        'cust_id' => $custId,
                        'status' => 'FAILED',
                        'rc' => '006',
                        'description' => 'Gangguan koneksi biller',
                        'message' => 'Gagal - Gangguan Biller',
                        'serial_no' => '',
                    ],
                ],
                '081380312003' => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'product_id' => $productId,
                        'cust_id' => $custId,
                        'status' => 'PENDING',
                        'rc' => '030',
                        'description' => 'Transaksi Pending',
                        'message' => 'Pending upstream processing',
                        'serial_no' => '',
                    ],
                ],
                default => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'product_id' => $productId,
                        'cust_id' => $custId,
                        'status' => 'SUCCESS',
                        'rc' => '000',
                        'description' => 'Transaction Success',
                        'message' => "TRX {$productId} KE {$custId} , SUKSES. SAL=489901,HRG=10100,SN=0041000180894618",
                        'unit_price' => '10100',
                        'balance_used' => '10100',
                        'balance' => '489901',
                        'serial_no' => '0041000180894618',
                        'data' => [],
                    ],
                ],
            };
        }

        // B. PLN Prepaid Query & Topup
        if (str_starts_with(strtoupper($productId), 'PLN')) {
            if ($action === 'PLNPrepaidQuery') {
                return match ($custId) {
                    '12000000000' => [
                        'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                        'body' => [
                            'status' => 'FAILED',
                            'rc' => '007',
                            'description' => 'Nomor pelanggan tidak ditemukan / salah',
                            'message' => 'Gagal - Nomor tidak ditemukan',
                        ],
                    ],
                    '12000000003' => [
                        'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                        'body' => [
                            'status' => 'FAILED',
                            'rc' => '003',
                            'description' => 'Internal Processing Timeout',
                            'message' => 'Gagal - Timeout',
                        ],
                    ],
                    '12000000004' => [
                        'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                        'body' => [
                            'status' => 'FAILED',
                            'rc' => '006',
                            'description' => 'Gangguan koneksi biller',
                            'message' => 'Gagal - Gangguan Biller',
                        ],
                    ],
                    default => [
                        'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                        'body' => [
                            'product_id' => $productId,
                            'cust_id' => $custId,
                            'status' => 'SUCCESS',
                            'rc' => '000',
                            'description' => 'Transaction Success',
                            'message' => "meter={$custId};nama=MAHAYANA GARBILEKA 01;daya=R1 /2200 VA;id_pel=550000000001",
                            'unit_price' => '21000',
                            'data' => [
                                'no_meter' => $custId,
                                'id_pel' => '550000000001',
                                'nama' => 'MAHAYANA GARBILEKA 01',
                                'daya' => 'R1 /2200 VA',
                            ],
                        ],
                    ],
                };
            }

            // PLN Prepaid Topup
            return match ($custId) {
                '12000000002' => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'status' => 'PENDING',
                        'rc' => '030',
                        'description' => 'Transaksi Pending',
                        'message' => 'Pending - Sukses via Callback',
                    ],
                ],
                '12000000005' => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'status' => 'FAILED',
                        'rc' => '006',
                        'description' => 'Gangguan koneksi biller',
                        'message' => 'Gagal transaksi PLN',
                    ],
                ],
                default => [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'product_id' => $productId,
                        'cust_id' => $custId,
                        'status' => 'SUCCESS',
                        'rc' => '000',
                        'description' => 'Transaction Success',
                        'serial_no' => '3797-4760-0161-1083-4669',
                        'unit_price' => '21000',
                        'balance_used' => '21000',
                        'balance' => '347356',
                        'message' => 'TRX PLN BERHASIL. SN=3797-4760-0161-1083-4669',
                        'data' => [
                            'token' => '3797-4760-0161-1083-4669',
                            'kwh' => '13,4',
                            'nama' => 'MAHAYANA GARBILEKA 01',
                            'daya' => 'R1 /2200 VA',
                        ],
                    ],
                ],
            };
        }

        // C. E-Wallet (DNAP)
        if ($productId === 'DNAP' || str_starts_with(strtoupper($productId), 'GPC')) {
            if ($action === 'eWalletQuery') {
                return [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'product_id' => $productId,
                        'cust_id' => $custId,
                        'status' => 'SUCCESS',
                        'rc' => '000',
                        'description' => 'Transaction Success',
                        'data' => [
                            'cust_id' => $custId,
                            'name' => 'GOPAY NAMA AKUN 000',
                        ],
                    ],
                ];
            }

            // eWalletPayment
            if ($custId === '081380312001') {
                return [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'status' => 'FAILED',
                        'rc' => '007',
                        'description' => 'Nomor pelanggan tidak ditemukan / salah',
                        'message' => 'FAILED',
                    ],
                ];
            }

            if ($custId === '081380312003') {
                return [
                    'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                    'body' => [
                        'status' => 'PENDING',
                        'rc' => '030',
                        'description' => 'Transaksi Pending',
                        'message' => 'PENDING - FAILED ±5 detik',
                    ],
                ];
            }

            return [
                'header' => ['req_id' => $reqId, 'ref_id' => $refId],
                'body' => [
                    'product_id' => $productId,
                    'cust_id' => $custId,
                    'status' => 'SUCCESS',
                    'rc' => '000',
                    'description' => 'Transaction Success',
                    'serial_no' => '210531GM11365635/GOPAY NAMA AKUN 000',
                    'unit_price' => '21000',
                    'balance_used' => '21000',
                    'balance' => '428301',
                    'message' => 'TRX EWALLET BERHASIL',
                    'data' => [],
                ],
            ];
        }

        // Default Success fallback
        return [
            'header' => ['req_id' => $reqId, 'ref_id' => $refId],
            'body' => [
                'product_id' => $productId,
                'cust_id' => $custId,
                'status' => 'SUCCESS',
                'rc' => '000',
                'description' => 'Transaction Success',
                'serial_no' => 'SN-MLINK-' . time(),
                'unit_price' => '10000',
                'balance_used' => '10000',
                'balance' => '500000',
                'data' => [],
            ],
        ];
    }
}
