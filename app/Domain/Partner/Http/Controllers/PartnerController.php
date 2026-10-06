<?php

namespace App\Domain\Partner\Http\Controllers;

use App\Domain\Shared\Http\ApiController;
use App\Domain\Transaction\Http\Requests\CreateTransactionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Controller Open API untuk mitra bisnis. Semua endpoint dilindungi PartnerAuthMiddleware. */
class PartnerController extends ApiController
{
    public function createTransaction(CreateTransactionRequest $request): JsonResponse
    {
        // TODO: Implementasi di Phase 8
        return $this->error('INTERNAL_ERROR', 'Belum diimplementasikan', 501);
    }

    public function getTransaction(Request $request, string $partnerRef): JsonResponse
    {
        // TODO: Implementasi di Phase 8
        return $this->notFound('Transaksi tidak ditemukan');
    }
}
