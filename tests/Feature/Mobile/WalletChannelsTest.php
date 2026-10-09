<?php

namespace Tests\Feature\Mobile;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletChannelsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = UserTier::create([
            'name' => 'end_user',
            'label' => 'Konsumen',
        ]);

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Budi Channel Test',
            'phone' => '081233334444',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    public function test_guest_cannot_access_wallet_channels(): void
    {
        $response = $this->getJson('/wallet/channels');
        $response->assertStatus(401);

        $responseV1 = $this->getJson('/api/v1/wallet/channels');
        $responseV1->assertStatus(401);
    }

    public function test_authenticated_user_can_get_wallet_channels(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/wallet/channels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.group', 'Virtual Account')
            ->assertJsonPath('data.1.group', 'QRIS & E-Wallet')
            ->assertJsonPath('data.2.group', 'Transfer Bank Manual');
    }
}
