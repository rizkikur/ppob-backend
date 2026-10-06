# Task Spec: Phase 5 — Inquiry Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (Domain: Inquiry), `docs/openapi.yaml` (endpoint /inquiry), `docs/security-design.md` (section 11), `docs/dev-roadmap.md` (Phase 5).
>
> **Selesai = kode + test lulus + update changelog & dev-roadmap.md + push branch**

---

## Konteks

Phase 5 mengimplementasikan alur inquiry tagihan sebelum pembayaran untuk produk pascabayar (postpaid, e.g. PLN Pasca, PDAM):
1. **Aturan Keras**: Inquiry **HANYA** untuk produk `product_type = 'postpaid'`. Jika dipanggil untuk produk prepaid, wajib ditolak dengan HTTP 422 `PRODUCT_TYPE_MISMATCH`.
2. Hasil inquiry berlaku selama 10 menit (`expires_at = now()->addMinutes(10)`). Transaksi pembayaran nantinya harus mencocokkan `amount` dengan nominal hasil inquiry yang masih valid.
3. Driver provider PPOB (`PlnDriver`, `PdamDriver`) mengimplementasikan method `inquiry()` pada `PpobProviderInterface`.
4. Endpoint: `POST /inquiry` dengan validasi FormRequest `sku_code` & `customer_number`.

---

## File yang Dibuat & Dikelola

```
app/Domain/Inquiry/
├── Models/
│   └── Inquiry.php
├── Services/
│   └── InquiryService.php
├── Http/
│   ├── Controllers/
│   │   └── InquiryController.php
│   ├── Requests/
│   │   └── InquiryRequest.php
│   └── Resources/
│       └── InquiryResource.php
app/Domain/Ppob/
├── Contracts/
│   └── PpobProviderInterface.php
├── Drivers/
│   ├── PlnDriver.php
│   └── PdamDriver.php
└── Services/
    └── PpobService.php
tests/Feature/Inquiry/
├── InquiryValidationTest.php
├── InquiryFlowTest.php
└── InquiryExpiryTest.php
```

---

## Rincian Item Pekerjaan

### Item 5.1 — Model: `Inquiry`
- Tabel: `inquiries`
- Field: `id`, `user_id`, `product_id`, `customer_number`, `amount_cents`, `admin_fee_cents`, `inquiry_ref`, `provider_response`, `status`, `expires_at`, `created_at`.
- Relasi:
  - `user(): BelongsTo` ke `User`
  - `product(): BelongsTo` ke `Product`
- Casting: `amount_cents` => `MoneyCast::class`, `admin_fee_cents` => `MoneyCast::class`, `provider_response` => `'array'`, `expires_at` => `'datetime'`.
- Helpers:
  - `isExpired(): bool`
  - `isUsable(): bool`
  - `totalAmount(): Money`

### Item 5.2 — Interface: `PpobProviderInterface`
- Lokasi: `App\Domain\Ppob\Contracts\PpobProviderInterface`
- Method:
  - `inquiry(string $customerNumber, string $productCode): array`
  - `pay(string $customerNumber, string $productCode, Money $amount, string $transactionRef): array`
  - `checkStatus(string $providerRef): array`
  - `verifyWebhookSignature(string $rawBody, string $signature): bool`

### Item 5.3 — Service: `InquiryService`
- Lokasi: `App\Domain\Inquiry\Services\InquiryService`
- Validasi:
  - Tolak jika produk prepaid: throw `BusinessException::productTypeMismatch('postpaid', 'prepaid')` (HTTP 422 `PRODUCT_TYPE_MISMATCH`).
  - Tolak jika produk tidak aktif: throw `BusinessException::productInactive()` (HTTP 422 `PRODUCT_INACTIVE`).
- Memanggil `PpobService::inquiry($product, $customerNumber)` yang me-route ke driver provider.
- Menyimpan record `Inquiry` baru dengan `expires_at = now()->addMinutes(10)` dan status `success`.
- Mengembalikan model `Inquiry`.

### Item 5.4 & 5.5 — Driver: `PlnDriver` & `PdamDriver`
- `PlnDriver::inquiry($customerNumber, $productCode)`:
  - Menghitung/mengambil data tagihan PLN (stand meter, periode, daya, nominal tagihan).
- `PdamDriver::inquiry($customerNumber, $productCode)`:
  - Mengambil data tagihan PDAM (periode, kubikasi meter, nominal tagihan).
- Mendukung mock/fallback realistis untuk keperluan pengujian dan otomasi.

### Item 5.6 — Controller + Request + Resource
- `InquiryRequest`: Validasi `sku_code` (required string) dan `customer_number` (required alphanumeric).
- `InquiryResource`: Mengembalikan `id`, `product` (`ProductResource`), `customer_number`, `amount`, `amount_display`, `admin_fee`, `admin_fee_display`, `status`, `expires_at`, `created_at`.
- `InquiryController`: `POST /inquiry`, resolve produk aktif berdasarkan `sku_code`, jalankan inquiry via `InquiryService`, return `ApiResponse::success(new InquiryResource($inquiry))` (HTTP 200).

### Item 5.7 & 5.8 — Feature Tests
1. **InquiryValidationTest**:
   - Inquiry ditolak untuk produk prepaid (HTTP 422 `PRODUCT_TYPE_MISMATCH`).
   - Inquiry ditolak untuk SKU yang tidak ditemukan (HTTP 404 `PRODUCT_NOT_FOUND`).
   - Inquiry ditolak untuk produk tidak aktif (HTTP 422 `PRODUCT_INACTIVE`).
   - Validasi input `customer_number` dan `sku_code`.
2. **InquiryFlowTest**:
   - Alur inquiry berhasil untuk PLN postpaid.
   - Alur inquiry berhasil untuk PDAM postpaid.
   - Verifikasi respon dan data tersimpan di tabel `inquiries`.
3. **InquiryExpiryTest**:
   - Masa kedaluwarsa 10 menit: `isExpired()` bernilai `false` saat baru dibuat, dan menjadi `true` setelah 11 menit (via time travel).
   - Helper `isUsable()` bernilai false jika sudah kedaluwarsa.

---

## Definition of Done Phase 5
- [x] Model `Inquiry` lengkap dengan relasi `user()`, `product()`, casts, dan helper methods
- [x] `PpobProviderInterface` lengkap dengan method inquiry, pay, checkStatus, verifyWebhookSignature
- [x] `InquiryService` menolak SKU prepaid (`PRODUCT_TYPE_MISMATCH`) dan produk inaktif
- [x] `PlnDriver` dan `PdamDriver` mengimplementasikan inquiry tagihan
- [x] `InquiryController`, `InquiryRequest`, dan `InquiryResource` bekerja dengan envelope `ApiResponse`
- [x] Semua feature test (`InquiryValidationTest`, `InquiryFlowTest`, `InquiryExpiryTest`) lulus 100%
- [x] Seluruh kode UTF-8 tanpa BOM dan diformat dengan Laravel Pint
- [x] `CHANGELOG.md` dan `docs/dev-roadmap.md` diperbarui
- [x] Branch `feature/phase-5-inquiry` di-commit dan di-push ke GitHub
