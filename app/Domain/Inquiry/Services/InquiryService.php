<?php

namespace App\Domain\Inquiry\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Inquiry\Models\Inquiry;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Exceptions\BusinessException;

/**
 * Service inquiry tagihan untuk produk postpaid.
 * Aturan keras: hanya untuk product_type=postpaid.
 * Hasil inquiry expire setelah 10 menit.
 */
class InquiryService
{
    public function __construct(private readonly PpobService $ppobService) {}

    /** @throws BusinessException */
    public function inquiry(User $user, Product $product, string $customerNumber): Inquiry
    {
        if ($product->isPrepaid()) {
            throw BusinessException::productTypeMismatch('postpaid', 'prepaid');
        }
        if (! $product->is_active) {
            throw BusinessException::productInactive();
        }
        $result = $this->ppobService->inquiry($product, $customerNumber);

        return Inquiry::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'customer_number' => $customerNumber,
            'amount_cents' => $result['amount_cents'],
            'admin_fee_cents' => $product->admin_fee_cents->toCents(),
            'inquiry_ref' => $result['ref'],
            'provider_response' => $result['raw'],
            'status' => Inquiry::STATUS_SUCCESS,
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
