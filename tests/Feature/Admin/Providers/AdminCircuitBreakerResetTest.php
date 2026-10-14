<?php

namespace Tests\Feature\Admin\Providers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCircuitBreakerResetTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Provider $provider;
    private CircuitBreakerService $cbService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Ops Officer',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Digiflazz Upstream',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->cbService = app(CircuitBreakerService::class);
    }

    public function test_unauthenticated_user_cannot_reset_circuit_breaker(): void
    {
        $response = $this->post("/admin/providers/{$this->provider->id}/circuit-breaker/reset");

        $response->assertRedirect('/admin/login');
    }

    public function test_reset_circuit_breaker_restores_state_to_closed_and_records_audit(): void
    {
        // Trip circuit breaker to OPEN
        $this->cbService->forceOpen($this->provider->code);
        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->cbService->getState($this->provider->code));

        $this->actingAs($this->admin, 'admin');

        $response = $this->post("/admin/providers/{$this->provider->id}/circuit-breaker/reset", [
            'reason' => 'Upstream Digiflazz telah pulih dari pemeliharaan server',
        ]);

        $response->assertSessionHas('success');

        // State is restored to CLOSED
        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->cbService->getState($this->provider->code));
        $this->assertTrue($this->cbService->isAvailable($this->provider->code));

        // Audit log created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'provider.circuit_breaker_reset',
            'target_type' => 'Provider',
            'target_id' => (string) $this->provider->id,
            'reason' => 'Upstream Digiflazz telah pulih dari pemeliharaan server',
        ]);
    }

    public function test_admin_can_update_routing_priority(): void
    {
        $provider2 = Provider::create([
            'name' => 'IAK Fallback',
            'code' => 'IAK',
            'driver' => 'IakDriver',
            'priority' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/routing/priority', [
            'priorities' => [
                ['id' => $this->provider->id, 'priority' => 2],
                ['id' => $provider2->id, 'priority' => 1],
            ],
            'reason' => 'Mengalihkan prioritas utama ke IAK',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals(2, $this->provider->fresh()->priority);
        $this->assertEquals(1, $provider2->fresh()->priority);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'provider.routing_priority_update',
            'reason' => 'Mengalihkan prioritas utama ke IAK',
        ]);
    }

    public function test_admin_can_ping_provider(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->postJson("/admin/providers/{$this->provider->id}/ping");

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'latency_ms', 'checked_at']);
    }

    public function test_admin_can_update_circuit_breaker_config(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->put("/admin/providers/{$this->provider->id}/circuit-breaker/config", [
            'failure_threshold' => 7,
            'cooldown_seconds' => 90,
            'reason' => 'Menaikkan toleransi kegagalan selama peak hours',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'provider.circuit_breaker_config_update',
            'target_id' => (string) $this->provider->id,
            'reason' => 'Menaikkan toleransi kegagalan selama peak hours',
        ]);
    }
}

