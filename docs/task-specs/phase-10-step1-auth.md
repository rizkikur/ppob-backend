# Task Spec: Phase 10 — Step 1: Fondasi & Autentikasi Admin

> **Dokumen Acuan**:
> - `docs/admin-web-architecture.md` (Arsitektur Admin Panel)
> - `docs/architecture-decisions.md` ADR-011 (Auth Guard terpisah & Tabel `admins`)
> - `docs/security-design.md` (Session fixation, CSP, Brute Force protection)
>
> **Selesai = Kode + Migration + Blade Views + Tests 100% Pass**

---

## 1. Konteks & Tujuan

Membangun fondasi server-side rendering (Blade SSR) untuk Backoffice Web:
1. Autentikasi berbasis **Session** yang aman dan terisolasi dari session user/mobile app (guard: `admin`).
2. Proteksi multi-lapis: Anti-brute-force rate limiting (5 req / 5 min), Session fixation prevention, CSP dengan cryptographic nonce, CSRF protection.
3. Tabel `admin_audit_logs` append-only untuk mencatat seluruh aksi create/update/delete seluruh modul admin.
4. Schema & self-service Two-Factor Authentication (2FA / TOTP) via profil admin.
5. Setup asset bundling dengan **Tailwind CSS v4** via `@tailwindcss/vite` dan Alpine.js.

---

## 2. Struktur File Yang Dibuat / Diubah

```
app/
├── Domain/
│   └── Admin/
│       ├── Models/
│       │   ├── Admin.php
│       │   └── AdminAuditLog.php
│       ├── Enums/
│       │   └── AdminRole.php
│       ├── Services/
│       │   ├── AdminAuthService.php
│       │   ├── AdminAuditLogService.php
│       │   └── AdminTwoFactorService.php
│       ├── Http/
│       │   ├── Controllers/
│       │   │   ├── Auth/
│       │   │   │   ├── AdminLoginController.php
│       │   │   │   └── AdminTwoFactorController.php
│       │   │   ├── Profile/
│       │   │   │   └── AdminProfileController.php
│       │   │   └── Security/
│       │   │       └── ConfirmPasswordController.php
│       │   ├── Middleware/
│       │   │   ├── AdminAuthenticate.php
│       │   │   ├── AdminContentSecurityPolicy.php
│       │   │   ├── AdminIpWhitelist.php
│       │   │   └── LogAdminActivity.php
│       │   └── Requests/
│       │       ├── AdminLoginRequest.php
│       │       ├── UpdateProfileRequest.php
│       │       ├── VerifyTwoFactorRequest.php
│       │       └── ConfirmAdminPasswordRequest.php
│       └── Providers/
│           └── AdminDomainServiceProvider.php
database/
├── migrations/
│   ├── 2026_10_10_000001_create_admins_table.php
│   └── 2026_10_10_000002_create_admin_audit_logs_table.php
│   └── 2026_10_10_000003_seed_super_admin.php (atau DatabaseSeeder)
resources/
├── views/
│   └── admin/
│       ├── layouts/
│       │   ├── app.blade.php           (Sidebar, topbar, flash alerts, CSP nonce)
│       │   └── guest.blade.php         (Minimal card layout untuk login)
│       ├── components/
│       │   ├── alert.blade.php
│       │   ├── button.blade.php
│       │   ├── input.blade.php
│       │   └── modal.blade.php
│       ├── auth/
│       │   ├── login.blade.php
│       │   ├── confirm-password.blade.php
│       │   └── two-factor.blade.php
│       └── profile/
│           ├── index.blade.php
│           └── two-factor-setup.blade.php
routes/
└── admin.php                           (Prefix /admin, middleware web)
tests/Feature/Admin/
├── Auth/
│   ├── AdminLoginTest.php
│   ├── AdminBruteForceTest.php
│   ├── AdminSessionSecurityTest.php
│   └── AdminTwoFactorTest.php
└── Security/
    ├── AdminAuditLogTest.php
    └── AdminPasswordConfirmTest.php
```

---

## 3. Database & Migrations

### 3.1 Migration `admins` Table
**File**: `database/migrations/2026_10_10_000001_create_admins_table.php`

```php
Schema::create('admins', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('email', 150)->unique();
    $table->string('password');
    $table->string('role', 30)->default('ops_admin'); // super_admin, ops_admin, finance_admin
    $table->boolean('is_active')->default(true);
    $table->text('two_factor_secret')->nullable(); // Encrypted TOTP secret
    $table->timestamp('two_factor_confirmed_at')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->string('last_login_ip', 45)->nullable();
    $table->rememberToken();
    $table->timestamps();

    $table->index(['role', 'is_active']);
});
```

### 3.2 Migration `admin_audit_logs` Table (Append-Only)
**File**: `database/migrations/2026_10_10_000002_create_admin_audit_logs_table.php`

```php
Schema::create('admin_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
    $table->string('action', 100);             // misal: 'transaction.refund', 'provider.toggle'
    $table->string('target_type', 100)->nullable(); // misal: 'Transaction', 'Provider'
    $table->string('target_id', 100)->nullable();
    $table->jsonb('changes')->nullable();      // ['old' => [...], 'new' => [...]]
    $table->text('reason')->nullable();        // Alasan aksi (wajib untuk financial refund/override)
    $table->string('ip_address', 45);
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->useCurrent(); // APPEND ONLY: TIDAK ADA updated_at

    $table->index(['admin_id', 'created_at']);
    $table->index(['target_type', 'target_id']);
    $table->index('action');
});
```

---

## 4. Model & Enum

### 4.1 `App\Domain\Admin\Enums\AdminRole`
```php
namespace App\Domain\Admin\Enums;

enum AdminRole: string
{
    case SUPER_ADMIN  = 'super_admin';
    case OPS_ADMIN    = 'ops_admin';
    case FINANCE_ADMIN = 'finance_admin';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN   => 'Super Administrator',
            self::OPS_ADMIN     => 'Operations & Support',
            self::FINANCE_ADMIN => 'Finance & Settlement',
        };
    }
}
```

### 4.2 `App\Domain\Admin\Models\Admin`
- Extends `Illuminate\Foundation\Auth\User`
- Property `$casts`:
  - `role` => `AdminRole::class`
  - `is_active` => `'boolean'`
  - `two_factor_secret` => `'encrypted'`
  - `two_factor_confirmed_at` => `'datetime'`
  - `last_login_at` => `'datetime'`
- Relasi:
  - `auditLogs(): HasMany` ke `AdminAuditLog::class`
- Helper methods:
  - `isSuperAdmin(): bool`
  - `isFinance(): bool`
  - `isOps(): bool`
  - `hasTwoFactor(): bool` (`!is_null($this->two_factor_confirmed_at)`)

### 4.3 `App\Domain\Admin\Models\AdminAuditLog`
- Extends `Illuminate\Database\Eloquent\Model`
- `$timestamps = false`
- `$casts`: `changes` => `'array'`, `created_at` => `'datetime'`
- Relasi: `admin(): BelongsTo`

---

## 5. Security Architecture & Middleware

### 5.1 Content Security Policy (`AdminContentSecurityPolicy`)
Menyediakan nonce acak per request di session/request attribute untuk semua script inline:
- Header: `Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{NONCE}' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'`
- Blade view layout wajib menyisipkan `nonce="{{ $csp_nonce }}"` pada setiap `<script>` tag.

### 5.2 Rate Limiter Admin Login
Di `bootstrap/app.php` atau `AppServiceProvider`:
```php
RateLimiter::for('admin-login', function (Request $request) {
    return Limit::perMinutes(5, 5)->by($request->ip())->response(function () {
        return back()->withErrors([
            'email' => 'Terlalu banyak percobaan login. Coba lagi dalam 5 menit.'
        ]);
    });
});
```

### 5.3 Password Confirmation Timeout (`password_timeout`)
Di `config/auth.php`:
```php
'password_timeout' => 10800, // 3 jam (sesuai kesepakatan)
```
Middleware `password.confirm:admin.password.confirm` digunakan pada route aksi finansial/kritis. Admin hanya perlu konfirmasi password 1x per 3 jam window.

### 5.4 Audit Trail Service (`AdminAuditLogService`)
Method `log(Admin $admin, string $action, ?Model $target = null, ?array $changes = null, ?string $reason = null)`:
- Menulis log ke `admin_audit_logs`.
- Mengekstrak IP dari `request()->ip()` dan User-Agent dari `request()->userAgent()`.

---

## 6. Controllers & Methods

### 6.1 `AdminLoginController`
- `showLoginForm()`: Render `admin.auth.login`. Redirect ke `/admin/dashboard` jika sudah authenticated.
- `login(AdminLoginRequest $request)`:
  1. Validasi email & password.
  2. Throttle check (5 attempt / 5 min).
  3. Cek `Auth::guard('admin')->attempt(['email' => $email, 'password' => $password, 'is_active' => true], $remember)`.
  4. Jika gagal: hit throttle limiter, kembalikan generic error "Email atau password salah".
  5. Jika sukses:
     - Clear throttle limiter.
     - `session()->regenerate()` (anti-session fixation).
     - Update `$admin->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()])`.
     - Log activity: `admin.login`.
     - Jika 2FA aktif: set session `admin_2fa_pending = true` dan redirect ke `/admin/two-factor`.
     - Redirect intended ke `/admin/dashboard`.
- `logout(Request $request)`:
  1. Log activity: `admin.logout`.
  2. `Auth::guard('admin')->logout()`.
  3. `$request->session()->invalidate()`.
  4. `$request->session()->regenerateToken()`.
  5. Redirect ke `/admin/login`.

### 6.2 `AdminTwoFactorController`
- `showForm()`: Tampilkan form input 6 digit OTP TOTP.
- `verify(VerifyTwoFactorRequest $request)`:
  1. Cek TOTP token menggunakan `AdminTwoFactorService::verifyKey($secret, $code)`.
  2. Jika valid: hapus session `admin_2fa_pending`, redirect ke dashboard.
  3. Jika invalid: return error "Kode 2FA tidak valid".

### 6.3 `AdminProfileController`
- `index()`: Form ganti nama, email, password, dan card status 2FA.
- `update(UpdateProfileRequest $request)`: Simpan perubahan, log `profile.updated`.
- `enableTwoFactor()`: Generate secret TOTP & QR code SVG, simpan draft secret di session.
- `confirmTwoFactor(Request $request)`: Verifikasi OTP pertama kali, simpan `$admin->two_factor_secret` & `$admin->two_factor_confirmed_at = now()`.

---

## 7. Routing (`routes/admin.php`)

```php
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest Routes
    Route::middleware(['guest:admin'])->group(function () {
        Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'login'])
            ->middleware('throttle:admin-login')
            ->name('login.submit');
    });

    // 2FA Challenge Route
    Route::middleware(['auth:admin'])->group(function () {
        Route::get('/two-factor', [AdminTwoFactorController::class, 'showForm'])->name('two-factor.form');
        Route::post('/two-factor', [AdminTwoFactorController::class, 'verify'])->name('two-factor.verify');
    });

    // Authenticated Admin Routes
    Route::middleware(['auth:admin', 'admin.csp'])->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

        // Profile & 2FA Setup (Self-Service)
        Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
        Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/2fa/enable', [AdminProfileController::class, 'enableTwoFactor'])->name('profile.2fa.enable');
        Route::post('/profile/2fa/confirm', [AdminProfileController::class, 'confirmTwoFactor'])->name('profile.2fa.confirm');
        Route::delete('/profile/2fa/disable', [AdminProfileController::class, 'disableTwoFactor'])->name('profile.2fa.disable');

        // Password Confirm Routes
        Route::get('/confirm-password', [ConfirmPasswordController::class, 'showConfirmForm'])->name('password.confirm');
        Route::post('/confirm-password', [ConfirmPasswordController::class, 'confirm'])->name('password.confirm.submit');
    });
});
```

---

## 8. Frontend Assets & Layouts

1. **Vite + Tailwind v4**:
   - `resources/css/app.css` menggunakan `@import "tailwindcss";`
   - Warna tema admin: Slate 900 (dark sidebar), Indigo 600 (primary actions), Emerald 500 (success), Rose 500 (danger).
2. **Layouts**:
   - `admin/layouts/guest.blade.php`: Clean centered card dengan background gradient gelap, logo sistem, alert feedback.
   - `admin/layouts/app.blade.php`:
     - Responsive sidebar (Alpine.js state `open: false`).
     - Top navigation bar: admin badge role (`super_admin`, `finance`, `ops`), user avatar dropdown, logout form button.
     - Flash notification container (`session('success')`, `session('error')`).
     - Script tag Alpine.js CDN dengan `defer` dan nonce CSP.

---

## 9. Feature Tests Wajib (Test Coverage 100%)

File test di `tests/Feature/Admin/Auth/`:

1. `AdminLoginTest.php`:
   - `test_can_render_login_page()`
   - `test_active_admin_can_login_with_correct_credentials()`
   - `test_inactive_admin_cannot_login()`
   - `test_login_fails_with_invalid_password()`
   - `test_admin_session_is_regenerated_after_successful_login()`
   - `test_admin_can_logout_and_session_is_invalidated()`

2. `AdminBruteForceTest.php`:
   - `test_login_is_throttled_after_5_failed_attempts_from_same_ip()`
   - `test_throttle_cleared_after_successful_login()`

3. `AdminAuditLogTest.php`:
   - `test_audit_log_recorded_on_admin_login_and_logout()`
   - `test_audit_log_captures_correct_ip_and_user_agent()`
   - `test_audit_log_table_is_append_only()`

4. `AdminTwoFactorTest.php`:
   - `test_admin_with_2fa_redirected_to_two_factor_form()`
   - `test_admin_can_complete_2fa_challenge_with_valid_totp()`
   - `test_admin_cannot_access_dashboard_while_2fa_pending()`

5. `AdminPasswordConfirmTest.php`:
   - `test_password_confirm_prompt_shown_when_accessing_protected_action()`
   - `test_confirmed_password_is_valid_for_3_hours_without_prompt()`
