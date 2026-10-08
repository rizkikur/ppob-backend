<?php

namespace App\Domain\Partner\Models;

use App\Domain\Product\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model PartnerRoutingRule — aturan routing dan failover per mitra (ADR-004).
 * Menentukan preferred supplier, apakah boleh failover jika down, dan cakupan failover.
 */
class PartnerRoutingRule extends Model
{
    protected $table = 'partner_routing_rules';

    public const POLICY_NONE = 'none';

    public const POLICY_SAME_CATEGORY = 'same_category';

    public const POLICY_ANY = 'any';

    protected $fillable = [
        'partner_id',
        'category_code',
        'preferred_provider_id',
        'allow_failover',
        'failover_policy',
    ];

    protected $casts = [
        'allow_failover' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function preferredProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'preferred_provider_id');
    }

    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeForCategory(Builder $query, ?string $categoryCode): Builder
    {
        return $query->where('category_code', $categoryCode);
    }
}
