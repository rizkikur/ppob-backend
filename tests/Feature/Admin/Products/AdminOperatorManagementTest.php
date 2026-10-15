<?php

namespace Tests\Feature\Admin\Products;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Ppob\Services\SupplierRoutingService;
use App\Domain\Product\Models\Operator;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOperatorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private ProductCategory $category;
    private Provider $providerA;
    private Provider $providerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Pulsa Reguler',
            'code' => 'PULSA',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->providerA = Provider::create([
            'name' => 'Digiflazz Supplier',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->providerB = Provider::create([
            'name' => 'Baseindo Supplier',
            'code' => 'BASEINDO',
            'driver' => 'BaseindoDriver',
            'priority' => 2,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_operators(): void
    {
        $response = $this->get(route('admin.operators.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_operators_index(): void
    {
        Operator::create([
            'category_id' => $this->category->id,
            'name' => 'Telkomsel',
            'code' => 'TELKOMSEL',
            'prefix_rules' => ['0811', '0812', '0813', '0821'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.operators.index'));

        $response->assertOk();
        $response->assertSee('Master Operator');
        $response->assertSee('Telkomsel');
        $response->assertSee('0811, 0812, 0813, 0821');
    }

    public function test_admin_can_create_operator_with_prefixes(): void
    {
        $payload = [
            'category_id' => $this->category->id,
            'name' => 'Indosat Ooredoo',
            'code' => 'INDOSAT',
            'prefixes' => "0814, 0815, 0816, \n 0855, 0856, 0857",
            'sort_order' => 2,
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.operators.store'), $payload);

        $response->assertRedirect(route('admin.operators.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('operators', [
            'name' => 'Indosat Ooredoo',
            'code' => 'INDOSAT',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $operator = Operator::where('code', 'INDOSAT')->first();
        $this->assertNotNull($operator);
        $this->assertContains('0814', $operator->prefix_rules);
        $this->assertContains('0857', $operator->prefix_rules);
    }

    public function test_admin_can_update_operator(): void
    {
        $operator = Operator::create([
            'category_id' => $this->category->id,
            'name' => 'XL Axiata',
            'code' => 'XL',
            'prefix_rules' => ['0817', '0818'],
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.operators.update', $operator), [
                'name' => 'XL Axiata Updated',
                'code' => 'XL_AXIATA',
                'prefixes' => '0817, 0818, 0819, 0859',
                'sort_order' => 5,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.operators.index'));

        $operator->refresh();
        $this->assertSame('XL Axiata Updated', $operator->name);
        $this->assertSame('XL_AXIATA', $operator->code);
        $this->assertCount(4, $operator->prefix_rules);
        $this->assertContains('0859', $operator->prefix_rules);
    }

    public function test_live_prefix_detection_api_detects_operator_accurately(): void
    {
        Operator::create([
            'category_id' => $this->category->id,
            'name' => 'Telkomsel',
            'code' => 'TELKOMSEL',
            'prefix_rules' => ['0811', '0812', '0813', '0821', '0822'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Operator::create([
            'category_id' => $this->category->id,
            'name' => 'Indosat',
            'code' => 'INDOSAT',
            'prefix_rules' => ['0814', '0815', '0856', '0857'],
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // Standard 0812 number
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.operators.detect'), ['phone' => '08123456789']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'operator' => [
                'name' => 'Telkomsel',
                'code' => 'TELKOMSEL',
            ],
        ]);

        // International prefix +62857 number
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.operators.detect'), ['phone' => '+628571234567']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'operator' => [
                'name' => 'Indosat',
                'code' => 'INDOSAT',
            ],
        ]);

        // Unknown number
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.operators.detect'), ['phone' => '08999999999']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'detected' => false,
            'operator' => null,
        ]);
    }

    public function test_admin_can_create_product_with_operator_and_routes_with_supplier_sku(): void
    {
        $operator = Operator::create([
            'category_id' => $this->category->id,
            'name' => 'Telkomsel',
            'code' => 'TELKOMSEL',
            'prefix_rules' => ['0812'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Create product
        $createPayload = [
            'sku_code' => 'TSEL10',
            'name' => 'Telkomsel 10.000',
            'category_id' => $this->category->id,
            'operator_id' => $operator->id,
            'provider_id' => $this->providerA->id,
            'base_price_rupiah' => 10100,
            'product_type' => 'prepaid',
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $createPayload);

        $product = Product::where('sku_code', 'TSEL10')->first();
        $this->assertNotNull($product);
        $this->assertEquals($operator->id, $product->operator_id);
        $this->assertSame('Telkomsel', $product->operator->name);

        // Update supplier routes with custom supplier_sku and costs
        $routesPayload = [
            'routes' => [
                [
                    'provider_id' => $this->providerA->id,
                    'supplier_sku' => 'digi-tsel-10k',
                    'cost_rupiah' => 10100,
                    'priority' => 1,
                    'is_active' => 1,
                ],
                [
                    'provider_id' => $this->providerB->id,
                    'supplier_sku' => 'base-tsel-10000',
                    'cost_rupiah' => 10150,
                    'priority' => 2,
                    'is_active' => 1,
                ],
            ],
        ];

        $routeResponse = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.routes.update', $product), $routesPayload);

        $routeResponse->assertRedirect();
        $routeResponse->assertSessionHas('success');

        $this->assertDatabaseHas('product_supplier_routes', [
            'product_id' => $product->id,
            'provider_id' => $this->providerA->id,
            'supplier_sku' => 'digi-tsel-10k',
            'priority' => 1,
        ]);

        $this->assertDatabaseHas('product_supplier_routes', [
            'product_id' => $product->id,
            'provider_id' => $this->providerB->id,
            'supplier_sku' => 'base-tsel-10000',
            'priority' => 2,
        ]);
    }

    public function test_supplier_routing_service_resolves_route_with_custom_supplier_sku(): void
    {
        $operator = Operator::create([
            'category_id' => $this->category->id,
            'name' => 'Telkomsel',
            'code' => 'TELKOMSEL',
            'prefix_rules' => ['0812'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku_code' => 'TSEL50',
            'name' => 'Telkomsel 50.000',
            'category_id' => $this->category->id,
            'operator_id' => $operator->id,
            'provider_id' => $this->providerA->id,
            'base_price_cents' => Money::fromCents(5000000),
            'product_type' => 'prepaid',
            'is_active' => true,
        ]);

        ProductSupplierRoute::create([
            'product_id' => $product->id,
            'provider_id' => $this->providerA->id,
            'supplier_sku' => 'dna50_upstream',
            'cost_cents' => Money::fromCents(4980000),
            'priority' => 1,
            'is_active' => true,
        ]);

        $routingService = app(SupplierRoutingService::class);
        $resolved = $routingService->resolve($product);

        $this->assertNotNull($resolved);
        $this->assertSame($this->providerA->id, $resolved->provider->id);
        $this->assertSame('dna50_upstream', $resolved->supplierSku);
        $this->assertSame('dna50_upstream', $resolved->getEffectiveSku($product->sku_code));
        $this->assertEquals(4980000, $resolved->costCents);
    }
}
