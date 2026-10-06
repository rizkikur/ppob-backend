<?php

namespace App\Domain\Partner\Http\Controllers;

use App\Domain\Partner\Http\Requests\CreatePartnerTransactionRequest;
use App\Domain\Partner\Http\Resources\PartnerProductResource;
use App\Domain\Partner\Http\Resources\PartnerTransactionResource;
use App\Domain\Partner\Models\Partner;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Http\ApiController;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Open API untuk mitra bisnis (B2B / H2H).
 * Semua endpoint dilindungi PartnerAuthMiddleware.
 */
class PartnerController extends ApiController
{
    public function __construct(private readonly TransactionService $transactionService) {}

    /**
     * Cek saldo deposit mitra.
     * GET /partner/balance
     */
    public function balance(Request $request): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');
        $balance = $partner->getBalance();

        return $this->success([
            'partner_id' => $partner->id,
            'partner_name' => $partner->name,
            'balance' => $balance->toCents(),
            'balance_display' => $balance->format(),
            'currency' => 'IDR',
        ], 'Saldo mitra berhasil diambil');
    }

    /**
     * Katalog produk dengan harga jual partner.
     * GET /partner/products
     */
    public function products(Request $request): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');

        $products = Product::query()
            ->where('is_active', true)
            ->with(['category', 'provider'])
            ->when($request->query('category'), function ($q, $slug) {
                $q->whereHas('category', fn ($c) => $c->where('slug', $slug));
            })
            ->when($request->query('type'), function ($q, $type) {
                $q->where('product_type', $type);
            })
            ->get();

        $resourceCollection = $products->map(function ($product) use ($partner) {
            $price = $partner->getPriceForProduct($product);

            return new PartnerProductResource($product, $price);
        });

        return $this->success($resourceCollection, 'Daftar produk berhasil diambil');
    }

    /**
     * Buat transaksi PPOB baru atas nama partner.
     * POST /partner/transactions
     */
    public function createTransaction(CreatePartnerTransactionRequest $request): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');
        $data = $request->validated();

        $product = Product::where('sku_code', $data['sku_code'])->first();
        if (! $product) {
            throw BusinessException::productNotFound($data['sku_code']);
        }

        if (! $product->is_active) {
            throw BusinessException::productInactive();
        }

        $tx = $this->transactionService->createForPartner(
            $partner,
            $product,
            $data['customer_number'],
            $data['inquiry_id'] ?? null,
            $data['amount'] ?? null
        );

        $tx->loadMissing(['product']);

        return $this->created(new PartnerTransactionResource($tx), 'Transaksi berhasil dibuat');
    }

    /**
     * Cek status transaksi via ID transaksi atau Idempotency-Key.
     * GET /partner/transactions/{partnerRef}
     */
    public function getTransaction(Request $request, string $partnerRef): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');

        $tx = Transaction::where('partner_id', $partner->id)
            ->where(function ($query) use ($partnerRef) {
                if (is_numeric($partnerRef)) {
                    $query->where('id', (int) $partnerRef)
                        ->orWhere('idempotency_key', $partnerRef);
                } else {
                    $query->where('idempotency_key', $partnerRef);
                }
            })
            ->with(['product'])
            ->first();

        if (! $tx) {
            return $this->notFound('Transaksi tidak ditemukan');
        }

        return $this->success(new PartnerTransactionResource($tx), 'Data transaksi ditemukan');
    }
}
