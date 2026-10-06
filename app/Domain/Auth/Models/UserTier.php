<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model UserTier — tier/level pengguna untuk penentuan harga.
 *
 * Contoh tier: end_user, agent, reseller
 */
class UserTier extends Model
{
    protected $table = 'user_tiers';

    protected $fillable = [
        'name',
        'label',
        'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_tier_id');
    }
}
