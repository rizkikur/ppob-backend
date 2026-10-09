# Blueprint Arsitektur Web Admin (Backoffice) — PPOB Backend

Dokumen ini mendefinisikan rancangan struktur, antarmuka, keamanan, dan alur kerja untuk **Web Admin PPOB Backend** berbasis **Blade + Alpine.js + Tailwind CSS v3**.

---

## 1. Tujuan & Profil Pengguna (Personas)

Web Admin dirancang untuk 3 peran operasional utama:

| Peran (Role) | Tanggung Jawab Utama | Hak Akses (Permissions) |
|---|---|---|
| **Super Admin** | Manajemen sistem menyeluruh, konfigurasi rute supplier, manajemen akun staf, audit finansial global. | Akses penuh (Full Access) ke semua menu & konfigurasi sistem. |
| **Operations Admin (CS/Ops)** | Pemantauan transaksi real-time, penanganan komplain, pengecekan status supplier, retry transaksi macet, manual refund. | Dashboard, Transaksi, Produk & Rute (view only), Log Provider. |
| **Finance Admin** | Validasi permintaan top-up deposit, penyesuaian saldo deposit mitra B2B, rekonsiliasi kas vs saldo supplier, mutasi ledger. | Dashboard Keuangan, Top-up Approval, Partner Balance, Ledger Mutasi. |

---

## 2. Stack Teknologi & Standar UI/UX

- **Rendering Engine**: **Laravel Blade** (Server-side rendered, aman dengan CSRF token bawaan, layout modular via Blade Components).
- **Client Reactivity**: **Alpine.js 3** (Interaktivitas ringan: modal pop-up, slide-over drawer detail, dropdown menu, live search debounce, tabs switcher, filter dinamis).
- **Styling**: **Tailwind CSS v3** (Tema modern *Dark/Navy Slate* fintech dashboard: Slate `#0f172a`, Zinc, Aksen Indigo `#6366f1` / Emerald `#10b981` / Rose `#ef4444`).
- **Data Visual**: **Chart.js** (Grafik volume transaksi harian & margin profit).
- **Iconography**: **Heroicons** (SVG inline ringan dan konsisten).

---

## 3. Skema Database & Autentikasi Admin

### 3.1. Isolasi Guard Autentikasi (`auth:admin`)
Untuk keamanan maksimal, akun admin diisolasi penuh dari tabel konsumen (`users`):
- Konsumen mobile menggunakan nomor telepon + OTP WhatsApp via Sanctum.
- Admin backoffice menggunakan email + password via **Laravel Session Auth (`guard: admin`)**.

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
    $table->rememberToken();
    $table->timestamps();
});
```

### 3.3. Konfigurasi `config/auth.php`
- Guard baru: `'admin' => ['driver' => 'session', 'provider' => 'admins']`
- Provider baru: `'admins' => ['driver' => 'eloquent', 'model' => App\Domain\Admin\Models\Admin::class]`

---

## 4. Struktur Modul & Layar Web Admin

```
Admin Backoffice (/admin)
├── 0. Auth (/admin/login, /admin/logout)
├── 1. Dashboard (/admin/dashboard)
├── 2. Transaksi (/admin/transactions)
├── 3. Produk & Routing (/admin/products, /admin/routing)
├── 4. Mitra B2B (/admin/partners, /admin/webhooks)
├── 5. Keuangan & Dompet (/admin/wallet/topups, /admin/wallet/ledger)
└── 6. Pengaturan Akun (/admin/users, /admin/staff)
```

---

### Modul 1: Dashboard & Live Operation Monitoring (`/admin/dashboard`)
- **KPI Cards (Statistik 24 Jam Terakhir)**:
  - Total Gross Transaction Value (GTV) dalam Rupiah.
  - Jumlah Transaksi Berhasil vs Gagal vs Pending.
  - Success Rate (%) & Rata-rata Latensi Provider (detik).
  - Net Profit Margin (selisih harga jual vs harga modal supplier).
- **Status Circuit Breaker & Provider Health**:
  - Kartu indikator real-time: Digiflazz (`HEALTHY` / `TRIPPED`), VIP Payment (`HEALTHY` / `TRIPPED`).
  - Sisa deposit saldo backend di masing-masing provider.
- **Grafik Tren Transaksi (Chart.js)**:
  - Grafik garis volume transaksi per-jam selama 24 jam.
- **Live Transaction Feed**:
  - 10 transaksi paling baru dengan polling otomatis (Alpine.js `x-init="setInterval(...)"`).

---

### Modul 2: Manajemen Transaksi & Resolusi Masalah (`/admin/transactions`)
- **Tabel Transaksi dengan Filter Multidimensi**:
  - Filter rentang tanggal, status (`pending`, `processing`, `success`, `failed`, `refunded`), tipe produk, channel (`mobile` vs `partner`), SKU, nomor handphone tujuan.
- **Detail Transaksi (Slide-over Drawer / Modal)**:
  - Nomor referensi sistem, referensi mitra, ID provider luar.
  - Rincian harga modal supplier, harga jual konsumen, admin fee.
  - Log audit lengkap (timestamp pembuatan, debit dompet, pengiriman provider, respon provider, webhook delivery).
- **Aksi Operasional Khusus (Ops Action)**:
  - 🔄 **Cek Status Manual**: Memanggil ulang API status provider untuk transaksi berstatus `pending/processing`.
  - 🔁 **Retry ke Supplier Cadangan**: Memaksa pengiriman ulang transaksi yang gagal ke supplier rute urutan berikutnya.
  - 💸 **Manual Refund**: Mengembalikan saldo dompet konsumen/mitra secara atomik dengan catatan audit alasan refund.

---

### Modul 3: Katalog Produk & Multi-Supplier Routing (`/admin/products` & `/admin/routing`)
- **Katalog Produk & Matriks Harga per-Tier**:
  - Daftar SKU, nama produk, kategori, dan provider default.
  - Konfigurasi harga modal (cents) vs harga tier: End User, Silver, Gold, Platinum.
  - Toggle cepat: Aktifkan / Nonaktifkan produk.
- **Konfigurasi Rute Failover (ADR-004 & ADR-005)**:
  - Tabel prioritas rute supplier per SKU (Priority 1: Digiflazz, Priority 2: VIP Payment).
  - Pengaturan Circuit Breaker threshold (maksimal kegagalan beruntun sebelum sirkuit dibuka).
  - Saklar global `default_user_allow_failover`.

---

### Modul 4: Manajemen Mitra Bisnis B2B (`/admin/partners`)
- **Daftar Mitra**:
  - Nama perusahaan, email PIC, sisa saldo deposit dompet mitra, status aktif, limit RPM.
- **Detail & Keamanan Kunci API Mitra (ADR-006)**:
  - Tombol **Generate New API Key / API Secret** (dengan modal konfirmasi).
  - Manajemen **IP Whitelist** (tambah/hapus IP statis mitra).
  - Konfigurasi **Webhook Callback**: URL callback, secret token, dan mode respon (`sync` vs `async`).
- **Penyesuaian Saldo Deposit Mitra**:
  - Form top-up deposit mitra (kredit) atau penyesuaian koreksi (debit) dengan catatan mutasi wajib.
- **Audit Webhook Delivery (ADR-007 & ADR-008)**:
  - Tabel histori pengiriman callback ke URL mitra.
  - Jumlah percobaan (Attempt 1 s/d 5), status (`delivered`, `pending`, `failed_permanent`), respon HTTP (200, 500, timeout).
  - Tombol **Re-dispatch Webhook Manual** jika mitra telah memperbaiki server mereka.

---

### Modul 5: Keuangan & Verifikasi Top-Up (`/admin/wallet`)
- **Persetujuan Top-Up Transfer Bank Manual**:
  - Menampilkan daftar permintaan top-up yang menunggu konfirmasi (`pending`).
  - Menampilkan nomor rekening pengirim, bank tujuan, nominal unik, dan bukti transfer.
  - Aksi: Tombol **Approve** (otomatis menambah saldo dompet konsumen secara atomik dalam database transaction) atau **Reject** (dengan alasan penolakan).
- **Audit Buku Besar (Ledger Audit & Reconciliation)**:
  - Pemeriksaan integritas saldo: Total saldo semua user + mitra vs kas riil.
  - Pencarian riwayat mutasi dompet (`wallet_mutations`).

---

### Modul 6: Manajemen Pengguna & Staf Admin (`/admin/users`)
- **Manajemen Konsumen Mobile**:
  - Daftar konsumen terdaftar, status verifikasi OTP, tier akun.
  - Reset status lockout PIN (jika user terkunci akibat salah PIN 5 kali).
- **Manajemen Staf Backoffice**:
  - Tambah staf admin baru (Super Admin, Ops, Finance).
  - Nonaktifkan akun staf yang sudah tidak bertugas.

---

## 5. Struktur Folder Kode (File Blueprint)

```
laravel-project/
├── app/
│   └── Domain/
│       └── Admin/
│           ├── Models/
│           │   └── Admin.php
│           ├── Http/
│           │   ├── Controllers/
│           │   │   ├── AuthController.php          # Login, logout, session
│           │   │   ├── DashboardController.php     # Metrik KPI, chart, circuit breaker
│           │   │   ├── TransactionController.php   # List, detail, manual retry, refund
│           │   │   ├── ProductController.php       # Harga tier, status produk
│           │   │   ├── RoutingController.php       # Failover route & circuit breaker rules
│           │   │   ├── PartnerController.php       # Manajemen mitra, API key, deposit
│           │   │   ├── WebhookAuditController.php  # Monitoring delivery & retry
│           │   │   ├── TopupApprovalController.php # Approval transfer bank manual
│           │   │   └── UserController.php          # Manajemen konsumen & reset PIN
│           │   ├── Middleware/
│           │   │   ├── AdminAuthenticate.php       # Cek session auth admin
│           │   │   └── AdminRoleMiddleware.php     # Cek super_admin / finance / ops
│           │   └── Requests/
│           │       ├── AdminLoginRequest.php
│           │       └── ManualRefundRequest.php
│           └── Services/
│               ├── AdminDashboardService.php       # Komputasi agregasi KPI finansial
│               └── AdminOperationService.php       # Logika manual refund & retry rute
├── database/
│   ├── migrations/
│   │   └── 2026_10_10_000001_create_admins_table.php
│   └── seeders/
│       └── AdminSeeder.php                         # Akun default superadmin
├── resources/
│   └── views/
│       └── admin/
│           ├── layouts/
│           │   ├── app.blade.php                   # Master layout (Sidebar + Header + Shell)
│           │   └── guest.blade.php                 # Layout untuk halaman login
│           ├── components/
│           │   ├── stat-card.blade.php             # Komponen KPI card
│           │   ├── badge-status.blade.php          # Badge pending/success/failed
│           │   ├── modal.blade.php                 # Modal Alpine.js reusable
│           │   ├── drawer.blade.php                # Slide-over panel
│           │   └── toast.blade.php                 # Notifikasi aksi sukses/gagal
│           ├── auth/
│           │   └── login.blade.php                 # Halaman login admin bernuansa modern
│           ├── dashboard/
│           │   └── index.blade.php                 # Dashboard analitik & status provider
│           ├── transactions/
│           │   ├── index.blade.php                 # Tabel transaksi + filter
│           │   └── show-modal.blade.php            # Modal detail & aksi retry/refund
│           ├── products/
│           │   └── index.blade.php                 # Manajemen produk & harga
│           ├── routing/
│           │   └── index.blade.php                 # Rute multi-supplier & circuit breaker
│           ├── partners/
│           │   ├── index.blade.php                 # List mitra & deposit
│           │   ├── show.blade.php                  # Detail mitra, API keys, IP whitelist
│           │   └── webhooks.blade.php              # Log webhook delivery & retry
│           └── wallet/
│               ├── topups.blade.php                # Approval topup manual transfer
│               └── ledger.blade.php                # Audit mutasi saldo
└── routes/
    └── admin.php                                   # Rute grup prefix /admin dengan auth guard
```

---

## 6. Rencana Tahapan Eksekusi (Implementation Steps)

1. **Step 1 — Fondasi & Autentikasi Admin**:
   - Migration `create_admins_table` + seeder admin default (`superadmin@ppob.test`).
   - Setup guard `admin` di `config/auth.php` & model `Admin`.
   - Setup layout master Blade (`admin.layouts.app`) dengan Tailwind v3 + Alpine.js.
   - Halaman Login Admin (`/admin/login`) dan tes autentikasi.

2. **Step 2 — Executive Dashboard & Provider Health**:
   - Controller `DashboardController`: Query agregasi GTV, transaksi harian, status Circuit Breaker Digiflazz/VIP Payment.
   - Blade view dashboard dengan KPI cards, chart visual, dan live status widget.

3. **Step 3 — Operasional Transaksi (Monitoring, Retry, Refund)**:
   - Data table transaksi dengan pagination & filter status/tanggal.
   - Slide-over detail transaksi (data payload, provider ref).
   - Fitur aksi: Re-check status provider & Manual Refund aman dengan DB transaction.

4. **Step 4 — Manajemen Produk & Multi-Supplier Routing**:
   - Modul harga jual per-tier konsumen vs modal supplier.
   - Modul konfigurasi failover rute supplier & toggle status provider.

5. **Step 5 — Manajemen Mitra B2B & Webhook Audit**:
   - Manajemen API Key/Secret mitra, IP Whitelist, dan top-up deposit mitra.
   - Layar monitoring delivery webhook & retry job.

6. **Step 6 — Approval Top-up Manual & Audit Keuangan**:
   - Layar konfirmasi persetujuan top-up transfer bank.
   - Audit buku besar mutasi saldo append-only.
