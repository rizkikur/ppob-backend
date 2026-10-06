<?php

namespace App\Domain\Partner\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Partner — mitra bisnis dengan akses Open API (B2B / H2H).
 * api_key: plaintext (digunakan sebagai identifier lookup).
 * secret: terenkripsi dengan Laravel encrypt(), digunakan untuk HMAC.
 * allowed_ips: array IP whitelist, null = semua IP diizinkan.
 */
class Partner extends Model
{
    protected $table = 'partners';

    protected $fillable = [
        'user_id',
        'name',
        'api_key',
        'secret',
        'allowed_ips',
        'is_active',
        'rate_limit_rpm',
        'protocol',
        'response_mode',
        'response_timeout_ms',
        'callback_url',
        'callback_secret',
    ];

    protected $hidden = ['secret', 'callback_secret'];

    protected $casts = [
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
        'rate_limit_rpm' => 'integer',
        'response_timeout_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PartnerLog::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(PartnerProductPrice::class);
    }

    public function getSecretDecrypted(): string
    {
        try {
            return decrypt($this->secret);
        } catch (\Throwable) {
            return $this->secret;
        }
    }

    /**
     * Hitung harga jual khusus partner untuk suatu produk (ADR-006).
     */
    public function getPriceForProduct(Product $product): Money
    {
        $customPrice = $this->productPrices()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();

        if ($customPrice && $customPrice->sell_price_cents) {
            return $customPrice->sell_price_cents;
        }

        // Fallback: harga modal + admin fee
        $base = $product->base_price_cents ?? Money::zero();
        $fee = $product->admin_fee_cents ?? Money::zero();

        return $base->add($fee);
    }

    /**
     * Ambil atau inisialisasi User representasi partner untuk pengelolaan dompet/saldo.
     */
    public function getOrCreateUser(): User
    {
        if ($this->user_id && $this->user) {
            return $this->user;
        }

        $tier = UserTier::where('name', 'partner')->first()
            ?? UserTier::firstOrCreate(
                ['name' => 'partner'],
                ['label' => 'Mitra / Partner', 'description' => 'Tier mitra B2B']
            );

        $user = User::firstOrCreate(
            ['email' => "partner_{$this->id}@partner.internal"],
            [
                'user_tier_id' => $tier->id,
                'name' => "Partner: {$this->name}",
                'phone' => '0899'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT),
                'role' => 'user',
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        if (! $this->user_id) {
            $this->updateQuietly(['user_id' => $user->id]);
        }

        // Pastikan dompet sudah ada
        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance_cents' => 0]
        );

        return $user;
    }

    /**
     * Ambil saldo deposit partner saat ini.
     */
    public function getBalance(): Money
    {
        $user = $this->getOrCreateUser();
        $wallet = Wallet::where('user_id', $user->id)->first();

        return $wallet ? $wallet->balance() : Money::zero();
    }
}
