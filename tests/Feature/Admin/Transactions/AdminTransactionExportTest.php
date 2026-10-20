<?php

namespace Tests\Feature\Admin\Transactions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Jobs\ExportTransactionsJob;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\TransactionExportJob;
use App\Domain\Admin\Services\AdminTransactionManagementService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTransactionExportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Admin $otherAdmin;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = Admin::create([
            'name' => 'Admin Ops',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->otherAdmin = Admin::create([
            'name' => 'Other Admin',
            'email' => 'other@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular tier']
        );

        $this->user = User::create([
            'name' => 'Pelanggan Test',
            'phone' => '081234567890',
            'user_tier_id' => $tier->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'PULSA',
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10K',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);

        // Sample transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'export-test-1',
            'created_at' => now(),
        ]);
    }

    public function test_unauthenticated_admin_cannot_dispatch_async_export(): void
    {
        $response = $this->postJson(route('admin.transactions.export.async'));
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_dispatch_async_export_job(): void
    {
        Queue::fake();

        $this->actingAs($this->admin, 'admin');

        $response = $this->postJson(route('admin.transactions.export.async'), [
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'status' => 'success',
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'job' => [
                'status' => 'pending',
                'progress_pct' => 0,
            ],
        ]);

        $this->assertDatabaseHas('transaction_export_jobs', [
            'admin_id' => $this->admin->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ExportTransactionsJob::class);
    }

    public function test_export_transactions_job_generates_valid_csv_in_storage(): void
    {
        $exportJob = TransactionExportJob::create([
            'admin_id' => $this->admin->id,
            'filename' => 'test-export.csv',
            'filters' => [
                'date_start' => now()->toDateString(),
                'date_end' => now()->toDateString(),
            ],
            'status' => TransactionExportJob::STATUS_PENDING,
        ]);

        $job = new ExportTransactionsJob($exportJob);
        $job->handle(app(AdminTransactionManagementService::class));

        $exportJob->refresh();

        $this->assertTrue($exportJob->isCompleted());
        $this->assertEquals(1, $exportJob->total_rows);
        $this->assertEquals(1, $exportJob->processed_rows);
        $this->assertNotNull($exportJob->file_path);
        $this->assertGreaterThan(0, $exportJob->file_size_bytes);

        // Verify CSV contents in fake storage
        $this->assertTrue(Storage::disk('local')->exists($exportJob->file_path));
        $content = Storage::disk('local')->get($exportJob->file_path);
        $this->assertStringContainsString('ID Transaksi', $content);
        $this->assertStringContainsString('Pelanggan Test', $content);
        $this->assertStringContainsString('081234567890', $content);
        $this->assertStringContainsString('SUCCESS', $content);
    }

    public function test_admin_can_poll_job_status_and_download_csv(): void
    {
        $exportJob = TransactionExportJob::create([
            'admin_id' => $this->admin->id,
            'filename' => 'completed-export.csv',
            'filters' => [],
            'status' => TransactionExportJob::STATUS_COMPLETED,
            'file_path' => 'exports/1_completed-export.csv',
            'file_size_bytes' => 1024,
            'total_rows' => 50,
            'processed_rows' => 50,
            'completed_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        Storage::disk('local')->put('exports/1_completed-export.csv', 'dummy,csv,content');

        $this->actingAs($this->admin, 'admin');

        // Check status
        $statusResponse = $this->getJson(route('admin.transactions.export.status', $exportJob));
        $statusResponse->assertStatus(200);
        $statusResponse->assertJson([
            'success' => true,
            'job' => [
                'id' => $exportJob->id,
                'status' => 'completed',
                'progress_pct' => 100,
                'is_completed' => true,
            ],
        ]);

        // Download file
        $downloadResponse = $this->get(route('admin.transactions.export.download', $exportJob));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename=completed-export.csv');
    }

    public function test_other_admin_cannot_download_unauthorized_export_file(): void
    {
        $exportJob = TransactionExportJob::create([
            'admin_id' => $this->admin->id,
            'filename' => 'secret-export.csv',
            'filters' => [],
            'status' => TransactionExportJob::STATUS_COMPLETED,
            'file_path' => 'exports/secret.csv',
            'file_size_bytes' => 500,
            'completed_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        Storage::disk('local')->put('exports/secret.csv', 'confidential');

        // Logged in as other admin
        $this->actingAs($this->otherAdmin, 'admin');

        $response = $this->get(route('admin.transactions.export.download', $exportJob));
        $response->assertStatus(403);
    }

    public function test_cleanup_command_removes_expired_export_files(): void
    {
        $expiredJob = TransactionExportJob::create([
            'admin_id' => $this->admin->id,
            'filename' => 'expired.csv',
            'status' => TransactionExportJob::STATUS_COMPLETED,
            'file_path' => 'exports/expired.csv',
            'file_size_bytes' => 100,
            'completed_at' => now()->subHours(30),
            'expires_at' => now()->subHours(6),
            'created_at' => now()->subHours(30),
        ]);

        Storage::disk('local')->put('exports/expired.csv', 'expired content');

        $this->artisan('exports:cleanup', ['--hours' => 24])
            ->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists('exports/expired.csv'));
        $this->assertDatabaseMissing('transaction_export_jobs', ['id' => $expiredJob->id]);
    }
}
