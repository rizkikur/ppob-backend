# Task Spec: Phase 1 — Auth Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (section users, otp_codes), `docs/security-design.md` (section 1 & 2), `docs/openapi.yaml` (endpoint /auth/*).
>
> **Selesai = kode + test lulus + update dev-roadmap.md**

---

## Konteks

Phase ini mengimplementasi alur registrasi dan login user menggunakan OTP WhatsApp.
Tidak ada password — autentikasi berbasis nomor telepon + OTP + Sanctum token.

---

## File yang Harus Dibuat

```
app/Domain/Auth/
├── Models/
│   ├── User.php
│   ├── UserTier.php
│   └── OtpCode.php
├── Services/
│   ├── OtpService.php
│   └── AuthService.php
├── Drivers/
│   └── FonnteOtpDriver.php          ← HTTP call ke Fonnte API
├── Contracts/
│   └── OtpDriverInterface.php
├── Http/
│   ├── Controllers/
│   │   └── AuthController.php
│   ├── Requests/
│   │   ├── SendOtpRequest.php
│   │   ├── VerifyOtpRequest.php
│   │   └── LogoutRequest.php
│   └── Resources/
│       ├── UserResource.php
│       └── TokenResource.php
tests/Feature/Auth/
├── RegisterLoginTest.php
└── OtpRateLimitTest.php
```

---

## Item 1.1 — Model: User, UserTier, OtpCode

### `App\Domain\Auth\Models\UserTier`
- Tabel: `user_tiers` (sudah ada migration)
- Field: `id`, `name`, `description`, `created_at`, `updated_at`
- Relasi: `hasMany(User::class)`

### `App\Domain\Auth\Models\User`
- Tabel: `users` (sudah ada migration — **jangan buat migration baru**)
- Extends `Illuminate\Foundation\Auth\User` (bukan Model biasa, agar compatible dengan Sanctum)
- Fillable: `user_tier_id`, `name`, `phone`, `email`, `pin_hash`, `role`, `is_active`, `is_verified`
- Hidden: `pin_hash`, `remember_token`
- Cast: `is_active` → boolean, `is_verified` → boolean
- Relasi:
  - `belongsTo(UserTier::class)`
  - `hasOne(Wallet::class)` — forward declaration, wallet belum ada, gunakan string class
  - `hasMany(OtpCode::class)`
- Sanctum: gunakan `HasApiTokens` trait

### `App\Domain\Auth\Models\OtpCode`
- Tabel: `otp_codes` (sudah ada migration)
- Field: `id`, `user_id`, `code` (6 digit), `purpose` (register|login|reset_pin), `expires_at`, `used_at`, `attempt_count`, `created_at`
- Relasi: `belongsTo(User::class)`
- Method helper: `isExpired(): bool` → `now()->gt($this->expires_at)`
- Method helper: `isUsed(): bool` → `$this->used_at !== null`
- Method helper: `isExhausted(): bool` → `$this->attempt_count >= 3`

---

## Item 1.2 — Service: OtpService

Lokasi: `App\Domain\Auth\Services\OtpService`

### Dependency
- `OtpDriverInterface` — inject via constructor (bukan hardcode driver)
- Redis cache (via `Illuminate\Support\Facades\Cache`) — rate limit

### Method: `send(string $phone, string $purpose): void`

**Logic:**
1. Cek rate limit di Redis: key = `otp:rl:{phone}:{purpose}` — max 3 request per 10 menit
   - Jika sudah 3×: throw `\App\Domain\Auth\Exceptions\OtpRateLimitException` (error_code: `OTP_RATE_LIMIT_EXCEEDED`)
2. Generate 6-digit code: `str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)`
3. `DB::transaction`: insert ke `otp_codes` dengan `expires_at = now()->addMinutes(5)`, `attempt_count = 0`
4. Increment Redis counter (`Cache::increment`), set TTL 10 menit jika baru
5. Panggil `$this->driver->send($phone, $code)`

**Error yang mungkin dilempar:**
- `OtpRateLimitException` — 429
- `OtpDeliveryException` — 503 (jika driver gagal kirim)

### Method: `verify(string $phone, string $code, string $purpose): OtpCode`

**Logic:**
1. Ambil OtpCode terbaru yang belum used, belum expired, tujuan sesuai:
   ```php
   OtpCode::whereHas('user', fn($q) => $q->where('phone', $phone))
       ->where('purpose', $purpose)
       ->whereNull('used_at')
       ->latest()
       ->lockForUpdate()
       ->firstOrFail()
   ```
2. Jika `isExpired()`: throw `OtpExpiredException` (error_code: `OTP_EXPIRED`)
3. Jika `isExhausted()`: throw `OtpExhaustedException` (error_code: `OTP_MAX_ATTEMPTS_EXCEEDED`)
4. `DB::transaction`:
   - Increment `attempt_count`
   - Jika code salah: throw `OtpInvalidException` (error_code: `OTP_INVALID`)
   - Jika benar: update `used_at = now()`
5. Return `$otpCode`

---

## Item 1.3 — Driver: FonnteOtpDriver

Lokasi: `App\Domain\Auth\Drivers\FonnteOtpDriver`
Implements: `App\Domain\Auth\Contracts\OtpDriverInterface`

### Interface
```php
interface OtpDriverInterface {
    public function send(string $phone, string $code): void;
}
```

### FonnteOtpDriver
- Config: `config('ppob.otp.fonnte_token')` — token Fonnte API
- HTTP POST ke `https://api.fonnte.com/send` dengan header `Authorization: {token}`
- Body: `['target' => $phone, 'message' => "Kode OTP Anda: {$code}. Berlaku 5 menit."]`
- Gunakan `Http::timeout(10)->post(...)`
- Jika HTTP gagal atau response tidak success: throw `OtpDeliveryException`

> **Catatan**: driver `WaBizOtpDriver` sudah ada di folder, tapi untuk Phase 1 cukup implement `FonnteOtpDriver`. `WaBizOtpDriver` bisa diisi nanti.

---

## Item 1.4 — Service: AuthService

Lokasi: `App\Domain\Auth\Services\AuthService`

### Method: `register(array $data): User`
- `$data` berisi: `phone`, `name`, `user_tier_id` (default: tier terendah jika tidak diisi)
- Cek apakah phone sudah ada: jika ya, throw `UserAlreadyExistsException` (error_code: `USER_ALREADY_EXISTS`)
- `DB::transaction`:
  - Buat `User` baru dengan `is_verified = false`
  - Buat `Wallet` (user_id, balance = 0) — **langsung insert** (WalletService belum ada, ini setup awal)
  - Kirim OTP via `OtpService::send($phone, 'register')`
- Return `$user`

### Method: `verifyRegistration(string $phone, string $code): array`
- Panggil `OtpService::verify($phone, $code, 'register')`
- Update `user->is_verified = true`
- Issue Sanctum token: `$user->createToken('mobile')`
- Return `['user' => $user, 'token' => $token->plainTextToken]`

### Method: `requestLoginOtp(string $phone): void`
- Cari user by phone, pastikan `is_active = true` dan `is_verified = true`
- Jika tidak ada: throw `UserNotFoundException` (error_code: `USER_NOT_FOUND`)
- Panggil `OtpService::send($phone, 'login')`

### Method: `login(string $phone, string $code): array`
- Panggil `OtpService::verify($phone, $code, 'login')`
- Ambil user, pastikan masih aktif
- Revoke token lama (opsional — bisa skip untuk sekarang)
- Issue Sanctum token: `$user->createToken('mobile')`
- Return `['user' => $user, 'token' => $token->plainTextToken]`

### Method: `logout(User $user): void`
- Revoke current token: `$user->currentAccessToken()->delete()`

---

## Item 1.5 — Controller + Request + Resource

### `AuthController`
- Extends `ApiController` (`App\Domain\Shared\Http\ApiController`)
- Inject `AuthService` via constructor

| Method | Route | Description |
|--------|-------|-------------|
| `sendOtp` | `POST /auth/otp` | Kirim OTP (register atau login) |
| `register` | `POST /auth/register` | Daftar user baru |
| `verifyRegister` | `POST /auth/verify-register` | Verifikasi OTP registrasi → dapat token |
| `login` | `POST /auth/login` | Login → verifikasi OTP → dapat token |
| `logout` | `POST /auth/logout` | Revoke token (requires auth) |
| `me` | `GET /auth/me` | Data user saat ini (requires auth) |

**Response format** — selalu pakai `$this->success(...)` atau `$this->error(...)` dari `ApiController`.

### Requests

**`SendOtpRequest`**
```php
'phone'   => 'required|string|regex:/^08[0-9]{9,12}$/'
'purpose' => 'required|in:register,login'
```

**`VerifyOtpRequest`**
```php
'phone' => 'required|string'
'code'  => 'required|string|size:6'
```

### Resources

**`UserResource`**
```php
[
    'id'          => $this->id,
    'name'        => $this->name,
    'phone'       => $this->phone,
    'email'       => $this->email,
    'role'        => $this->role,
    'tier'        => $this->whenLoaded('tier', fn() => $this->tier->name),
    'is_verified' => $this->is_verified,
    'created_at'  => $this->created_at->toIso8601String(),
]
```

**`TokenResource`**
```php
[
    'token'      => $this->token,
    'token_type' => 'Bearer',
    'user'       => new UserResource($this->user),
]
```

---

## Item 1.6 — Feature Test: Register + OTP Flow

File: `tests/Feature/Auth/RegisterLoginTest.php`

**Test cases (wajib ada semua):**

1. `test_user_can_register_and_receive_otp`
   - POST /auth/otp dengan `purpose=register` → 200, OTP terkirim (fake driver)
   - Assert `otp_codes` ada 1 row

2. `test_user_can_verify_registration_and_get_token`
   - Setup: buat user + OTP code di DB
   - POST /auth/verify-register → 200, dapat token + user data

3. `test_otp_expires_after_5_minutes`
   - Buat OTP dengan `expires_at = now()->subMinute()`
   - POST /auth/verify-register → 422, error_code = `OTP_EXPIRED`

4. `test_otp_invalid_increments_attempt_count`
   - Kirim kode salah → 422, error_code = `OTP_INVALID`
   - Assert `attempt_count` di DB = 1

5. `test_otp_blocked_after_3_wrong_attempts`
   - Buat OTP dengan `attempt_count = 3`
   - Kirim request → 422, error_code = `OTP_MAX_ATTEMPTS_EXCEEDED`

6. `test_duplicate_phone_registration_rejected`
   - Buat user dengan phone yang sama
   - POST /auth/otp dengan purpose=register → 409, error_code = `USER_ALREADY_EXISTS`

---

## Item 1.7 — Feature Test: OTP Rate Limit

File: `tests/Feature/Auth/OtpRateLimitTest.php`

**Test cases (wajib ada semua):**

1. `test_otp_rate_limit_after_3_requests_in_10_minutes`
   - Kirim POST /auth/otp 3× berturut-turut → semua 200
   - Kirim yang ke-4 → 429, error_code = `OTP_RATE_LIMIT_EXCEEDED`

2. `test_otp_rate_limit_resets_after_cooldown`
   - Kirim 3× → block
   - Travel time +11 menit (gunakan `$this->travel(11)->minutes()`)
   - Kirim lagi → 200 (berhasil)

---

## Panduan Implementasi

### Setup di DomainServiceProvider
Pastikan binding ini ada di `App\Providers\DomainServiceProvider::register()`:
```php
$this->app->bind(
    \App\Domain\Auth\Contracts\OtpDriverInterface::class,
    \App\Domain\Auth\Drivers\FonnteOtpDriver::class
);
```

### Fake OTP Driver untuk Test
Buat `App\Domain\Auth\Drivers\FakeOtpDriver` yang hanya log/store ke array, tidak HTTP.
Bind di `TestCase::setUp()` atau gunakan `$this->instance(OtpDriverInterface::class, new FakeOtpDriver())`.

### Exception Classes yang Perlu Dibuat
Semua di `App\Domain\Auth\Exceptions\`:
- `OtpRateLimitException` → HTTP 429, error_code `OTP_RATE_LIMIT_EXCEEDED`
- `OtpExpiredException` → HTTP 422, error_code `OTP_EXPIRED`
- `OtpExhaustedException` → HTTP 422, error_code `OTP_MAX_ATTEMPTS_EXCEEDED`
- `OtpInvalidException` → HTTP 422, error_code `OTP_INVALID`
- `OtpDeliveryException` → HTTP 503, error_code `OTP_DELIVERY_FAILED`
- `UserNotFoundException` → HTTP 404, error_code `USER_NOT_FOUND`
- `UserAlreadyExistsException` → HTTP 409, error_code `USER_ALREADY_EXISTS`

Register handler di `bootstrap/app.php` atau di `App\Exceptions\Handler`.

### Update dev-roadmap.md
Setelah tiap item selesai, ubah kolom Status dari `⬜ Belum` menjadi `✅ Selesai`.

---

## Definition of Done Phase 1
- [x] Semua 7 file model/service/driver/controller/resource dibuat
- [x] `php artisan test --filter=Auth` lulus semua (min 8 test)
- [x] Tidak ada `response()->json()` mentah di controller
- [x] Tidak ada file PHP dengan BOM
- [x] Semua error_code menggunakan string yang didefinisikan di dokumen ini
- [x] Status di `docs/dev-roadmap.md` item 1.1–1.7 diupdate ke `✅ Selesai`
