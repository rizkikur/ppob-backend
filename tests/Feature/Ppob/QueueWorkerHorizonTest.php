<?php

namespace Tests\Feature\Ppob;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Services\TransactionService;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueWorkerHorizonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Regular user']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Queue Test User',
            'phone' => '089999990001',
            'email' => 'queue_user@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 100000000, // Rp 1.000.000,00
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_horizon_config_file_has_all_adr002_supplier_queues(): void
    {
        $this->assertFileExists(config_path('horizon.php'));

        $config = config('horizon.environments.production');
        $this->assertIsArray($config);

        $expectedSupervisors = [
            'supervisor-telkomsel' => 'supplier_telkomsel',
            'supervisor-indosat' => 'supplier_indosat',
            'supervisor-xl' => 'supplier_xl',
            'supervisor-pln' => 'supplier_pln',
            'supervisor-pdam' => 'supplier_pdam',
        ];

        foreach ($expectedSupervisors as $supervisorKey => $expectedQueue) {
            $this->assertArrayHasKey($supervisorKey, $config, "Supervisor {$supervisorKey} harus ada di config/horizon.php");
            $this->assertContains(
                $expectedQueue,
                $config[$supervisorKey]['queue'],
                "Queue {$expectedQueue} harus dipantau oleh {$supervisorKey}"
            );
            $this->assertGreaterThan(0, $config[$supervisorKey]['maxProcesses']);
        }

        // Cek default supervisor untuk transaksi umum
        $this->assertArrayHasKey('supervisor-default', $config);
        $this->assertContains('transactions', $config['supervisor-default']['queue']);
    }

    public function test_supervisor_conf_file_exists_and_contains_all_named_programs(): void
    {
        $confPath = base_path('deploy/supervisor/ppob-worker.conf');
        $this->assertFileExists($confPath);

        $content = file_get_contents($confPath);
        $this->assertStringContainsString('[program:ppob-worker-telkomsel]', $content);
        $this->assertStringContainsString('[program:ppob-worker-indosat]', $content);
        $this->assertStringContainsString('[program:ppob-worker-xl]', $content);
        $this->assertStringContainsString('[program:ppob-worker-pln]', $content);
        $this->assertStringContainsString('[program:ppob-worker-pdam]', $content);
        $this->assertStringContainsString('[program:ppob-worker-default]', $content);
    }

    public function test_transaction_jobs_are_dispatched_to_provider_specific_queues(): void
    {
        Queue::fake();

        $suppliers = [
            ['code' => 'telkomsel', 'queue' => 'supplier_telkomsel', 'driver' => 'TelkomselDriver'],
            ['code' => 'indosat',   'queue' => 'supplier_indosat',   'driver' => 'IndosatDriver'],
            ['code' => 'xl',        'queue' => 'supplier_xl',        'driver' => 'XlDriver'],
            ['code' => 'pln',       'queue' => 'supplier_pln',       'driver' => 'PlnDriver'],
            ['code' => 'pdam',      'queue' => 'supplier_pdam',      'driver' => 'PdamDriver'],
        ];

        /** @var TransactionService $txService */
        $txService = app(TransactionService::class);

        foreach ($suppliers as $s) {
            $provider = Provider::create([
                'name' => ucfirst($s['code']),
                'code' => $s['code'],
                'driver' => $s['driver'],
                'queue_name' => $s['queue'],
                'max_workers' => 20,
                'rate_limit_per_minute' => 60,
                'timeout_seconds' => 30,
                'priority' => 1,
                'is_active' => true,
            ]);

            $product = Product::create([
                'category_id' => $this->category->id,
                'provider_id' => $provider->id,
                'sku_code' => 'TEST_'.strtoupper($s['code']),
                'name' => 'Product '.ucfirst($s['code']),
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 1000000,
                'admin_fee_cents' => 100000,
                'is_active' => true,
            ]);

            $txService->create($this->user, $product, '081234567890');

            // Verifikasi bahwa job transaksi didispatch ke queue spesifik supplier tersebut (bukan default)
            Queue::assertPushedOn($s['queue'], ProcessTransactionJob::class);
        }
    }

    public function test_artisan_queue_status_command_executes_successfully(): void
    {
        Provider::create([
            'name' => 'Telkomsel',
            'code' => 'telkomsel',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_telkomsel',
            'max_workers' => 100,
            'rate_limit_per_minute' => 100,
            'timeout_seconds' => 30,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->artisan('ppob:queue:status')
            ->expectsOutputToContain('PPOB Supplier Queue Worker Configuration')
            ->expectsOutputToContain('Total Alokasi Workers: 100 processes')
            ->assertExitCode(0);
    }
}
