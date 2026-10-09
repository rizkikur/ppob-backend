<?php

namespace Tests\Feature\Mobile;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileFcmTest extends TestCase
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
            'name' => 'Budi Profile Test',
            'phone' => '081255556666',
            'email' => 'budi.profile@example.com',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    public function test_guest_cannot_update_profile_or_fcm_token(): void
    {
        $this->putJson('/auth/profile', ['name' => 'Baru'])->assertStatus(401);
        $this->postJson('/auth/fcm-token', ['fcm_token' => 'token123'])->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/auth/profile', [
            'name' => 'Budi Updated',
            'email' => 'budi.new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Budi Updated')
            ->assertJsonPath('data.email', 'budi.new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Budi Updated',
            'email' => 'budi.new@example.com',
        ]);
    }

    public function test_user_cannot_update_email_if_already_taken(): void
    {
        Sanctum::actingAs($this->user);

        User::create([
            'user_tier_id' => $this->user->user_tier_id,
            'name' => 'User Lain',
            'phone' => '081277778888',
            'email' => 'taken@example.com',
            'is_active' => true,
        ]);

        $response = $this->putJson('/auth/profile', [
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_update_fcm_token(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/auth/fcm-token', [
            'fcm_token' => 'fcm_device_token_xyz_1234567890',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fcm_token', 'fcm_device_token_xyz_1234567890');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'fcm_token' => 'fcm_device_token_xyz_1234567890',
        ]);
    }
}
