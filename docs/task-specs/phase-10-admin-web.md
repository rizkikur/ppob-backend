# Task Spec: Phase 10 — Admin Web (Backoffice Panel)

> **Dokumen Acuan Utama**: `docs/admin-web-architecture.md` (revisi 2026-10-12)  
> **Keputusan Arsitektur**: `docs/architecture-decisions.md` ADR-011  
> **Prioritas Engineering**: Correctness → Security → Maintainability → Testability → Performance → Simplicity  

---

## 1. Ringkasan & Navigasi Task Specs Detail

Perencanaan Phase 10 telah dipecah menjadi **6 dokumen spesifikasi teknis detail** (per-controller, per-method, form validation, dan skenario test case):

| Step | Dokumen Spesifikasi Detail | Deskripsi Modul | Status |
|:----:|:---------------------------|:----------------|:------:|
| **1** | [`phase-10-step1-auth.md`](file:///c:/Users/R/Documents/project%20baru/laravel-project/docs/task-specs/phase-10-step1-auth.md) | **Fondasi & Autentikasi**: Tabel `admins`, guard session, proteksi brute-force, CSP nonce, table append-only `admin_audit_logs`, 2FA self-service. | ✅ Selesai (2026-10-12) |
| **2** | [`phase-10-step2-dashboard.md`](file:///c:/Users/R/Documents/project%20baru/laravel-project/docs/task-specs/phase-10-step2-dashboard.md) | **Realtime Monitoring**: KPI GMV, Success Rate, Queue antrean aksi, Chart 24 jam, Polling Alpine 10s dengan Auto-Pause, Redis Caching. | ✅ Selesai (2026-10-12) |
| **3** | [`phase-10-step3-transactions.md`](file:///c:/Users/R/Documents/project%20baru/laravel-project/docs/task-specs/phase-10-step3-transactions.md) | **Manajemen Transaksi**: Filter & search cepat, Timeline lifecycle, Re-query status provider, Manual Refund via `WalletService::credit()`, Status Override. | ✅ Selesai (2026-10-12) |
| **4** | [`phase-10-step4-products.md`](file:///c:/Users/R/Documents/project%20baru/laravel-project/docs/task-specs/phase-10-step4-products.md) | **Produk, Provider & Circuit Breaker**: Katalog SKU, margin tiering (Regular s/d Platinum), emergency toggle provider, reset circuit breaker, routing priority. | ✅ Selesai (2026-10-12) |
| **5** | [`phase-10-step5-partners.md`](file:///c:/Users/R/Documents/project%20baru/laravel-project/docs/task-specs/phase-10-step5-partners.md) | **Mitra B2B & Keamanan Kredensial**: One-Time Secret Display (Stripe/AWS standard), manajemen IP Whitelist CIDR, retry pengiriman webhook. | ✅ Selesai (2026-10-14) |
| **6** | [`phase-10-step6-finance.md`](file:///c:/Users/R/Documents/project%20baru/laravel-project/docs/task-specs/phase-10-step6-finance.md) | **Keuangan, Rekonsiliasi & Ledger**: Approval top-up manual, Balance Adjustment via `WalletService`, Double-Entry ledger viewer, Rekonsiliasi provider, Streaming CSV export. | ✅ Selesai (2026-10-14) |

---

## 2. Keputusan Arsitektur & Keamanan (Kesepakatan Grill-Me)

1. **Tech Stack UI**:
   - Laravel Blade SSR (bukan SPA/Vue/React).
   - **Tailwind CSS v4** via plugin `@tailwindcss/vite` (`resources/css/app.css` dengan `@import "tailwindcss";`).
   - Alpine.js 3 via CDN (`defer`) untuk interaktivitas komponen modal, dropdown, dan polling.
   - Chart.js via CDN untuk visualisasi chart tren transaksi.
2. **Autentikasi & Session**:
   - Guard `admin` berbasis session web mandiri, terisolasi dari guard `api` (Sanctum) maupun web user.
   - Rate limiting ketat: maksimal 5 attempt gagal per 5 menit per alamat IP.
   - Regenerasi ID session setelah login sukses untuk menangkal Session Fixation attack.
3. **Password Confirmation Window**:
   - Menggunakan middleware `password.confirm:admin.password.confirm` untuk aksi sensitif finansial.
   - Sekali dikonfirmasi, valid selama **3 jam** (`config('auth.password_timeout') = 10800`), sehingga tidak mengganggu flow kerja shift operasional.
4. **Audit Trail Wajib (`admin_audit_logs`)**:
   - Seluruh aksi create, update, delete, approve, reject, dan refund **WAJIB** tercatat ke tabel append-only `admin_audit_logs` (admin_id, action, target, changes, reason, ip, timestamp).
5. **Two-Factor Authentication (2FA / TOTP)**:
   - Mulai dengan opsi self-service di profil pada Phase 10 (schema & fungsionalitas siap). Pengetatan kewajiban role (`super_admin` & `finance`) dapat diaktifkan pada hardening phase.
6. **Wewenang Transaksi & Insiden**:
   - Seluruh role admin (`ops`, `finance`, `super_admin`) diizinkan melakukan re-query provider dan refund transaksi bermasalah selama menyertakan alasan (`reason`) dan tercatat di audit log.
   - Seluruh role admin diizinkan melakukan toggle provider atau reset circuit breaker demi kecepatan mitigasi insiden operasional.
7. **Standar Kredensial Rahasia Mitra (One-Time Display)**:
   - Plaintext API Secret hanya ditampilkan **1 kali** di layar dalam modal saat dibuat atau di-rotate. Di database hanya disimpan dalam bentuk encrypted.
8. **Integritas Finansial (Hard Rule)**:
   - Dilarang keras memodifikasi `wallets.balance` secara langsung di controller.
   - Semua mutasi kredit/debit **WAJIB** melalui `WalletService` dan `TopupService::approve()`.
   - Tanpa batasan limit nominal untuk topup approval dan balance adjustment bagi admin yang memiliki hak akses, asalkan wajib mencantumkan alasan.

---

## 3. Matriks Otorisasi Role (Gates & Policies)

| Modul / Kemampuan | Super Admin | Finance Admin | Ops / CS Admin |
|:------------------|:-----------:|:-------------:|:--------------:|
| Dashboard Ringkasan & Antrean | ✅ Penuh | ✅ Penuh | ✅ Terbatas (Profit di-mask) |
| Pencarian & Detail Transaksi | ✅ | ✅ | ✅ |
| Re-query Transaksi ke Provider | ✅ | ✅ | ✅ |
| Manual Refund (via `WalletService`) | ✅ | ✅ | ✅ (Wajib Reason) |
| Override Status Transaksi | ✅ | ✅ | ✅ (Wajib Reason) |
| Katalog Produk & Margin Tiering | ✅ | ✅ | 👁️ Read-Only |
| Emergency Provider Toggle / Reset Breaker | ✅ | ✅ | ✅ |
| Manajemen Mitra & IP Whitelist | ✅ | 👁️ Read-Only | 👁️ Read-Only |
| Rotate API Key / Secret Mitra | ✅ | ❌ | ❌ |
| Manual Webhook Retry | ✅ | ❌ | ✅ |
| Approval Permohonan Top-Up | ✅ | ✅ | ❌ |
| Penyesuaian Saldo (Balance Adjustment) | ✅ | ✅ | ❌ |
| Ledger Mutasi & Export CSV | ✅ | ✅ | ❌ |
| Rekonsiliasi Saldo Provider | ✅ | ✅ | ❌ |
| Manajemen Akun Admin | ✅ | ❌ | ❌ |

---

## 4. Urutan Eksekusi Pengerjaan (Implementasi)

Pengerjaan kode diimplementasikan secara sekuensial mengikuti tahapan step:

1. **Step 1 (Fondasi & Auth Admin)**: ✅ Selesai (2026-10-12) — Migrations `admins` & `admin_audit_logs`, guard `admin`, Login, 2FA setup, layout Tailwind v4, CSP nonce, 25 test.
2. **Step 2 (Realtime Dashboard & Metrics)**: ✅ Selesai (2026-10-12) — `AdminDashboardService`, KPI GMV/Profit/Volume, Circuit Breaker widget, Chart.js 24h, Alpine 10s auto-pause polling, 9 test.
3. **Step 3 (Manajemen Transaksi & Intervensi)**: ✅ Selesai (2026-10-12) — List multidimensi filter, slide detail, timeline audit trail, requery provider, manual refund via `WalletService::credit()`, status override, masking payload, 16 test.
4. **Step 4 (Katalog Produk & Provider Circuit Breaker)**: ✅ Selesai (2026-10-12) — CRUD produk & kategori, matriks harga tier anti-negative margin, toggle provider status, reset Circuit Breaker, failover routing priority, 15 test.
5. **Step 5 (Mitra B2B & Kredensial API)**: ✅ Selesai (2026-10-14) — Manajemen mitra B2B, One-Time secret display modal, IP whitelist CIDR validator, webhook delivery log & manual retry, 18 test.
6. **Step 6 (Keuangan, Ledger & Rekonsiliasi)**: ✅ Selesai (2026-10-14) — Approval topup manual, Balance adjustment via WalletService, Double-entry ledger viewer, rekonsiliasi upstream provider, streaming CSV export, 16 test.
7. **Audit Log Viewer (Item 10.21)**: ✅ Selesai (2026-10-14) — Halaman audit trail `/admin/audit-logs`, filter admin/aksi/target/tanggal, modal Alpine.js diff JSON payload, 8 test.
