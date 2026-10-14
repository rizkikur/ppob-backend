# Task Spec: Phase 10 — Step 2: Realtime Dashboard & Operational Metrics

> **Dokumen Acuan**:
> - `docs/admin-web-architecture.md` (Spesifikasi Dashboard & Visual Monitoring)
> - `docs/task-specs/phase-10-step1-auth.md` (Fondasi Auth & Blade)
> - `docs/monitoring-alerting.md` (Metrik operasional & Circuit Breaker)
>
> **Selesai = Kode + Service Caching + Blade Component + Polling Alpine + Tests Pass**

---

## 1. Konteks & Tujuan

Dashboard adalah layar utama operasional untuk memantau kesehatan sistem secara live:
1. **Statistik Bisnis Harian**: GMV hari ini, Volume transaksi, Success rate (%), dan Gross profit.
2. **Antrean Operasional (Action Required)**: Jumlah transaksi pending / timeout, Jumlah request top-up manual menunggu approval.
3. **Status Sistem & Provider**: Status Circuit Breaker tiap provider (Closed/Normal, Half-Open, Open/Tripped), Sisa saldo deposit provider.
4. **Grafik Tren 24 Jam**: Visualisasi volume per jam menggunakan Chart.js (CDN).
5. **Live Polling Efisien**: Polling tiap 10 detik via Alpine.js dengan **Auto-Pause saat tab inactive** dan **Redis Caching (TTL 10s–30s)** agar database PostgreSQL tidak overload.

---

## 2. Struktur File Yang Dibuat

```
app/
└── Domain/
    └── Admin/
        ├── Services/
        │   └── AdminDashboardService.php
        ├── Http/
        │   └── Controllers/
        │       └── Dashboard/
        │           └── AdminDashboardController.php
resources/
└── views/
    └── admin/
        └── dashboard/
            ├── index.blade.php
            ├── components/
            │   ├── _kpi_cards.blade.php
            │   ├── _action_queue_cards.blade.php
            │   ├── _provider_status_card.blade.php
            │   ├── _transaction_chart.blade.php
            │   └── _recent_alerts_table.blade.php
routes/
└── admin.php (Group dashboard)
tests/Feature/Admin/
└── Dashboard/
    ├── AdminDashboardTest.php
    └── AdminDashboardCacheTest.php
```

---

## 3. Business Logic & Caching Strategy

### 3.1 Redis Cache TTL
Untuk menghindari query agregasi besar dieksekusi terus menerus setiap kali admin melakukan polling (10 detik):
- **Realtime Counters** (Pending Topup, Pending Transaksi, Provider Status): Cache TTL = **10 detik**.
- **Financial Aggregations** (GMV today, Success Rate, Total Vol): Cache TTL = **30 detik**.
- **24-Hour Trend Data** (Hourly buckets): Cache TTL = **60 detik**.

Cache tags yang digunakan: `['admin', 'dashboard']`. Saat ada mutasi transaksi atau approval top-up, event listener dapat melakukan `Cache::tags(['admin', 'dashboard'])->flush()`.

### 3.2 Role-Based Data Visibility
- `super_admin`: Melihat semua data (termasuk Gross Profit & Selisih Saldo).
- `finance_admin`: Melihat semua data finansial, deposit provider, dan volume transaksi.
- `ops_admin`: Melihat volume, success rate, pending queues, provider health. Nilai finansial sensitif seperti **Gross Profit** disamarkan (`Rp ***` atau disembunyikan via Blade `@can`).

---

## 4. Service: `AdminDashboardService`

Class `App\Domain\Admin\Services\AdminDashboardService`:

### 4.1 Method: `getSummaryMetrics(AdminRole $role): array`
Menghitung:
- `gmv_today`: `Transaction::whereDate('created_at', today())->where('status', 'SUCCESS')->sum('amount')`
- `profit_today`: Hanya untuk `super_admin` & `finance_admin`. `Transaction::whereDate('created_at', today())->where('status', 'SUCCESS')->sum(DB::raw('amount - cost_price'))`
- `transactions_count_today`: `Transaction::whereDate('created_at', today())->count()`
- `success_rate`: `(success_count / total_count) * 100` (dibulatkan 2 desimal)
- `pending_transactions_count`: `Transaction::whereIn('status', ['PENDING', 'PROCESSING'])->count()`
- `pending_topups_count`: `TopupRequest::where('status', 'PENDING')->count()`

### 4.2 Method: `getProviderHealthSummary(): array`
Mengambil data dari `providers` dan `circuit_breakers`:
- List semua provider aktif.
- Status Breaker: `NORMAL` (Closed), `DEGRADED` (Half-Open), `TRIPPED` (Open).
- Saldo deposit provider terkini (dari table / cached check).

### 4.3 Method: `getHourlyTrend24Hours(): array`
Mengembalikan array 24 elemen:
- Labels: `['00:00', '01:00', ..., '23:00']`
- Datasets:
  - `success`: Jumlah transaksi status SUCCESS per jam.
  - `failed`: Jumlah transaksi status FAILED per jam.

---

## 5. Controller: `AdminDashboardController`

Class `App\Domain\Admin\Http\Controllers\Dashboard\AdminDashboardController`:

### 5.1 Method: `index(Request $request): View`
- Mengambil data awal via `AdminDashboardService`.
- Melempar data ke view `admin.dashboard.index`.
- Inisialisasi initial state Alpine.js.

### 5.2 Method: `metrics(Request $request): JsonResponse`
- Endpoint API polling: `GET /admin/dashboard/metrics`
- Mengembalikan response JSON berformat standar:
  ```json
  {
    "success": true,
    "timestamp": "2026-10-12T11:00:00Z",
    "metrics": {
      "gmv_today": 154200000,
      "transactions_count": 1420,
      "success_rate": 98.4,
      "pending_transactions": 3,
      "pending_topups": 1
    },
    "provider_health": [
      { "name": "Digiflazz", "status": "NORMAL", "deposit": 12500000 },
      { "name": "IAK", "status": "DEGRADED", "deposit": 4200000 }
    ]
  }
  ```

---

## 6. Frontend: Alpine.js Polling & Visibility API

Di `resources/views/admin/dashboard/index.blade.php`:

```html
<div x-data="dashboardPoller()" x-init="startPolling()" class="space-y-6">
    {{-- KPI Cards --}}
    @include('admin.dashboard.components._kpi_cards')

    {{-- Urgent Queue Alerts --}}
    @include('admin.dashboard.components._action_queue_cards')

    {{-- Grid 2 Kolom: Chart 24 Jam & Provider Health --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            @include('admin.dashboard.components._transaction_chart')
        </div>
        <div class="lg:col-span-1">
            @include('admin.dashboard.components._provider_status_card')
        </div>
    </div>
</div>

<script nonce="{{ $csp_nonce }}">
function dashboardPoller() {
    return {
        timer: null,
        metrics: @json($initialMetrics),
        isLive: true,
        startPolling() {
            // Polling setiap 10 detik
            this.timer = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.fetchMetrics();
                }
            }, 10000);

            // Pause polling saat user pindah tab, resume saat kembali
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.fetchMetrics();
                }
            });
        },
        async fetchMetrics() {
            try {
                const res = await fetch('{{ route('admin.dashboard.metrics') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.metrics = data.metrics;
                    this.updateCards(data);
                }
            } catch (err) {
                console.warn('Polling dashboard error:', err);
            }
        },
        updateCards(data) {
            // Update widget reactive DOM
        }
    }
}
</script>
```

---

## 7. Routing (`routes/admin.php`)

```php
Route::middleware(['auth:admin', 'admin.csp'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/metrics', [AdminDashboardController::class, 'metrics'])->name('dashboard.metrics');
});
```

---

## 8. Feature Tests (Coverage Wajib)

File: `tests/Feature/Admin/Dashboard/AdminDashboardTest.php`:
1. `test_unauthenticated_user_cannot_access_dashboard()`
2. `test_admin_can_view_dashboard_with_all_components()`
3. `test_ops_admin_cannot_see_gross_profit_metric()`
4. `test_super_admin_and_finance_can_see_gross_profit()`
5. `test_metrics_endpoint_returns_valid_json_structure()`
6. `test_metrics_data_is_served_from_redis_cache_on_subsequent_calls()`
7. `test_pending_transaction_counter_matches_actual_database_records()`
8. `test_circuit_breaker_alert_badge_displayed_when_provider_is_tripped()`
