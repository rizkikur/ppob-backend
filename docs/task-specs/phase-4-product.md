# Task Spec: Phase 4 — Product Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (Domain: Product), `docs/openapi.yaml` (endpoint /products, /products/categories), `docs/dev-roadmap.md` (Phase 4).
>
> **Selesai = kode + test lulus + update dev-roadmap.md**

---

## Konteks

Phase 4 mengimplementasikan domain katalog produk PPOB:
1. Pengguna dapat melihat daftar kategori produk (`GET /products/categories`).
2. Pengguna dapat melihat daftar produk aktif (`GET /products`) dengan filter kategori (`category={code}`) dan operator/provider (`provider={code}`).
3. **Tier Pricing**: Harga jual produk (`sell_price` dan `sell_price_display`) disesuaikan secara dinamis berdasarkan tier pengguna yang sedang terautentikasi (`user->user_tier_id`).
   - Jika terdapat harga di tabel `product_tier_prices` untuk `(product_id, user_tier_id)`, gunakan harga tersebut.
   - Jika tidak ada, fallback ke `base_price + admin_fee`.
4. Model dan relasi: `Product`, `ProductCategory`, `Provider`, `ProductTierPrice`.
5. Seeder awal untuk kategori (Pulsa, Paket Data, Token Listrik, Tagihan Listrik, PDAM) dan provider (Telkomsel, Indosat, XL Axiata, PLN, PDAM).

---

## File yang Dibuat & Dikelola

```
app/Domain/Product/
├── Models/
│   ├── Product.php
│   ├── ProductCategory.php
│   ├── Provider.php
│   └── ProductTierPrice.php
├── Services/
│   └── ProductPricingService.php
├── Http/
│   ├── Controllers/
│   │   └── ProductController.php
│   └── Resources/
│       ├── CategoryResource.php
│       └── ProductResource.php
database/seeders/
├── ProductCategorySeeder.php
└── ProviderSeeder.php
tests/Feature/Product/
├── ProductCatalogTest.php
└── TierPricingTest.php
```

---

## Rincian Item Pekerjaan

### Item 4.1 — Models: `Product`, `ProductCategory`, `Provider`, `ProductTierPrice`
- **ProductCategory**:
  - Kolom: `name`, `code`, `icon_url`, `sort_order`, `is_active`.
  - Scope: `scopeActive($query)`.
  - Relasi: `products(): HasMany`.
- **Provider**:
  - Kolom: `name`, `code`, `driver`, `config`, `queue_name`, `max_workers`, `rate_limit_per_minute`, `timeout_seconds`, `priority`, `supports_balance_api`, `balance_alert_threshold_cents`, `is_active`.
  - Scope: `scopeActive($query)`.
  - Relasi: `products(): HasMany`.
- **Product**:
  - Kolom: `provider_id`, `category_id`, `sku_code`, `name`, `description`, `product_type`, `base_price_cents`, `admin_fee_cents`, `is_active`.
  - Casts: `base_price_cents` => `MoneyCast::class`, `admin_fee_cents` => `MoneyCast::class`.
  - Helper: `isPrepaid(): bool`, `isPostpaid(): bool`.
  - Scope: `scopeActive($query)`.
  - Relasi: `provider(): BelongsTo`, `category(): BelongsTo`, `tierPrices(): HasMany`.
- **ProductTierPrice**:
  - Kolom: `product_id`, `user_tier_id`, `sell_price_cents`.
  - Casts: `sell_price_cents` => `MoneyCast::class`.
  - Relasi: `product(): BelongsTo`, `userTier(): BelongsTo`.

### Item 4.2 — Service: `ProductPricingService`
- Menghitung harga jual produk untuk user tertentu berdasarkan tier-nya:
  ```php
  public function getPriceForUser(Product $product, User $user): Money
  ```
  - Mencari record di `ProductTierPrice` berdasarkan `product_id` dan `user->user_tier_id`.
  - Jika ada, kembalikan `sell_price_cents` (Money).
  - Jika tidak ada, hitung fallback: `$product->base_price_cents->add($product->admin_fee_cents)`.

### Item 4.3 — Controller & Resources
- **CategoryResource**:
  - Field: `id`, `name`, `code`, `icon_url`.
- **ProductResource**:
  - Field: `id`, `sku_code`, `name`, `description`, `product_type`, `sell_price` (cents integer), `sell_price_display` (string format Rupiah), `provider` (nama provider), `category` (`CategoryResource`), `is_active`.
- **ProductController**:
  - `categories()`: Mengambil daftar kategori aktif diurutkan berdasarkan `sort_order`. Return `CategoryResource` array via `ApiResponse::success()`.
  - `index(Request $request)`: Mengambil produk aktif dengan filter opsional:
    - `category`: kode kategori
    - `provider`: kode provider
    - `product_type`: `prepaid` atau `postpaid`
    - Menghitung harga per produk via `ProductPricingService::getPriceForUser()`.
    - Return `ProductResource` collection via `ApiResponse::success()`.

### Item 4.4 — Seeders
- `ProductCategorySeeder`: Menambahkan kategori: Pulsa, Paket Data, Token Listrik, Tagihan Listrik, PDAM.
- `ProviderSeeder`: Menambahkan provider: Telkomsel, Indosat, XL Axiata, PLN, PDAM dengan atribut `queue_name` Horizon yang sesuai.

### Item 4.5 — Feature Tests
- `ProductCatalogTest`:
  - List kategori mengembalikan data dan format yang sesuai.
  - List produk mengembalikan produk dengan status aktif saja.
  - Filter by category (`?category=pulsa`).
  - Filter by provider (`?provider=telkomsel`).
  - Filter by product_type (`?product_type=prepaid`).
- `TierPricingTest`:
  - User dengan tier berbeda (misal `end_user` vs `agent`) mendapatkan harga berbeda untuk SKU yang sama.
  - Jika tier price tidak ditentukan untuk tier tertentu, harga otomatis fallback ke `base_price + admin_fee`.

---

## Definition of Done Phase 4
- [x] Model `Product`, `ProductCategory`, `Provider`, `ProductTierPrice` lengkap dengan relasi, scopes, dan casting `Money`
- [x] `ProductPricingService` menghitung harga berdasarkan user tier dan fallback dengan benar
- [x] `ProductController`, `CategoryResource`, dan `ProductResource` mengimplementasikan envelope `ApiResponse`
- [x] Endpoint `GET /products/categories` dan `GET /products` berfungsi dan mendukung filtering
- [x] `ProductCategorySeeder` dan `ProviderSeeder` berjalan tanpa error
- [x] Seluruh feature test di `tests/Feature/Product/` lulus 100%
- [x] Tidak ada file PHP ber-BOM dan telah diformat dengan Laravel Pint
- [x] `docs/dev-roadmap.md` diperbarui menandai Phase 4 `✅ Selesai`
