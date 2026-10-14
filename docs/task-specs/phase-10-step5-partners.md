# Task Spec: Phase 10 — Step 5: Manajemen Mitra B2B & Keamanan Kredensial API

> **Dokumen Acuan**:
> - `docs/admin-web-architecture.md` (Spesifikasi Mitra B2B & Kredensial)
> - `docs/task-specs/phase-8-partner.md` (Domain Mitra B2B, API Key, HMAC & Webhook)
> - `docs/security-design.md` (Standar One-Time Secret & IP Whitelisting)
> - `docs/task-specs/phase-10-step1-auth.md` (Audit Trail & Password Confirmation)
>
> **Selesai = Controller + Service + Blade UI + Modal Secret Display + Tests Pass**

---

## 1. Konteks & Tujuan

Modul Mitra mengelola klien B2B (Host-to-Host) yang menggunakan API transaksi:
1. **Pendaftaran & Profil Mitra**: Data perusahaan/mitra, status aktif, tier harga mitra, webhook endpoint URL.
2. **Keamanan Kredensial API (One-Time Secret Display)**:
   - Sesuai kesepakatan standar industri (Stripe/AWS pattern), saat API Secret dibuat atau di-rotate, **plaintext secret HANYA ditampilkan SATU KALI** di dalam modal dengan tombol "Copy to Clipboard".
   - Nilai secret disimpan secara terenkripsi (`encrypted` cast) atau hashed di database.
   - Setelah modal ditutup, secret **TIDAK BISA DILIHAT LAGI** oleh siapapun (termasuk admin). Jika hilang, wajib di-generate ulang (rotate).
3. **Manajemen IP Whitelist**: Menambah/menghapus alamat IP (format IPv4, IPv6, atau CIDR seperti `103.10.20.0/24`) yang diizinkan memanggil API B2B.
4. **Monitoring & Retry Webhook**: Melihat riwayat pengiriman notifikasi webhook ke server mitra, status HTTP response, payload, dan tombol manual re-dispatch via `DeliverWebhookJob`.
5. **Audit & Perlindungan**: Regenerasi API Key dan penambahan IP Whitelist dilindungi **konfirmasi password** (`password.confirm`) dan dicatat lengkap di `admin_audit_logs`.

---

## 2. Struktur File Yang Dibuat

```
app/
└── Domain/
    └── Admin/
        ├── Http/
        │   ├── Controllers/
        │   │   └── Partner/
        │   │       ├── AdminPartnerController.php
        │   │       ├── AdminPartnerSecurityController.php
        │   │       └── AdminPartnerWebhookController.php
        │   └── Requests/
        │       ├── StorePartnerRequest.php
        │       ├── UpdatePartnerRequest.php
        │       ├── AddIpWhitelistRequest.php
        │       └── RegenerateApiKeyRequest.php
        └── Services/
            └── AdminPartnerManagementService.php
resources/
└── views/
    └── admin/
        └── partners/
            ├── index.blade.php
            ├── create.blade.php
            ├── show.blade.php
            ├── edit.blade.php
            ├── components/
            │   ├── _ip_whitelist_manager.blade.php
            │   ├── _api_credential_card.blade.php
            │   └── _webhook_delivery_logs.blade.php
            └── modals/
                ├── _secret_revealed_modal.blade.php
                └── _confirm_rotate_key_modal.blade.php
routes/
└── admin.php (Group partners)
tests/Feature/Admin/
└── Partners/
    ├── AdminPartnerCrudTest.php
    ├── AdminApiKeyRotationTest.php
    ├── AdminIpWhitelistTest.php
    └── AdminWebhookRetryTest.php
```

---

## 3. Keamanan Kredensial API (One-Time Display Pattern)

### 3.1 Alur Regenerasi API Secret
1. Admin menekan tombol "Rotate API Secret" pada halaman detail mitra.
2. Form meminta konfirmasi password admin (`password.confirm`).
3. Controller memanggil `AdminPartnerManagementService::rotateApiSecret($partner, $admin)`:
   - Generate string acak berkekuatan tinggi: `bin2hex(random_bytes(32))` (64 hex characters).
   - Simpan versi terenkripsi ke kolom `partners.api_secret`.
   - Simpan audit log: `action = 'partner.api_secret_rotated'`, target `$partner`.
   - **Flash** string plaintext secret ke Laravel Session (`session()->flash('newly_generated_secret', $plainSecret)`).
4. Redirect kembali ke halaman detail mitra.
5. Blade layout mendeteksi `session('newly_generated_secret')` dan otomatis membuka modal Alpine.js:
   - Tampilkan teks warning tegas: *"Simpan secret ini sekarang. Anda tidak akan bisa melihatnya lagi setelah menutup jendela ini."*
   - Kotak kode dengan tombol "Copy".
   - Setelah tombol "Saya Sudah Menyimpan Secret" ditekan, modal tertutup dan session flash otomatis hilang.

---

## 4. Service: `AdminPartnerManagementService`

Class `App\Domain\Admin\Services\AdminPartnerManagementService`:

### 4.1 Method: `createPartner(array $data, Admin $admin): array`
- Membuat data mitra di tabel `partners`.
- Generate `api_key` publik unik (prefix `pk_live_...`).
- Generate `api_secret` (prefix `sk_live_...`).
- Simpan `api_secret` dalam bentuk encrypted.
- Catat audit trail `partner.created`.
- Kembalikan array berisi model `$partner` dan `$plainSecret`.

### 4.2 Method: `addIpWhitelist(Partner $partner, string $ipAddress, Admin $admin): void`
- Validasi format IP / CIDR via filter validator (`ip`, `ipv4`, `ipv6`).
- Tambahkan ke tabel `partner_ip_whitelists` atau kolom jsonb `ip_whitelist`.
- Catat audit trail `partner.ip_whitelist_added`.
- Invalidate cache otorisasi partner di Redis (`Cache::forget("partner:ip:{$partner->id}")`).

### 4.3 Method: `retryWebhookDelivery(WebhookDelivery $delivery, Admin $admin): void`
- Mengambil payload asli transaksi webhook.
- Dispatch job `DeliverWebhookJob::dispatch($delivery->partner, $delivery->payload, isManualRetry: true)`.
- Catat audit trail `partner.webhook_retry_dispatched`.

---

## 5. Controllers & Endpoints

### 5.1 `AdminPartnerController`
- `index()`: Tabel mitra, saldo, status aktif, total transaksi mitra.
- `create()` & `store()`: Registrasi mitra baru.
- `show(Partner $partner)`: Detail lengkap mitra, kartu kredensial API, tabel IP whitelist, dan riwayat webhook 50 terakhir.
- `update(UpdatePartnerRequest $request, Partner $partner)`: Update nama perusahaan, webhook URL, dan tier harga.

### 5.2 `AdminPartnerSecurityController`
- `rotateApiKey(RegenerateApiKeyRequest $request, Partner $partner)`:
  - Middleware: `password.confirm:admin.password.confirm`
  - Regenerate public API Key.
- `rotateApiSecret(RegenerateApiKeyRequest $request, Partner $partner)`:
  - Middleware: `password.confirm:admin.password.confirm`
  - Regenerate Secret, flash secret ke session.
- `storeIp(AddIpWhitelistRequest $request, Partner $partner)`:
  - Tambah IP ke whitelist.
- `deleteIp(Partner $partner, string $ipId)`:
  - Hapus IP dari whitelist.

### 5.3 `AdminPartnerWebhookController`
- `retry(WebhookDelivery $delivery)`:
  - Manual retry webhook delivery.

---

## 6. Routing (`routes/admin.php`)

```php
Route::middleware(['auth:admin', 'admin.csp'])->group(function () {
    Route::resource('partners', AdminPartnerController::class);

    // Keamanan Mitra (Wajib Konfirmasi Password untuk Regenerasi Kunci)
    Route::middleware(['password.confirm:admin.password.confirm'])->group(function () {
        Route::post('partners/{partner}/rotate-api-key', [AdminPartnerSecurityController::class, 'rotateApiKey'])
            ->name('partners.rotate_key');
        Route::post('partners/{partner}/rotate-api-secret', [AdminPartnerSecurityController::class, 'rotateApiSecret'])
            ->name('partners.rotate_secret');
    });

    // IP Whitelist
    Route::post('partners/{partner}/ip-whitelist', [AdminPartnerSecurityController::class, 'storeIp'])
        ->name('partners.ip.store');
    Route::delete('partners/{partner}/ip-whitelist/{ip}', [AdminPartnerSecurityController::class, 'deleteIp'])
        ->name('partners.ip.destroy');

    // Webhook Retry
    Route::post('partners/webhooks/{delivery}/retry', [AdminPartnerWebhookController::class, 'retry'])
        ->name('partners.webhooks.retry');
});
```

---

## 7. Feature Tests (Coverage Wajib)

File di `tests/Feature/Admin/Partners/`:
1. `AdminPartnerCrudTest.php`:
   - `test_admin_can_create_new_partner_and_receives_one_time_secret()`
   - `test_plain_secret_is_flashed_to_session_only_once()`
   - `test_partner_secret_is_encrypted_in_database()`
2. `AdminApiKeyRotationTest.php`:
   - `test_rotating_secret_requires_admin_password_confirmation()`
   - `test_rotation_generates_new_secret_and_logs_audit_trail()`
   - `test_old_secret_is_invalidated_immediately()`
3. `AdminIpWhitelistTest.php`:
   - `test_admin_can_add_valid_ipv4_and_cidr_to_partner()`
   - `test_invalid_ip_format_is_rejected()`
   - `test_deleting_ip_invalidates_partner_ip_cache()`
4. `AdminWebhookRetryTest.php`:
   - `test_admin_can_retry_failed_webhook_delivery()`
   - `test_webhook_retry_dispatches_deliver_webhook_job()`
