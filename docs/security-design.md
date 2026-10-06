# Security Design — PPOB Backend

Dokumen ini mendeskripsikan desain keamanan lengkap untuk semua lapisan sistem.
Setiap implementasi keamanan **harus merujuk ke dokumen ini** — jangan membuat keputusan keamanan ad-hoc.

---

## 1. Autentikasi Pengguna (Sanctum)

### Alur Login
```
POST /auth/otp/request  →  kirim OTP ke WhatsApp
POST /auth/otp/verify   →  verifikasi OTP  →  issue Sanctum token
```

### Token Sanctum
- Token disimpan di tabel `personal_access_tokens` (dikelola Sanctum)
- Token bersifat opaque (bukan JWT)
- Kirim token via header: `Authorization: Bearer {token}`
- Expiry default: 30 hari, configurable via `config/sanctum.php`
- Satu user dapat memiliki beberapa token aktif (multi-device)
- Saat logout, token yang bersangkutan dihapus (`currentAccessToken()->delete()`)

### Proteksi Endpoint
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // semua endpoint yang butuh autentikasi
});
```

---

## 2. Alur PIN (Security Domain)

PIN **tidak pernah dikirim langsung ke endpoint transaksi**. Alur wajib:

```
Step 1: POST /security/pin/challenge
        → Server membuat tantangan (challenge), return: { challenge_id }

Step 2: POST /security/pin/verify
        Body: { challenge_id, pin }  ← PIN hanya dikirim di sini
        → Server verifikasi PIN menggunakan bcrypt
        → Jika benar: buat pin_verification_token (hex 32-byte, expire 5 menit)
        → Return: { pin_verification_token }

Step 3: POST /transactions  (atau endpoint lain yang butuh PIN)
        Header: X-Pin-Token: {pin_verification_token}
        → Server validasi token: ada, belum dipakai, belum expire
        → Tandai token sebagai used (set used_at = now())
        → Lanjutkan transaksi
```

### Aturan pin_verification_token
- **Sekali pakai**: setelah digunakan satu kali, `used_at` di-set dan token tidak dapat dipakai lagi
- **Expire**: 5 menit sejak dibuat (`expires_at = now() + 5 minutes`)
- **Scope purpose**: token dibuat dengan `purpose` tertentu (contoh: `transaction`), validasi harus cocok
- **Bukan Bearer token**: dikirim via header khusus `X-Pin-Token`, bukan `Authorization`

### Hash PIN
- PIN 6 digit (angka saja), di-hash menggunakan `bcrypt` (cost factor 12)
- Tidak pernah disimpan plaintext, tidak pernah dikirim plaintext kecuali di Step 2
- Proteksi brute force: maks 5 percobaan PIN salah → akun di-lock sementara 15 menit

---

## 3. OTP WhatsApp

### Provider
- Gunakan **Fonnte** atau **WhatsApp Business API** (konfigurable via `.env`)
- Interface: `app/Domain/Auth/Contracts/OtpSenderInterface.php`
- Driver aktif dikonfigurasi di `config/ppob.php`:
  ```php
  'otp_driver' => env('OTP_DRIVER', 'fonnte'), // 'fonnte' | 'wabiz'
  ```

### Aturan OTP
- **Panjang**: 6 digit angka
- **Expire**: 5 menit
- **Maks percobaan**: 3 kali per OTP code (field `attempts`)
- **Cooldown**: tidak boleh kirim OTP baru dalam 60 detik sejak OTP terakhir yang masih aktif
- **Rate limit**: maks 5 OTP per nomor per jam (enforce via Redis)
- Setelah OTP berhasil diverifikasi, set `verified_at` dan **jangan bisa dipakai lagi**

### Payload WA yang Dikirim
```
Kode OTP Anda: {code}
Berlaku 5 menit. Jangan bagikan kode ini ke siapapun.
```

---

## 4. Webhook Security (PPOB & Wallet Callback)

Endpoint webhook **terpisah** dari API publik dan butuh validasi berlapis:

### Alur Validasi Berurutan
```
1. mTLS (mutual TLS)
   → Pastikan client certificate valid dan sesuai CA yang diketahui
   → Terminate di nginx/load balancer (bukan di PHP)

2. X-Signature (HMAC-SHA256)
   → Signature = HMAC-SHA256(secret_key, raw_request_body)
   → Header: X-Signature: sha256={hex_digest}
   → Tolak jika signature tidak cocok

3. X-Timestamp
   → Header: X-Timestamp: {unix_timestamp}
   → Tolak jika |now() - timestamp| > 300 detik (5 menit)
   → Proteksi replay attack

4. Idempotency (event_id)
   → Cek tabel processed_webhook_events WHERE event_id = ?
   → Jika sudah ada → return 409 (jangan diproses ulang)
   → Jika belum → proses, lalu INSERT ke processed_webhook_events
```

### Secret Key Per Provider
- Setiap provider PPOB punya `webhook_secret` unik
- Disimpan di `config/ppob.php` di-load dari `.env`
- Contoh: `PPOB_PLN_WEBHOOK_SECRET=xxx`

---

## 5. Partner API Authentication (Open API)

Partner mengautentikasi setiap request dengan **API Key + HMAC Signature**:

### Header yang Dibutuhkan
```
X-Api-Key:   {api_key}         ← Identifikasi partner
X-Timestamp: {unix_timestamp}  ← Cegah replay attack
X-Signature: {hmac_sha256}     ← Verifikasi integritas body
```

### Cara Hitung Signature
```
string_to_sign = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + sha256(raw_body)
signature      = HMAC-SHA256(partner.secret, string_to_sign)
X-Signature    = hex(signature)
```

### Validasi di Server
1. Cari `Partner` berdasarkan `X-Api-Key`
2. Cek `is_active = true`
3. Cek IP whitelist jika `allowed_ips` tidak null
4. Verifikasi `X-Timestamp` (tolak jika selisih > 300 detik)
5. Hitung ulang signature, bandingkan dengan `X-Signature` (constant-time compare)
6. Rate limit: cek Redis key `partner:{id}:rpm`, jika > `rate_limit_rpm` → 429

---

## 6. Idempotency

Semua endpoint yang mengubah saldo atau stok wajib mendukung idempotency:

### Header
```
Idempotency-Key: {string, maks 100 karakter}
```

### Alur
```
1. Terima request dengan Idempotency-Key
2. Cek tabel idempotency_keys WHERE user_id = ? AND key_value = ?
3. Jika ditemukan → return response_body tersimpan dengan HTTP 409
4. Jika tidak ada → proses request
5. Setelah response dibuat → INSERT ke idempotency_keys (user_id, key_value, endpoint, response_code, response_body)
```

### Catatan
- Scope key adalah per `user_id` (bukan global)
- TTL: cleanup job hapus record > 30 hari
- Jika request pertama masih diproses (race condition) → 409 atau 202 dengan status `processing`

---

## 7. Rate Limiting

| Endpoint Group       | Limit            | Backend        |
|----------------------|------------------|----------------|
| OTP request          | 5/jam per nomor  | Redis          |
| Login attempt        | 10/menit per IP  | Redis          |
| PIN verify           | 5/15mnt per user | Redis          |
| API publik (umum)    | 100/menit per token | Redis       |
| Partner API          | Configurable per partner (default 60 RPM) | Redis |
| Webhook              | Tidak di-limit (validasi mTLS sudah cukup) | — |

---

## 8. Enkripsi Data Sensitif

| Data               | Cara Penyimpanan                             |
|--------------------|----------------------------------------------|
| PIN                | bcrypt hash (cost 12)                        |
| Partner secret     | `encrypt()` Laravel (AES-256-CBC)            |
| Provider config    | `encrypt()` Laravel, disimpan di kolom JSONB |
| OTP code           | Plaintext (expire pendek, tidak kritis)      |
| Access token       | SHA256 hash (dikelola Sanctum)               |

---

## 9. Input Validation

- Semua input divalidasi via Laravel Form Request (`app/Domain/*/Http/Requests/`)
- Nomor telepon wajib format E.164 (`/^\+62[0-9]{9,13}$/`)
- `customer_number` hanya boleh alphanumeric + tanda hubung
- Semua `amount` bertipe integer (cents), tidak boleh float
- Panjang `Idempotency-Key`: 1–100 karakter

---

## 10. CORS & Security Headers

```php
// config/cors.php
'allowed_origins' => ['*'],           // API publik — mobile app bisa dari mana saja
'allowed_methods' => ['GET', 'POST'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Pin-Token',
                       'Idempotency-Key', 'X-Api-Key', 'X-Timestamp', 'X-Signature'],
```

Security headers (tambahkan via middleware):
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

---

## 11. Standar Error Code

Semua response error **wajib** menggunakan `error_code` dari daftar berikut.
Jangan buat string error baru di luar daftar ini tanpa memperbarui dokumen ini terlebih dahulu.

| error_code                        | HTTP Status | Keterangan                                              |
|-----------------------------------|-------------|---------------------------------------------------------|
| `UNAUTHENTICATED`                 | 401         | Token tidak ada atau tidak valid                        |
| `FORBIDDEN`                       | 403         | Token valid tapi tidak punya akses ke resource ini      |
| `VALIDATION_ERROR`                | 422         | Input tidak valid (lihat field `errors`)                |
| `RESOURCE_NOT_FOUND`              | 404         | Resource yang diminta tidak ditemukan                   |
| `OTP_REQUIRED`                    | 403         | Nomor belum diverifikasi OTP                            |
| `OTP_INVALID`                     | 422         | Kode OTP salah                                          |
| `OTP_EXPIRED`                     | 422         | Kode OTP sudah kedaluwarsa                              |
| `OTP_MAX_ATTEMPTS`                | 429         | Terlalu banyak percobaan OTP                            |
| `OTP_RATE_LIMITED`                | 429         | Terlalu cepat meminta OTP baru                          |
| `PIN_NOT_SET`                     | 422         | PIN belum diatur                                        |
| `PIN_INVALID`                     | 422         | PIN salah                                               |
| `PIN_LOCKED`                      | 429         | Terlalu banyak percobaan PIN, akun terkunci sementara   |
| `PIN_TOKEN_INVALID`               | 422         | `X-Pin-Token` tidak valid                               |
| `PIN_TOKEN_EXPIRED`               | 422         | `X-Pin-Token` sudah kedaluwarsa                         |
| `PIN_TOKEN_USED`                  | 422         | `X-Pin-Token` sudah pernah dipakai                      |
| `IDEMPOTENCY_CONFLICT`            | 409         | `Idempotency-Key` sudah dipakai sebelumnya              |
| `IDEMPOTENCY_KEY_REQUIRED`        | 422         | Header `Idempotency-Key` tidak ada                      |
| `INSUFFICIENT_BALANCE`            | 422         | Saldo tidak mencukupi                                   |
| `PRODUCT_NOT_FOUND`               | 404         | SKU produk tidak ditemukan                              |
| `PRODUCT_INACTIVE`                | 422         | Produk sedang tidak aktif                               |
| `PRODUCT_TYPE_MISMATCH`           | 422         | Operasi tidak sesuai tipe produk (prepaid/postpaid)     |
| `INQUIRY_REQUIRED`                | 422         | Produk postpaid butuh inquiry dulu                      |
| `INQUIRY_EXPIRED`                 | 422         | Hasil inquiry sudah kedaluwarsa                         |
| `INQUIRY_AMOUNT_MISMATCH`         | 422         | Amount tidak cocok dengan hasil inquiry                 |
| `PROVIDER_UNAVAILABLE`            | 503         | Provider PPOB sedang tidak tersedia                     |
| `PROVIDER_ERROR`                  | 502         | Provider PPOB mengembalikan error                       |
| `TRANSACTION_NOT_FOUND`           | 404         | Transaksi tidak ditemukan                               |
| `WEBHOOK_SIGNATURE_INVALID`       | 401         | Signature webhook tidak valid                           |
| `WEBHOOK_TIMESTAMP_INVALID`       | 422         | Timestamp webhook di luar toleransi                     |
| `WEBHOOK_DUPLICATE`               | 409         | Event webhook sudah diproses sebelumnya                 |
| `PARTNER_KEY_INVALID`             | 401         | API key partner tidak valid                             |
| `PARTNER_SIGNATURE_INVALID`       | 401         | HMAC signature partner tidak valid                      |
| `PARTNER_RATE_LIMITED`            | 429         | Partner melampaui rate limit                            |
| `PARTNER_IP_BLOCKED`              | 403         | IP tidak ada di whitelist partner                       |
| `INTERNAL_ERROR`                  | 500         | Error server yang tidak terduga                         |

---

## 12. Audit & Logging

- Semua request ke endpoint transaksi dan wallet dicatat ke Laravel application log
- Partner request dicatat ke tabel `partner_logs`
- Webhook yang diterima dicatat ke tabel `processed_webhook_events`
- Log level: `INFO` untuk transaksi sukses, `ERROR` untuk gagal, `WARNING` untuk validasi ditolak
- **Jangan log**: PIN, raw password, partner secret, token Sanctum, data kartu
