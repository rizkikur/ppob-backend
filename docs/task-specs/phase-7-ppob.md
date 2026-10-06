# Task Spec: Phase 7 — PPOB Integration Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (Domain: PPOB), `docs/openapi.yaml` (endpoint /ppob/callback), `docs/security-design.md` (section 4, 11), `docs/architecture-decisions.md` (ADR-002, ADR-003, ADR-005), `docs/dev-roadmap.md` (Phase 7).
>
> **Selesai = kode + test lulus + update changelog & dev-roadmap.md + push branch**

---

## Konteks

Phase 7 mengintegrasikan driver-driver supplier PPOB dan webhook callback dari provider:
1. **Driver Supplier**:
   - `TelkomselDriver` (pulsa & paket data)
   - `IndosatDriver` (pulsa & paket data)
   - `XlDriver` (pulsa & paket data)
   - `PlnDriver` (token prepaid & tagihan postpaid)
   - `PdamDriver` (tagihan air)
   - Semua driver mengimplementasikan `PpobProviderInterface` (`inquiry`, `pay`, `checkStatus`, `verifyWebhookSignature`).
2. **Circuit Breaker Per-Supplier (ADR-003)**:
   - State `circuit:{supplier_code}:state` disimpan di Cache/Redis (`CLOSED`, `OPEN`, `HALF-OPEN`).
   - Saat circuit `OPEN`, request langsung ditolak dengan HTTP 503 `PROVIDER_UNAVAILABLE` tanpa menunggu timeout.
3. **Webhook Callback Provider (`POST /ppob/callback`)**:
   - Validasi berlapis ketat:
     1. `X-Signature`: HMAC-SHA256(secret, raw_body) → Tolak jika salah dengan HTTP 401 `WEBHOOK_SIGNATURE_INVALID`.
     2. `X-Timestamp`: Tolak jika `|now - timestamp| > 300` detik dengan HTTP 422 `WEBHOOK_TIMESTAMP_INVALID`.
     3. Idempotency `event_id` pada tabel `processed_webhook_events` → Tolak duplikat dengan HTTP 409 `WEBHOOK_DUPLICATE`.
   - Update transaksi terkait dan auto-refund jika provider melaporkan status failed.

---

## File yang Dibuat & Dikelola

```
app/Domain/Ppob/
├── Contracts/
│   └── PpobProviderInterface.php
├── Drivers/
│   ├── TelkomselDriver.php
│   ├── IndosatDriver.php
│   ├── XlDriver.php
│   ├── PlnDriver.php
│   └── PdamDriver.php
├── Services/
│   ├── CircuitBreakerService.php
│   └── PpobService.php
├── Models/
│   └── ProcessedWebhookEvent.php
└── Http/
    └── Controllers/
        └── WebhookController.php
tests/Feature/Ppob/
├── WebhookValidationTest.php
├── ProviderCircuitBreakerTest.php
└── ProviderDriverTest.php
```

---

## Rincian Item Pekerjaan

### Item 7.1 — Driver: `TelkomselDriver`
- Implementasi `PpobProviderInterface` untuk produk pulsa dan paket data Telkomsel.
- Mendukung verifikasi signature webhook HMAC-SHA256 dan mock helper untuk testing.

### Item 7.2 — Driver: `IndosatDriver`
- Implementasi `PpobProviderInterface` untuk produk pulsa dan paket data Indosat Ooredoo.
- Mendukung verifikasi signature webhook HMAC-SHA256 dan mock helper untuk testing.

### Item 7.3 — Driver: `XlDriver`
- Implementasi `PpobProviderInterface` untuk produk pulsa dan paket data XL Axiata / Axis.
- Mendukung verifikasi signature webhook HMAC-SHA256 dan mock helper untuk testing.

### Item 7.4 & 7.5 — Driver: `PlnDriver` & `PdamDriver`
- Finalisasi `PlnDriver` (prepaid token & postpaid tagihan) & `PdamDriver` (postpaid air).
- Verifikasi webhook HMAC-SHA256 terintegrasi dengan konfigurasi `config/ppob.php`.

### Item 7.6 — Service: `PpobService` & `CircuitBreakerService`
- Resolusi driver supplier dinamis berdasarkan `provider.driver`.
- Pengecekan circuit breaker sebelum memanggil provider (fail-fast HTTP 503 `PROVIDER_UNAVAILABLE`).
- Verifikasi signature webhook terpusat.

### Item 7.7 — Controller: `WebhookController`
- Endpoint `POST /ppob/callback` (dan `/api/v1/ppob/callback`).
- Validasi berlapis:
  1. Signature mismatch → 401 `WEBHOOK_SIGNATURE_INVALID`
  2. Timestamp stale (>300s) → 422 `WEBHOOK_TIMESTAMP_INVALID`
  3. Missing event_id → 422 `VALIDATION_ERROR`
  4. Duplicate event_id → 409 `WEBHOOK_DUPLICATE`
- Update transaksi terkait dan simpan ke `processed_webhook_events`.

### Item 7.8 & 7.9 — Feature Tests
- 7.8: `WebhookValidationTest` — Pengujian validasi berlapis (signature, timestamp, idempotency duplicate).
- 7.9: `ProviderCircuitBreakerTest` — Pengujian circuit breaker open fail-fast (503 PROVIDER_UNAVAILABLE) dan auto-recovery.
- Pengujian eksekusi driver pada `ProviderDriverTest`.
