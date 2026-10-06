# Task Spec: Phase 2 — Security Domain (PIN)

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (section pin_verification_tokens), `docs/security-design.md` (section 2 & 11), `docs/openapi.yaml` (endpoint /security/pin/*).
>
> **Selesai = kode + test lulus + update dev-roadmap.md**

---

## Konteks

Phase ini mengimplementasikan alur manajemen dan verifikasi PIN transaksi:
1. PIN tidak pernah dikirim ke endpoint transaksi langsung.
2. Alur PIN: `POST /security/pin/challenge` → `POST /security/pin/verify` → terbitkan `pin_verification_token` sekali pakai (expire 5 menit).
3. Endpoint transaksi memvalidasi header `X-Pin-Token` via `PinTokenMiddleware`.
4. Proteksi brute force: maksimal 5 kali percobaan salah dalam 15 menit → akun di-lock sementara (`PIN_LOCKED`, HTTP 429).

---

## File yang Dibuat & Dikelola

```
app/Domain/Security/
├── Models/
│   └── PinVerificationToken.php
├── Services/
│   └── PinService.php
├── Exceptions/
│   ├── PinNotSetException.php
│   ├── PinInvalidException.php
│   ├── PinLockedException.php
│   ├── PinTokenInvalidException.php
│   ├── PinTokenExpiredException.php
│   └── PinTokenUsedException.php
├── Http/
│   ├── Controllers/
│   │   └── PinController.php
│   ├── Requests/
│   │   ├── SetPinRequest.php
│   │   ├── ChangePinRequest.php
│   │   ├── PinChallengeRequest.php
│   │   └── PinVerifyRequest.php
│   └── Resources/
│       └── PinTokenResource.php
app/Domain/Shared/Middleware/
└── PinTokenMiddleware.php
tests/Feature/Security/
├── PinFlowTest.php
└── PinBruteForceTest.php
```

---

## Item 2.1 — Model: PinVerificationToken

- Tabel: `pin_verification_tokens`
- Field: `id`, `user_id`, `token` (varchar 64), `purpose` (varchar 50), `used_at`, `expires_at`, `created_at`
- Relasi: `belongsTo(User::class)`
- Helper methods:
  - `isUsed(): bool` → `$this->used_at !== null`
  - `isExpired(): bool` → `$this->expires_at->isPast()`
  - `isValid(): bool` → `!$this->isUsed() && !$this->isExpired()`

---

## Item 2.2 — Service: PinService

Lokasi: `App\Domain\Security\Services\PinService`

### Method: `setPin(User $user, string $pin): void`
- Cek `$user->hasPin()`: jika ya, throw `BusinessException('VALIDATION_ERROR', ...)`
- Update `$user->pin_hash = Hash::make($pin)`

### Method: `changePin(User $user, string $newPin): void`
- Update `$user->pin_hash = Hash::make($newPin)`

### Method: `challenge(User $user, string $purpose): string`
- Cek lock brute force: jika terkunci throw `PinLockedException` (429)
- Cek `$user->hasPin()`: jika belum throw `PinNotSetException` (422)
- Generate UUID `challenge_id`, simpan ke Cache (TTL 10 menit) dengan data `user_id` dan `purpose`
- Return `challenge_id`

### Method: `verify(User $user, string $challengeId, string $pin, ?string $purpose = null): PinVerificationToken`
- Cek lock brute force: jika terkunci throw `PinLockedException` (429)
- Ambil data challenge dari Cache: jika tidak ada atau purpose tidak cocok throw `PinTokenInvalidException` (422)
- Verifikasi PIN via `Hash::check($pin, $user->pin_hash)`:
  - Jika salah: increment attempt counter di Cache (TTL 15 menit). Jika sudah mencapai 5 kali, set lock di Cache dan throw `PinLockedException` (429), jika belum throw `PinInvalidException` (422).
  - Jika benar: reset counter percobaan, hapus challenge dari Cache.
- Terbitkan `PinVerificationToken`: hex 32 byte, expire 5 menit, simpan ke DB.
- Return model `PinVerificationToken`.

---

## Item 2.3 — Controller + Request + Resource

### `PinController`
Extends `ApiController` (`App\Domain\Shared\Http\ApiController`).

| Method | Route | Description |
|---|---|---|
| `setPin` | `POST /security/pin` | Set PIN pertama kali |
| `changePin` | `PUT /security/pin` | Ganti PIN (dilindungi `PinTokenMiddleware:change_pin`) |
| `challenge` | `POST /security/pin/challenge` | Minta challenge_id untuk tujuan tertentu |
| `verify` | `POST /security/pin/verify` | Verifikasi PIN & dapatkan `pin_verification_token` |

### `PinTokenMiddleware`
Lokasi: `App\Domain\Shared\Middleware\PinTokenMiddleware`
- Memeriksa header `X-Pin-Token`
- Token wajib ada di `pin_verification_tokens` dan milik user yang sedang terautentikasi
- Token belum expired (`expires_at > now()`) dan belum digunakan (`used_at IS NULL`)
- Jika diberikan parameter purpose, token purpose harus cocok
- Jika valid: update `used_at = now()` (konsumsi token sekali pakai) dan lanjutkan request

---

## Item 2.5 & 2.6 — Feature Tests

1. `tests/Feature/Security/PinFlowTest.php`:
   - `test_challenge_fails_if_pin_not_set`
   - `test_user_can_set_pin_first_time`
   - `test_user_cannot_set_pin_again`
   - `test_challenge_and_verify_pin_flow`
   - `test_change_pin_flow_with_token`
   - `test_expired_token_is_rejected`
   - `test_token_with_wrong_purpose_is_rejected`

2. `tests/Feature/Security/PinBruteForceTest.php`:
   - `test_5_wrong_pin_attempts_triggers_lock` (HTTP 429 PIN_LOCKED)
   - `test_challenge_rejected_while_locked`
   - `test_lock_resets_after_cooldown` (travel 16 menit)

---

## Definition of Done Phase 2
- [x] Semua file model/service/controller/requests/resource/exceptions dibuat
- [x] `PinTokenMiddleware` memvalidasi dan mengonsumsi `X-Pin-Token` sekali pakai
- [x] Brute force protection: 5 percobaan salah mengunci selama 15 menit (HTTP 429)
- [x] `php vendor/phpunit/phpunit/phpunit --filter=Security` lulus semua (10 test)
- [x] Tidak ada `response()->json()` mentah di controller
- [x] Tidak ada file PHP dengan BOM
- [x] Semua error_code sesuai standar `docs/security-design.md`
- [x] Status di `docs/dev-roadmap.md` item 2.1–2.6 diupdate ke `✅ Selesai`
