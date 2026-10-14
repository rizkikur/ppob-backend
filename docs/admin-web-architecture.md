# Blueprint Arsitektur Web Admin (Backoffice) — PPOB Backend

Dokumen ini mendefinisikan rancangan struktur, antarmuka, keamanan, dan alur kerja untuk
**Web Admin PPOB Backend** berbasis **Blade + Alpine.js + Tailwind CSS v4**.

> **Terakhir diperbarui:** 2026-10-12 — Revisi setelah gap analysis vs seluruh dokumen project.
> Koreksi utama: Tailwind v4, tabel `admins` terpisah (dijelaskan di §3.1), audit log tabel,
> security berlapis (rate limit, CSP, 2FA schema, session security), cursor pagination, Redis cache KPI.

---

## 1. Tujuan & Profil Pengguna (Personas)

Web Admin dirancang untuk 3 peran operasional utama:

| Peran (Role) | Tanggung Jawab Utama | Hak Akses (Permissions) |
|---|---|---|
| **Super Admin** | Manajemen sistem menyeluruh, konfigurasi rute supplier, manajemen akun staf, audit finansial global. | Akses penuh ke semua menu & konfigurasi sistem. |
| **Operations Admin (CS/Ops)** | Pemantauan transaksi real-time, penanganan komplain, pengecekan status supplier, retry transaksi macet, manual refund. | Dashboard, Transaksi (retry + refund), Produk & Rute (view only), Log Provider. |
| **Finance Admin** | Validasi permintaan top-up deposit, penyesuaian saldo deposit mitra B2B, rekonsiliasi kas vs saldo supplier, mutasi ledger. | Dashboard Keuangan, Top-up Approval, Partner Balance, Ledger Mutasi. |

---

## 2. Stack Teknologi & Standar UI/UX

- **Rendering Engine**: **Laravel Blade** (Server-side rendered, aman dengan CSRF token bawaan, layout modular via Blade Components).
- **Client Reactivity**: **Alpine.js 3** (Interaktivitas ringan: modal pop-up, slide-over drawer detail, dropdown menu, live search debounce, tabs switcher, filter dinamis).
- **Styling**: **Tailwind CSS v4** (sesuai `package.json` aktual — menggunakan `@tailwindcss/vite` plugin dan CSS-first configuration via `@import "tailwindcss"`).
  - Tema: *Dark/Navy Slate* fintech dashboard: `oklch(0.13 0.02 265)` (Slate near-black), aksen Indigo, Emerald, Rose.
  - **Catatan**: Tailwind v4 TIDAK menggunakan `tailwind.config.js` — konfigurasi via CSS `@theme`.
- **Data Visual**: **Chart.js** (via CDN, bukan npm — lebih sederhana untuk SSR Blade; grafik volume transaksi harian & margin profit).
- **Iconography**: **Heroicons** (SVG inline ringan dan konsisten).

---

## 3. Skema Database & Autentikasi Admin

### 3.1. Keputusan: Isolasi Guard & Tabel Terpisah

**Admin panel menggunakan tabel `admins` terpisah dan guard `auth:admin`**, berbeda dari
tabel `users` konsumen mobile.

**Alasan (Security-first reasoning)**:
1. **Prinsip Least Privilege**: Admin backoffice tidak boleh bisa login ke endpoint mobile `/api/v1/*`
   dan sebaliknya. Isolasi guard memastikan ini secara teknikal.
2. **Blast radius isolation**: Jika credentials satu guard compromise, yang lain tidak terpengaruh.
3. **Audit trail bersih**: Aksi admin tercatat di model `Admin`, tidak tercampur dengan `User`.
4. **Perbedaan autentikasi fundamental**: Admin menggunakan email + password (session-based),
   konsumen menggunakan nomor HP + OTP WhatsApp (Sanctum token).

> **Catatan vs ADR-006**: ADR-006 mendefinisikan field `role` di tabel `users`
> (`user`/`admin`/`super_admin`). Field tersebut dipertahankan untuk kebutuhan H2H protocol
> (OTOMAX/IRS) di masa depan. Tabel `admins` terpisah ini adalah untuk admin **panel web**,
> bukan untuk API access. Tidak ada konflik — ini dua mekanisme yang berbeda untuk dua
> persona yang berbeda.

### 3.2. Migration: `create_admins_table`

```php
Schema::create('admins', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('email', 150)->unique();
    $table->string('password');
    $table->string('role', 30)->default('ops_admin'); // super_admin, ops_admin, finance_admin
    $table->boolean('is_active')->default(true);
    $table->string('avatar_url')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->string('last_login_ip', 45)->nullable();
    // 2FA (schema ready, implementasi opsional iterasi berikutnya)
    $table->boolean('two_factor_enabled')->default(false);
    $table->text('two_factor_secret')->nullable();       // TOTP secret, encrypted
    // Session security
    $table->rememberToken();
    $table->timestamps();
});
```

### 3.3. Migration: `create_admin_activity_logs_table` (WAJIB — Compliance Keuangan)

Setiap aksi admin yang mengubah data sistem **wajib** dicatat. Tabel ini **append-only** —
tidak ada UPDATE/DELETE, sama seperti `wallet_mutations`.

```php
Schema::create('admin_activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')->constrained('admins');
    $table->string('action', 100);        // 'topup.approve', 'transaction.refund', 'partner.generate_key'
    $table->string('resource_type', 50)->nullable(); // 'TopupRequest', 'Transaction', 'Partner'
    $table->bigInteger('resource_id')->nullable();
    $table->jsonb('payload')->nullable(); // {'before': {...}, 'after': {...}, 'reason': '...'}
    $table->string('ip_address', 45);
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->useCurrent();
    // Tidak ada updated_at — append-only

    $table->index(['admin_id', 'created_at']);
    $table->index(['resource_type', 'resource_id']);
});
```

### 3.4. Konfigurasi `config/auth.php`

```php
'guards' => [
    // ... guard yang sudah ada (web, sanctum)
    'admin' => [
        'driver'   => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    // ... provider yang sudah ada
    'admins' => [
        'driver' => 'eloquent',
        'model'  => App\Domain\Admin\Models\Admin::class,
    ],
],
```

---

## 4. Keamanan Berlapis (Defense in Depth)

### 4.1. Rate Limiting Admin Login

Admin login lebih ketat dari user biasa: **5 attempt dalam 5 menit per IP**.

```php
// Di AdminLoginController::store()
$throttleKey = 'admin-login:' . request()->ip();
if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
    $seconds = RateLimiter::availableIn($throttleKey);
    throw ValidationException::withMessages([
        'email' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
    ]);
}
RateLimiter::hit($throttleKey, 300); // 5 menit decay
```

### 4.2. Session Security

- **Regenerasi session** wajib setelah login (`session()->regenerate()`).
- **Session timeout**: 30 menit inaktivitas (konfigurasi di `config/session.php`).
- **Secure cookie**: `session.secure = true` di production (HTTPS only).
- **HttpOnly cookie**: pastikan session cookie tidak bisa diakses via JavaScript.
- **SameSite=Lax** untuk proteksi CSRF tambahan di samping `VerifyCsrfToken`.

### 4.3. CSRF Protection

Admin web menggunakan Session Auth → `VerifyCsrfToken` middleware **aktif otomatis**.
Pastikan:
- Semua form Blade menggunakan directive `@csrf`.
- Semua request AJAX dari Alpine.js menyertakan `X-CSRF-TOKEN` header:
  ```js
  fetch('/admin/...', {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/json',
      },
      body: JSON.stringify({...})
  });
  ```
- Route admin **tidak** di-exclude dari `VerifyCsrfToken`.

### 4.4. Content Security Policy (CSP)

Tambahkan CSP header via middleware `AdminSecurityHeaders` untuk mencegah XSS:

```php
// Di AdminSecurityHeaders middleware
$nonce = base64_encode(random_bytes(16));
session(['csp_nonce' => $nonce]);

$response->headers->set('Content-Security-Policy',
    "default-src 'self'; " .
    "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; " . // Chart.js CDN
    "style-src 'self' 'unsafe-inline'; " .       // Alpine.js membutuhkan inline style
    "img-src 'self' data:; " .
    "font-src 'self'; " .
    "connect-src 'self'; " .
    "frame-ancestors 'none';"
);
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
```

### 4.5. IP Whitelist Admin (Opsional, Sangat Direkomendasikan)

```php
// AdminIpWhitelist middleware
// config: ADMIN_ALLOWED_IPS=192.168.1.0/24,10.0.0.1
// Jika kosong → semua IP diizinkan (development mode)
```

### 4.6. Konfirmasi Password untuk Aksi Finansial Kritis

Aksi berikut wajib meminta konfirmasi password ulang (middleware `password.confirm`):
- Approve top-up transfer bank
- Manual refund
- Generate ulang API Key mitra
- Nonaktifkan akun admin

```php
Route::middleware(['auth:admin', 'admin.role:super_admin,finance_admin', 'password.confirm'])
    ->group(function () {
        Route::post('/wallet/topups/{id}/approve', [TopupApprovalController::class, 'approve']);
        Route::post('/transactions/{id}/refund', [TransactionController::class, 'refund']);
    });
```

### 4.7. Otorisasi Granular via Laravel Gates

`AdminRoleMiddleware` menangani akses level menu/route. Untuk aksi spesifik, gunakan Gates:

```php
// Di AppServiceProvider atau AdminServiceProvider
Gate::define('admin.transaction.refund', fn(Admin $admin) =>
    in_array($admin->role, ['super_admin', 'ops_admin']));
Gate::define('admin.topup.approve', fn(Admin $admin) =>
    in_array($admin->role, ['super_admin', 'finance_admin']));
Gate::define('admin.partner.generate_key', fn(Admin $admin) =>
    in_array($admin->role, ['super_admin']));
Gate::define('admin.staff.manage', fn(Admin $admin) =>
    $admin->role === 'super_admin');
Gate::define('admin.routing.edit', fn(Admin $admin) =>
    $admin->role === 'super_admin');
Gate::define('admin.product.edit', fn(Admin $admin) =>
    $admin->role === 'super_admin');
```

Di controller: `$this->authorize('admin.topup.approve');`
Di Blade: `@can('admin.topup.approve') ... @endcan`

### 4.8. Output Encoding — Cegah XSS

- **WAJIB**: Gunakan `{{ $var }}` (escaped), **bukan** `{!! $var !!}` untuk data dari database.
- Semua data user-generated (nama, nomor HP, catatan refund, alasan penolakan) harus di-escape.
- Jika data mengandung HTML legitimate (rare), sanitize dulu sebelum ditampilkan.

---

## 5. Struktur Modul & Layar Web Admin

```
Admin Backoffice (/admin)
├── 0. Auth       (/admin/login, /admin/logout)
├── 1. Dashboard  (/admin/dashboard)
├── 2. Transaksi  (/admin/transactions)
├── 3. Produk & Routing (/admin/products, /admin/routing)
├── 4. Mitra B2B  (/admin/partners, /admin/webhooks)
├── 5. Keuangan   (/admin/wallet/topups, /admin/wallet/ledger)
├── 6. Pengguna   (/admin/users, /admin/staff)
└── 7. Audit Log  (/admin/audit-log)  ← BARU
```

---

### Modul 1: Dashboard & Live Operation Monitoring (`/admin/dashboard`)

**KPI Cards (data di-cache Redis TTL 60 detik)**:
- Total Gross Transaction Value (GTV) dalam Rupiah — 24 jam terakhir.
- Jumlah Transaksi Berhasil vs Gagal vs Pending.
- Success Rate (%) & Rata-rata Latensi Provider (detik).
- Net Profit Margin (selisih harga jual vs harga modal supplier).

**Status Circuit Breaker & Provider Health** (baca langsung dari Redis, bukan query DB):
- Kartu indikator: status per supplier (`CLOSED` / `OPEN` / `HALF-OPEN`).
- Sisa deposit saldo backend di masing-masing provider.

**Grafik Tren Transaksi (Chart.js via CDN)**:
- Grafik garis volume transaksi per-jam selama 24 jam.
- Data di-cache Redis TTL 120 detik.

**Live Transaction Feed**:
- 10 transaksi paling baru dengan polling otomatis **interval 10 detik** (bukan 1-2 detik).
- Endpoint `GET /admin/dashboard/feed` di-cache Redis TTL 5 detik.
- Alpine.js: `x-init="setInterval(() => refreshFeed(), 10000)"`

---

### Modul 2: Manajemen Transaksi & Resolusi Masalah (`/admin/transactions`)

**Tabel Transaksi dengan Filter Multidimensi**:
- Filter rentang tanggal, status (`pending`, `processing`, `success`, `failed`, `refunded`),
  tipe produk, channel (`mobile` vs `partner`), SKU, nomor handphone tujuan.
- **Cursor pagination** (bukan offset) untuk performa optimal pada jutaan record.

**Detail Transaksi (Slide-over Drawer)**:
- Nomor referensi sistem, referensi mitra, ID provider luar.
- Rincian harga modal supplier, harga jual konsumen, admin fee.
- Log audit lengkap (timestamp pembuatan, debit dompet, pengiriman provider, respon provider, webhook delivery).

**Aksi Operasional (Ops Action)**:

> ⚠️ **Aturan keras**: Semua aksi yang mengubah saldo **WAJIB** melewati `WalletService`.
> `AdminOperationService` adalah orchestrator, bukan direct DB manipulator.

- 🔄 **Cek Status Manual**: Dispatch `CheckTransactionStatusJob` untuk transaksi `pending/processing`.
- 🔁 **Retry ke Supplier Cadangan**: Via `AdminOperationService` yang memanggil `SupplierRoutingService`.
  Hanya boleh dilakukan jika transaksi `failed` dan `allow_failover = true` untuk partner terkait.
- 💸 **Manual Refund**: Via `AdminOperationService` → `WalletService::credit()` dalam DB transaction.
  - Wajib isi alasan (minimal 10 karakter).
  - Konfirmasi password sebelum eksekusi.
  - Dicatat di `admin_activity_logs` dengan payload `{before_balance, after_balance, reason, transaction_id}`.
  - **Gate**: `admin.transaction.refund` (`super_admin`, `ops_admin`).

---

### Modul 3: Katalog Produk & Multi-Supplier Routing (`/admin/products` & `/admin/routing`)

**Katalog Produk & Matriks Harga per-Tier**:
- Daftar SKU, nama produk, kategori, dan provider default.
- Konfigurasi harga modal (cents) vs harga tier: End User, Silver, Gold, Platinum.
- Toggle cepat: Aktifkan / Nonaktifkan produk.
- **Gate**: Edit dan toggle hanya `super_admin`.

**Konfigurasi Rute Failover (ADR-004 & ADR-005)**:
- Tabel prioritas rute supplier per SKU.
- Pengaturan Circuit Breaker threshold (maksimal kegagalan beruntun sebelum sirkuit dibuka).
- Toggle force OPEN / force CLOSE circuit breaker manual (via update Redis state).
- Saklar global `default_user_allow_failover`.
- **Gate**: Konfigurasi routing hanya `super_admin`.

---

### Modul 4: Manajemen Mitra Bisnis B2B (`/admin/partners`)

**Daftar Mitra**:
- Nama perusahaan, email PIC, sisa saldo deposit dompet mitra, status aktif, limit RPM.

**Detail & Keamanan Kunci API Mitra (ADR-006)**:
- Tombol **Generate New API Key / API Secret** (konfirmasi password wajib).
  - Key lama langsung dinonaktifkan.
  - Dicatat di `admin_activity_logs`.
- Manajemen **IP Whitelist**: tambah/hapus IP statis mitra.
- Konfigurasi **Webhook Callback**: URL callback, secret token, mode respon (`sync` vs `async`).
- **Gate**: Generate key hanya `super_admin`.

**Penyesuaian Saldo Deposit Mitra**:
- Form top-up deposit mitra (kredit) atau koreksi (debit).
- **WAJIB** melewati `WalletService::credit/debit()` — bukan direct DB update.
- Catatan mutasi wajib diisi.
- Dicatat di `admin_activity_logs`.
- **Gate**: `admin.topup.approve` (`super_admin`, `finance_admin`).

**Audit Webhook Delivery (ADR-007 & ADR-008)**:
- Histori pengiriman callback ke URL mitra.
- Jumlah percobaan (Attempt 1 s/d 5), status (`delivered`, `pending`, `failed_permanent`).
- Tombol **Re-dispatch Webhook Manual** jika mitra telah memperbaiki server mereka.

---

### Modul 5: Keuangan & Verifikasi Top-Up (`/admin/wallet`)

**Persetujuan Top-Up Transfer Bank Manual**:
- Daftar permintaan top-up `pending` dengan nomor rekening pengirim, bank tujuan, nominal, bukti transfer.
- Aksi: **Approve** (via `TopupService::approve()` yang memanggil `WalletService::credit()` atomik)
  atau **Reject** (dengan alasan penolakan wajib diisi).
- Konfirmasi password sebelum Approve.
- Dicatat di `admin_activity_logs`.
- **Gate**: `admin.topup.approve` (`super_admin`, `finance_admin`).

**Audit Buku Besar (Ledger Audit & Reconciliation)**:
- Pemeriksaan integritas saldo: Total saldo semua user + mitra vs kas riil.
- Pencarian riwayat mutasi dompet (`wallet_mutations`) dengan filter user/mitra/tanggal.
- Ringkasan rekonsiliasi dari `reconciliation_reports` (ADR-010).

---

### Modul 6: Manajemen Pengguna & Staf Admin (`/admin/users`)

**Manajemen Konsumen Mobile**:
- Daftar konsumen terdaftar, status verifikasi OTP, tier akun.
- Reset status lockout PIN (jika user terkunci akibat salah PIN 5 kali) — dicatat di audit log.

**Manajemen Staf Backoffice**:
- Tambah staf admin baru (Super Admin, Ops, Finance).
- Nonaktifkan akun staf (`is_active = false`) — tidak dihapus, dicatat di audit log.
- **Gate**: `admin.staff.manage` (`super_admin` only).

---

### Modul 7: Audit Log Admin (`/admin/audit-log`) — BARU

- Tabel aktivitas admin: siapa, apa, kapan, dari mana.
- Filter by admin, aksi, resource type, tanggal.
- **Read-only** — tidak ada tombol edit/delete.
- **Gate**: `super_admin` dan `finance_admin` (untuk audit keuangan).

---

## 6. Performa & Skalabilitas

### 6.1. Caching Strategi (Redis)

| Data | Cache Key | TTL |
|------|-----------|-----|
| KPI Dashboard (GTV, success rate, dll) | `admin:dashboard:kpi` | 60 detik |
| Grafik tren transaksi | `admin:dashboard:chart` | 120 detik |
| Live feed 10 transaksi terbaru | `admin:dashboard:feed` | 5 detik |
| Status circuit breaker | `circuit:{code}:state` | Dikelola CircuitBreakerService |
| Supplier balance | `supplier:{id}:balance` | 15 menit |

### 6.2. Query Optimization

- **Cursor pagination** untuk semua tabel besar (transactions, wallet_mutations, partner_logs).
  ```php
  Transaction::with(['product', 'supplier'])
      ->orderBy('id', 'desc')
      ->cursorPaginate(50);
  ```
- **Eager loading** wajib untuk semua relasi yang ditampilkan di list view.
- **Index yang dibutuhkan** (pastikan sudah ada):
  ```sql
  -- Query dashboard KPI
  CREATE INDEX CONCURRENTLY idx_trx_created_status ON transactions (created_at DESC, status);
  -- Query per user
  CREATE INDEX CONCURRENTLY idx_trx_user ON transactions (user_id, created_at DESC);
  -- Topup pending
  CREATE INDEX CONCURRENTLY idx_topup_pending ON topup_requests (status, created_at)
      WHERE status = 'pending';
  -- Webhook retry queue
  CREATE INDEX CONCURRENTLY idx_webhook_retry ON webhook_deliveries (status, next_retry_at)
      WHERE status IN ('pending', 'failed');
  ```

### 6.3. Live Feed Polling — Interval Tepat

Gunakan 10 detik, **bukan** 1-2 detik. Alasan: backoffice bukan trading platform real-time.
Dengan 10 admin aktif dan interval 10 detik = 60 request/menit ke endpoint feed — masuk akal.
Dengan interval 1 detik = 600 request/menit — berlebihan dan bebani database.

---

## 7. Struktur Folder Kode (File Blueprint)

```
laravel-project/
├── app/
│   └── Domain/
│       └── Admin/
│           ├── Models/
│           │   └── Admin.php                         # Model admin + fillable + hidden
│           ├── Http/
│           │   ├── Controllers/
│           │   │   ├── Auth/
│           │   │   │   └── AdminLoginController.php  # Login (rate limit, regenerate), logout
│           │   │   ├── DashboardController.php       # KPI (Redis cache), chart, live feed
│           │   │   ├── TransactionController.php     # List (cursor pag.), detail, retry, refund
│           │   │   ├── ProductController.php         # Harga tier, toggle aktif
│           │   │   ├── RoutingController.php         # Failover routes, circuit breaker config
│           │   │   ├── PartnerController.php         # Mitra, API key, IP whitelist, deposit
│           │   │   ├── WebhookAuditController.php    # Delivery log & manual re-dispatch
│           │   │   ├── TopupApprovalController.php   # Approve (via TopupService), reject
│           │   │   ├── UserController.php            # Konsumen, PIN reset, staf admin
│           │   │   └── AuditLogController.php        # ← BARU: Read-only audit log viewer
│           │   ├── Middleware/
│           │   │   ├── AdminAuthenticate.php         # Redirect ke login jika belum auth
│           │   │   ├── AdminRoleMiddleware.php        # Cek role: super_admin/ops/finance
│           │   │   ├── AdminActivityLog.php          # ← BARU: Auto-log aksi ke admin_activity_logs
│           │   │   └── AdminSecurityHeaders.php      # ← BARU: CSP, HSTS, X-Frame-Options
│           │   └── Requests/
│           │       ├── AdminLoginRequest.php         # Validasi email/password
│           │       ├── ManualRefundRequest.php       # reason wajib, min 10 karakter
│           │       ├── TopupApprovalRequest.php      # Approve/reject dengan alasan
│           │       └── PartnerDepositRequest.php     # amount, catatan wajib
│           ├── Services/
│           │   ├── AdminDashboardService.php         # KPI dengan Redis cache, circuit breaker read
│           │   └── AdminOperationService.php         # Orchestrate refund/retry via WalletService
│           └── Policies/
│               └── AdminGateDefinitions.php          # ← BARU: Definisi semua Gate per aksi
├── database/
│   ├── migrations/
│   │   ├── 2026_10_10_000001_create_admins_table.php
│   │   └── 2026_10_10_000002_create_admin_activity_logs_table.php  ← BARU
│   └── seeders/
│       └── AdminSeeder.php                           # Akun default superadmin@ppob.test
├── resources/
│   └── views/
│       └── admin/
│           ├── layouts/
│           │   ├── app.blade.php                     # Master layout (Sidebar + Header + Shell)
│           │   └── guest.blade.php                   # Layout untuk halaman login
│           ├── components/
│           │   ├── stat-card.blade.php               # KPI card dengan trend indicator
│           │   ├── badge-status.blade.php            # Badge pending/success/failed
│           │   ├── modal.blade.php                   # Modal Alpine.js reusable (dengan nonce CSP)
│           │   ├── drawer.blade.php                  # Slide-over panel
│           │   ├── toast.blade.php                   # Notifikasi sukses/gagal
│           │   └── confirm-action.blade.php          # ← BARU: Modal konfirmasi aksi kritis
│           ├── auth/
│           │   └── login.blade.php                   # Login admin (rate limit aware, honeypot)
│           ├── dashboard/
│           │   └── index.blade.php                   # Dashboard KPI, chart, live feed (10s poll)
│           ├── transactions/
│           │   ├── index.blade.php                   # Tabel + filter + cursor pagination
│           │   └── show-modal.blade.php              # Detail + aksi retry/refund
│           ├── products/
│           │   └── index.blade.php                   # Katalog produk & harga tier
│           ├── routing/
│           │   └── index.blade.php                   # Rute multi-supplier & circuit breaker
│           ├── partners/
│           │   ├── index.blade.php                   # List mitra
│           │   ├── show.blade.php                    # Detail: API keys, IP whitelist, deposit
│           │   └── webhooks.blade.php                # Log webhook & re-dispatch
│           ├── wallet/
│           │   ├── topups.blade.php                  # Approval topup manual transfer
│           │   └── ledger.blade.php                  # Audit mutasi saldo
│           ├── users/
│           │   ├── index.blade.php                   # Daftar konsumen mobile
│           │   └── staff.blade.php                   # Manajemen staf admin
│           └── audit-log/
│               └── index.blade.php                   # ← BARU: Read-only audit trail
└── routes/
    └── admin.php                                     # Route grup /admin dengan middleware stack
```

---

## 8. Route File: `routes/admin.php`

```php
<?php

use App\Domain\Admin\Http\Controllers\Auth\AdminLoginController;
use App\Domain\Admin\Http\Controllers\{
    DashboardController, TransactionController, ProductController,
    RoutingController, PartnerController, WebhookAuditController,
    TopupApprovalController, UserController, AuditLogController,
};
use App\Domain\Admin\Http\Middleware\{AdminAuthenticate, AdminRoleMiddleware};
use Illuminate\Support\Facades\Route;

// ─── Guest Routes (Unauthenticated) ───────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware('web')->group(function () {

    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])
        ->middleware('throttle:5,5'); // 5 attempt per 5 menit
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

    // ─── Authenticated Routes ──────────────────────────────────────────────────
    Route::middleware([AdminAuthenticate::class, 'admin.activity-log', 'admin.security-headers'])
        ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/feed', [DashboardController::class, 'feed'])->name('dashboard.feed');

        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');

        // Aksi kritis — butuh konfirmasi password & cek role
        Route::middleware('password.confirm')->group(function () {
            Route::post('/transactions/{id}/refund', [TransactionController::class, 'refund'])
                ->middleware('admin.role:super_admin,ops_admin')
                ->name('transactions.refund');
            Route::post('/wallet/topups/{id}/approve', [TopupApprovalController::class, 'approve'])
                ->middleware('admin.role:super_admin,finance_admin')
                ->name('wallet.topups.approve');
            Route::post('/partners/{id}/regenerate-key', [PartnerController::class, 'regenerateKey'])
                ->middleware('admin.role:super_admin')
                ->name('partners.regenerate-key');
        });

        // ... route lainnya
    });
});
```

---

## 9. Dependency ke Domain Lain (Wajib Diikuti)

`AdminOperationService` dan controller admin **tidak boleh** memanipulasi saldo/transaksi secara langsung.
Wajib memanggil service dari domain yang sudah ada:

| Aksi Admin | Service yang Dipanggil | Domain |
|-----------|----------------------|--------|
| Approve top-up | `TopupService::approve($topupRequest)` | `Wallet` |
| Manual refund | `WalletService::credit($wallet, $amount, $reference)` | `Wallet` |
| Deposit mitra | `WalletService::credit($partnerWallet, $amount, $note)` | `Wallet` |
| Retry transaksi | `SupplierRoutingService::resolve($transaction)` | `Ppob` |
| Cek status provider | `CheckTransactionStatusJob::dispatch($transaction)` | `Transaction` |
| Re-dispatch webhook | `DeliverWebhookJob::dispatch($webhookDelivery)` | `Partner` |

> **Aturan keras**: `AdminOperationService` adalah orchestrator yang memanggil service lain,
> **bukan** implementor. Ini menjaga isolasi domain dan memastikan semua aturan bisnis
> (idempotency, SELECT FOR UPDATE, audit trail) tetap berjalan.

---

## 10. Rencana Tahapan Eksekusi (Implementation Steps)

### Step 1 — Fondasi & Autentikasi Admin *(Prerequisite untuk semua step lain)*
- Migration: `create_admins_table` + `create_admin_activity_logs_table`.
- Guard `admin` di `config/auth.php` + model `Admin`.
- `AdminLoginController`: login dengan **session regeneration** + **rate limit 5/5mnt**.
- Middleware stack: `AdminAuthenticate`, `AdminRoleMiddleware`, `AdminActivityLog`, `AdminSecurityHeaders`.
- Gate definitions di `AdminGateDefinitions`.
- Layout master Blade Tailwind v4 + Alpine.js 3 (sidebar, header, shell).
- Halaman login `/admin/login`.
- Seeder akun `superadmin@ppob.test`.
- **Feature test**: login sukses, rate limit, logout, unauthorized redirect 302, role middleware.

### Step 2 — Executive Dashboard & Provider Health
- `AdminDashboardService`: agregasi KPI + **Redis cache TTL 60s**.
- Endpoint `GET /admin/dashboard/feed` dengan **Redis cache TTL 5s**.
- Dashboard view: KPI cards, Chart.js (CDN), circuit breaker status (baca Redis langsung).
- Live feed Alpine.js polling **10 detik**.
- **Feature test**: dashboard hanya diakses admin auth, KPI cache bekerja, feed ter-update.

### Step 3 — Operasional Transaksi (Monitoring, Retry, Refund)
- Tabel transaksi + filter + **cursor pagination**.
- Slide-over detail + full audit trail.
- Refund via `AdminOperationService → WalletService` + catat `admin_activity_logs`.
- Konfirmasi password sebelum refund.
- **Feature test**: refund menambah saldo, tercatat audit log, unauthorized 403, cursor pagination.

### Step 4 — Produk & Multi-Supplier Routing
- Modul katalog produk + harga tier + toggle aktif.
- Konfigurasi failover route + circuit breaker threshold.
- **Feature test**: toggle nonaktif menolak transaksi baru, gate super_admin enforced.

### Step 5 — Mitra B2B & Webhook Audit
- API Key management (generate + konfirmasi password + audit log).
- IP Whitelist management.
- Deposit mitra via `WalletService`.
- Webhook delivery log + re-dispatch manual.
- **Feature test**: generate key baru membatalkan lama, deposit tercatat di mutations.

### Step 6 — Approval Top-Up & Audit Keuangan
- Approve via `TopupService::approve()` + konfirmasi password.
- Ledger audit: `wallet_mutations` dengan cursor pagination.
- Rekonsiliasi summary dari `reconciliation_reports`.
- Modul Audit Log Admin (read-only).
- **Feature test**: approve menambah saldo atomik, duplikat approve ditolak, ledger konsisten.

---

## 11. Definition of Done (Phase 10)

Sebuah item dianggap **selesai** jika memenuhi **semua** kriteria:

- [ ] Kode diimplementasi di `app/Domain/Admin/` sesuai struktur §7
- [ ] Tidak ada controller/service yang bypass `WalletService` untuk ubah saldo
- [ ] Semua aksi kritis tercatat di `admin_activity_logs`
- [ ] Semua aksi kritis terlindungi oleh Gate yang tepat
- [ ] Rate limiting admin login terimplementasi dan teruji
- [ ] Session regeneration terimplementasi setelah login
- [ ] CSP header terpasang di semua response admin
- [ ] Semua form menggunakan `@csrf`
- [ ] Feature test lulus: auth flow, role guard, refund via WalletService, audit log
- [ ] `./vendor/bin/phpstan analyse` tanpa error
- [ ] `./vendor/bin/pint` dijalankan, 0 style error
- [ ] `CHANGELOG.md` diperbarui dengan Phase 10 entries
- [ ] `docs/dev-roadmap.md` Phase 10 ditambahkan dan status diperbarui
