# Task Spec: Phase 10 — Step 4: Katalog Produk, Provider & Circuit Breaker

> **Dokumen Acuan**:
> - `docs/admin-web-architecture.md` (Spesifikasi Produk & Provider)
> - `docs/task-specs/phase-4-product.md` (Domain Produk & Tiering Harga)
> - `docs/task-specs/phase-7-ppob.md` (Provider Routing & Circuit Breaker)
> - `docs/task-specs/phase-10-step1-auth.md` (Audit Trail)
>
> **Selesai = Controller + Services + Blade Catalog + Alpine Priority UI + Tests Pass**

---

## 1. Konteks & Tujuan

Modul ini mengelola penawaran produk digital dan integrasi upstream:
1. **Katalog Produk & Kategori**: Manajemen produk pulsa, data, PLN, voucher game, dan e-wallet.
2. **Pemetaan SKU Provider**: Menghubungkan kode produk internal (misal: `TELKOMSEL_10K`) dengan kode SKU masing-masing provider (Digiflazz, IAK, OTOMAX).
3. **Konfigurasi Margin & Tiering Harga**: Menentukan harga jual per tingkat pengguna (`user_tiers`: Regular, Silver, Gold, Platinum) dan harga khusus mitra B2B.
4. **Kontrol Cepat Provider & Circuit Breaker**: Toggle status provider (Active, Maintenance, Disabled) dan reset counter kegagalan Circuit Breaker secara instan saat insiden.
5. **Kebijakan Akses**: Sesuai kesepakatan, seluruh role admin (`ops`, `finance`, `super_admin`) diizinkan melakukan emergency toggle provider demi kecepatan mitigasi gangguan, dengan **pencatatan penuh ke `admin_audit_logs`**.

---

## 2. Struktur File Yang Dibuat

```
app/
└── Domain/
    └── Admin/
        ├── Http/
        │   ├── Controllers/
        │   │   ├── Product/
        │   │   │   ├── AdminProductController.php
        │   │   │   ├── AdminCategoryController.php
        │   │   │   └── AdminProductPriceController.php
        │   │   └── Provider/
        │   │       ├── AdminProviderController.php
        │   │       ├── AdminCircuitBreakerController.php
        │   │       └── AdminRoutingPriorityController.php
        │   └── Requests/
        │       ├── StoreProductRequest.php
        │       ├── UpdateProductRequest.php
        │       ├── UpdateTierPricesRequest.php
        │       ├── ToggleProviderStatusRequest.php
        │       └── UpdateRoutingPriorityRequest.php
        └── Services/
            ├── AdminProductManagementService.php
            └── AdminProviderManagementService.php
resources/
└── views/
    └── admin/
        ├── products/
        │   ├── index.blade.php
        │   ├── create.blade.php
        │   ├── edit.blade.php
        │   └── components/
        │       ├── _price_tier_matrix.blade.php
        │       └── _sku_mapping_table.blade.php
        └── providers/
            ├── index.blade.php
            ├── show.blade.php
            └── components/
                ├── _circuit_breaker_panel.blade.php
                └── _routing_priority_drag.blade.php
routes/
└── admin.php (Group products & providers)
tests/Feature/Admin/
├── Products/
│   ├── AdminProductCrudTest.php
│   └── AdminProductPriceTierTest.php
└── Providers/
    ├── AdminProviderToggleTest.php
    └── AdminCircuitBreakerResetTest.php
```

---

## 3. Aturan Bisnis & Validasi

1. **Integritas Margin Harga**:
   - Harga jual ke tier pengguna **TIDAK BOLEH lebih rendah** dari harga modal provider (`base_cost`). Form validation wajib mencegah margin negatif kecuali jika diberi flag diskon promo khusus yang disetujui.
   - Hirarki harga: `Harga Platinum <= Harga Gold <= Harga Silver <= Harga Regular`.
2. **Emergency Provider Toggle**:
   - Ketika provider dimatikan (`is_active = false` atau status `MAINTENANCE`), semua routing otomatis mengalihkan request transaksi berikutnya ke provider fallback prioritas kedua.
   - Cache status provider dan circuit breaker di Redis wajib di-invalidate seketika (`Cache::tags(['routing', 'providers'])->flush()`).
3. **Audit Trail Wajib**:
   - Setiap perubahan status provider, perubahan harga margin, dan pergantian prioritas routing wajib dicatat ke `admin_audit_logs`.

---

## 4. Controllers & Services

### 4.1 `AdminProductController` & `AdminProductPriceController`
- `index()`: Daftar produk dengan filter kategori, provider sumber, dan status aktif.
- `edit(Product $product)`: Form edit info produk, SKU provider, dan matriks harga tier.
- `update(UpdateProductRequest $request, Product $product)`: Update info dasar produk.
- `updatePrices(UpdateTierPricesRequest $request, Product $product)`:
  - Menyimpan harga tier ke table `product_tier_prices`.
  - Log audit perubahan harga lama vs harga baru.

### 4.2 `AdminProviderController`
- `index()`: List semua provider (Digiflazz, IAK, OTOMAX, dll.), saldo deposit, dan status breaker.
- `toggleStatus(ToggleProviderStatusRequest $request, Provider $provider)`:
  - Mengubah status provider (`is_active`, `status`).
  - Mengosongkan cache routing.
  - Menulis log ke `admin_audit_logs` dengan `reason`.

### 4.3 `AdminCircuitBreakerController`
- `reset(Provider $provider, Request $request)`:
  - Mereset counter error kegagalan ke 0 di Redis/Database via `CircuitBreakerService::reset($provider->code)`.
  - Mengembalikan status breaker dari `TRIPPED` ke `CLOSED` (Normal).
  - Tulis audit log: `provider.circuit_breaker_reset`.

### 4.4 `AdminRoutingPriorityController`
- `update(UpdateRoutingPriorityRequest $request)`:
  - Menerima urutan prioritas array `provider_ids` untuk kategori atau produk tertentu.
  - Memperbarui kolom `priority` di database.
  - Invalidate cache routing transaksi.

---

## 5. Frontend & UI Komponen

1. **Tabel Matriks Harga Tier (`_price_tier_matrix.blade.php`)**:
   - Menampilkan kolom Modal Provider, Margin Nominal, Margin %, dan Harga Final per Tier (Regular, Silver, Gold, Platinum).
   - Dynamic calculation via Alpine.js: saat modal berubah atau margin diisi, harga tier otomatis terhitung sebelum disubmit.
2. **Circuit Breaker Status Panel (`_circuit_breaker_panel.blade.php`)**:
   - Tampilan visual warna indikator: Hijau (Normal), Kuning (Half-Open), Merah (Tripped / Open).
   - Tombol "Reset Circuit Breaker" dengan modal konfirmasi Alpine.js.
   - Tampilan persentase kegagalan dalam interval 5 menit terakhir.

---

## 6. Routing (`routes/admin.php`)

```php
Route::middleware(['auth:admin', 'admin.csp'])->group(function () {
    // Produk & Kategori
    Route::resource('categories', AdminCategoryController::class)->except(['show']);
    Route::resource('products', AdminProductController::class);
    Route::put('products/{product}/prices', [AdminProductPriceController::class, 'updatePrices'])
        ->name('products.prices.update');

    // Provider & Routing
    Route::get('providers', [AdminProviderController::class, 'index'])->name('providers.index');
    Route::get('providers/{provider}', [AdminProviderController::class, 'show'])->name('providers.show');
    Route::post('providers/{provider}/toggle', [AdminProviderController::class, 'toggleStatus'])
        ->name('providers.toggle');
    Route::post('providers/{provider}/circuit-breaker/reset', [AdminCircuitBreakerController::class, 'reset'])
        ->name('providers.circuit_breaker.reset');
    Route::post('routing/priority', [AdminRoutingPriorityController::class, 'update'])
        ->name('routing.priority.update');
});
```

---

## 7. Feature Tests (Coverage Wajib)

File di `tests/Feature/Admin/Products/` & `Providers/`:
1. `AdminProductCrudTest.php`:
   - `test_admin_can_view_product_list()`
   - `test_admin_can_update_product_metadata()`
2. `AdminProductPriceTierTest.php`:
   - `test_validation_prevents_selling_price_below_provider_base_cost()`
   - `test_updating_tier_prices_persists_and_logs_audit_trail()`
   - `test_price_hierarchy_rule_is_enforced()`
3. `AdminProviderToggleTest.php`:
   - `test_any_admin_role_can_toggle_provider_status()`
   - `test_provider_toggle_invalidates_routing_cache()`
   - `test_provider_toggle_creates_audit_log_with_reason()`
4. `AdminCircuitBreakerResetTest.php`:
   - `test_reset_clears_failure_counters_in_circuit_breaker_service()`
   - `test_audit_log_recorded_on_circuit_breaker_reset()`
