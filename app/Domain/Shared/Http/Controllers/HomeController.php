<?php

namespace App\Domain\Shared\Http\Controllers;

use App\Domain\Product\Http\Resources\CategoryResource;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Shared\Http\ApiController;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends ApiController
{
    /**
     * GET /home — Home Dashboard Summary untuk Mobile Apps.
     * Mengembalikan profil ringkas, saldo dompet, kategori aktif, dan 5 transaksi terakhir.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['tier', 'wallet']);
        $wallet = $user->wallet;

        $categories = ProductCategory::active()
            ->orderBy('sort_order')
            ->get();

        $recentTransactions = Transaction::with(['product.provider', 'product.category'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Transaction $tx) {
                return [
                    'id' => $tx->id,
                    'sku_code' => $tx->product?->sku_code,
                    'product_name' => $tx->product?->name ?? 'Produk PPOB',
                    'customer_number' => $tx->customer_number,
                    'amount_cents' => $tx->amount_cents?->toCents() ?? 0,
                    'amount_display' => $tx->amount_cents?->format() ?? 'Rp 0',
                    'sell_price_cents' => $tx->sell_price_cents?->toCents() ?? 0,
                    'sell_price_display' => $tx->sell_price_cents?->format() ?? 'Rp 0',
                    'status' => $tx->status,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            });

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'tier' => [
                    'name' => $user->tier?->name ?? 'end_user',
                    'label' => $user->tier?->label ?? 'End User',
                ],
                'is_verified' => $user->is_verified,
            ],
            'wallet' => [
                'balance_cents' => $wallet?->balance_cents?->toCents() ?? 0,
                'balance_display' => $wallet?->balance_cents?->format() ?? 'Rp 0',
                'currency' => 'IDR',
            ],
            'categories' => $categories->map(fn ($c) => new CategoryResource($c)),
            'recent_transactions' => $recentTransactions,
        ], 'Data home dashboard berhasil dimuat');
    }
}
