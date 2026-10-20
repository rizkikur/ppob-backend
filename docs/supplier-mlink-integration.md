# Integrasi Supplier: M-Link Gateway (H2H-JSON V.1.0)

Dokumen ini menjelaskan arsitektur, alur transaksi, spesifikasi API, pemetaan Response Code (RC), serta implementasi driver teknis untuk integrasi gateway supplier **M-Link Gateway (Xymetri)** ke dalam sistem backend PPOB.

---

## 1. Ringkasan & Arsitektur Koneksi

- **Nama Supplier / Provider**: M-Link Gateway (Xymetri)
- **Kode Provider di Sistem**: `mlink`
- **Driver Class**: `App\Domain\Ppob\Drivers\MLinkGatewayDriver`
- **Protokol**: HTTP POST over TLS (JSON)
- **Header HTTP**: `Content-Type: application/json`
- **URL Endpoint**: `http(s)://endpoint_url:[port]/json`
- **Model Transaksi**: Hybrid Synchronous & Asynchronous:
  - **Synchronous**: Jawaban instan FINAL berupa `SUCCESS` (RC: `000`) atau `FAILED` (RC selain `000` dan `030`).
  - **Asynchronous**: Status `PENDING` (RC: `030`). Transaksi diproses di sisi biller operator. Hasil akhir diterima melalui **Webhook Callback** atau pengecekan berkala via API **Cek Status**.
- **Timeout Transaksi**: Default 60 detik. Jika koneksi timeout tanpa respon, sistem otomatis menandai status `pending`/`suspect` dan memicu pengecekan via `CekStatus`.

---

## 2. Mekanisme Keamanan & Signature

M-Link Gateway menerapkan validasi keamanan berbasis **IP Whitelist** (IP Statis) dan **HMAC-SHA256 Signature**.

### Rumus Signature
$$\text{Signature} = \text{Base64}\Big(\text{HMAC-SHA256}\big(\text{secret\_key},\, \text{client\_id} : \text{req\_id} : \text{password}\big)\Big)$$

- **String-to-sign**: `client_id . ":" . req_id . ":" . password`
- **HMAC Hash Algorithm**: `sha256` dengan opsi raw binary output (`true`).
- **Encoding**: Base64 string.

### Implementasi PHP:
```php
$payload   = $clientId . ':' . $reqId . ':' . $password;
$rawHash   = hash_hmac('sha256', $payload, $secretKey, true);
$signature = base64_encode($rawHash);
```

### Aturan Duplikasi Request ID (`req_id`):
- `req_id` adalah ID referensi unik per transaksi dari klien kita (menggunakan kode transaksi sistem kita).
- `req_id` yang sama **tidak dapat dikirim berulang dalam rentang 24 jam**.
- Namun, nomor tujuan (`cust_id`) dan produk (`product_id`) yang sama **dapat ditransaksikan berulang kali**, asalkan `req_id`-nya berbeda.

---

## 3. Format Request & Response Envelope

Setiap request ke M-Link Gateway dibungkus dalam format seragam:

```json
{
  "header": {
    "action": "NamaAction",
    "client_id": "CLIENT_ID_MITRA",
    "req_id": "UNIQUE_TRX_ID",
    "req_date": "YYYY-MM-DD HH:mm:ss",
    "signature": "BASE64_HMAC_SHA256"
  },
  "body": {
    // parameter spesifik per action
  }
}
```

Format respon seragam dari M-Link Gateway:
```json
{
  "header": {
    "req_id": "UNIQUE_TRX_ID_MITRA",
    "ref_id": "ID_REFERENSI_SUPPLIER"
  },
  "body": {
    "product_id": "KODE_PRODUK",
    "cust_id": "NOMOR_PELANGGAN",
    "status": "SUCCESS | FAILED | PENDING",
    "rc": "KODE_RESPON",
    "description": "Keterangan RC",
    "message": "Pesan Transaksi / SN",
    "unit_price": "Harga Supplier (String angka)",
    "balance_used": "Saldo Terpotong",
    "balance": "Sisa Saldo Deposit",
    "serial_no": "Nomor SN / Token PLN",
    "data": {}
  }
}
```

---

## 4. Rincian API Endpoint Supplier

| No | Nama Aksi (`action`) | Deskripsi | Input Body | Output Data Utama |
|---|---|---|---|---|
| 1 | `ProductInquiry` | Ambil seluruh katalog produk supplier | `{}` (kosong) | `data.product[]` (operator, product_id, denom, description, unit_price, status) |
| 2 | `Balance` | Cek sisa saldo deposit di supplier | `{}` (kosong) | `body.balance`, `body.message` |
| 3 | `CekStatus` | Pengecekan status transaksi | `{"order_req_id": "..."}` | Status transaksi, `serial_no`, RC, ref_id |
| 4 | `TopupRequest` | Pembelian Pulsa & Paket Data | `{"product_id": "...", "cust_id": "..."}` | `status`, `serial_no`, `balance_used` |
| 5 | `eWalletQuery` | Inquiry nama pemilik akun e-wallet | `{"product_id": "...", "cust_id": "..."}` | `data.cust_id`, `data.name` |
| 6 | `eWalletPayment` | Topup saldo e-wallet (Fixed / Open Denom) | `{"product_id": "...", "cust_id": "...", "amount": "..."}` | `status`, `serial_no` (Biller ref) |
| 7 | `PLNPrepaidQuery` | Inquiry nomor meter / ID Pelanggan PLN | `{"product_id": "...", "cust_id": "..."}` | `data.nama`, `data.daya`, `data.id_pel`, `data.no_meter` |
| 8 | `PLNPrepaidTopup` | Pembelian Token Listrik PLN Prepaid | `{"product_id": "...", "cust_id": "..."}` | `serial_no` (Token PLN 20 digit), `data.kwh`, `data.token`, dst |

---

## 5. Pemetaan Response Code (RC) M-Link ke Sistem Internal

Sistem PPOB kita menggunakan standar Response Code internal di `ProviderErrorMappingService`:
- `SUCCESS`: Transaksi Berhasil (`000`)
- `ERR_SUSPECT_PENDING`: Transaksi Sedang Diproses Upstream / Menunggu Callback (`030`)
- `ERR_TIMEOUT`: Timeout Komunikasi Upstream (`003`)
- `ERR_DEST_INVALID`: Nomor Tujuan Salah, Tidak Ditemukan, Diblokir, atau Expired (`007`, `012`, `013`)
- `ERR_PROVIDER_DOWN`: Gangguan Operator / Pemeliharaan Biller (`006`, `008`, `009`, `014`, `023`, `025`, `035`)
- `ERR_OUT_OF_STOCK`: Stok Kuota Habis / Produk Sementara Tutup (`015`, `022`)
- `ERR_INSUFFICIENT_BALANCE`: Saldo Deposit Supplier Habis (`018`)
- `ERR_LIMIT_EXCEEDED`: Limit Harian Tercapai / Duplikasi Ditolak Biller (`026`, `027`, `034`, `036`)
- `ERR_GENERAL`: Kendala Umum / Konfigurasi / Parameter Tidak Valid (`005`, `011`, `019`, `021`, `024`, `031`, `033`, `037`)

### Tabel Mapping Lengkap

| RC Upstream | Status Supplier | Keterangan Supplier | Kode Internal Kita | Retryable? | Tindakan Sistem |
|:---:|:---:|---|:---:|:---:|---|
| **000** | Berhasil | Transaksi Sukses | `SUCCESS` | Tidak | Update transaksi `SUCCESS`, catat SN, selesaikan order |
| **030** | Pending | Transaksi Pending | `ERR_SUSPECT_PENDING` | Ya | Set status `PENDING`, tahan saldo, tunggu webhook atau polling |
| **003** | Gagal | Internal Processing Timeout | `ERR_TIMEOUT` | Ya | Cek status via `CekStatus` atau trigger failover jika belum terproses |
| **005** | Gagal | Kode produk tidak dikenal | `ERR_GENERAL` | Tidak | Gagalkan transaksi & refund saldo user |
| **006** | Gagal | Gangguan koneksi biller | `ERR_PROVIDER_DOWN` | Ya | Failover ke rute cadangan jika ada |
| **007** | Gagal | Nomor pelanggan tidak ditemukan / salah | `ERR_DEST_INVALID` | Tidak | Gagalkan transaksi, beri pesan nomor salah ke user |
| **008** | Gagal | Internal Error | `ERR_PROVIDER_DOWN` | Ya | Failover ke rute cadangan |
| **009** | Gagal | Sistem dalam proses maintanance | `ERR_PROVIDER_DOWN` | Ya | Buka circuit breaker / failover |
| **011** | Gagal | Data tidak ditemukan (API Cek Status) | `ERR_GENERAL` | Tidak | Log investigasi anomali transaksi |
| **012** | Gagal | Nomor pelanggan expired | `ERR_DEST_INVALID` | Tidak | Gagalkan transaksi, nomor telah hangus |
| **013** | Gagal | Nomor diblokir | `ERR_DEST_INVALID` | Tidak | Gagalkan transaksi, nomor diblokir operator |
| **014** | Gagal | Gangguan transaksi pada biller | `ERR_PROVIDER_DOWN` | Ya | Failover ke rute cadangan |
| **015** | Gagal | Stok kuota habis | `ERR_OUT_OF_STOCK` | Ya | Failover ke supplier alternatif |
| **018** | Gagal | Saldo deposit tidak cukup | `ERR_INSUFFICIENT_BALANCE` | Ya | Kirim alert ops bot telegram & failover |
| **019** | Gagal | Harga produk belum di set | `ERR_GENERAL` | Tidak | Log admin perlunya sinkronisasi harga |
| **021** | Gagal | Transaksi telah digagalkan dan saldo di-refund | `ERR_GENERAL` | Tidak | Pastikan transaksi sistem kita di-refund |
| **022** | Gagal | Kode produk sementara ditutup | `ERR_OUT_OF_STOCK` | Ya | Failover ke supplier alternatif |
| **023** | Gagal | Gagal biller (tidak spesifik menjelaskan penyebab) | `ERR_PROVIDER_DOWN` | Ya | Failover ke rute cadangan |
| **024** | Gagal | Tagihan tidak tersedia (paskabayar) | `ERR_GENERAL` | Tidak | Tagihan belum terbit atau nihil |
| **025** | Gagal | Layanan tidak tersedia | `ERR_PROVIDER_DOWN` | Ya | Failover ke rute cadangan |
| **026** | Gagal | Tagihan telah dibayar (paskabayar) | `ERR_LIMIT_EXCEEDED` | Tidak | Tagihan sudah lunas |
| **027** | Gagal | Limit harian telah tercapai | `ERR_LIMIT_EXCEEDED` | Tidak | Limit limit transaksi nomor tujuan tercapai |
| **031** | Gagal | Signature pada request tidak valid | `ERR_GENERAL` | Tidak | Cek kecocokan credential/secret-key |
| **033** | Gagal | Akun diblokir | `ERR_GENERAL` | Tidak | Alert admin terkait status akun supplier |
| **034** | Gagal | Biller menolak perulangan transaksi nomor/denom sama | `ERR_LIMIT_EXCEEDED` | Tidak | Informasikan tunggu interval transaksi |
| **035** | Gagal | Sedang proses cut-off pada biller | `ERR_PROVIDER_DOWN` | Ya | Retry setelah cut-off selesai |
| **036** | Gagal | Akun user melampaui batas maksimal operasional | `ERR_LIMIT_EXCEEDED` | Tidak | Batas maksimal tercapai |
| **037** | Gagal | Nilai Pembayaran tidak valid | `ERR_GENERAL` | Tidak | Validasi nominal transaksi tidak cocok |

---

## 6. Daftar Nomor Pengujian & Unit Test Skenario (Sandbox)

Berdasarkan dokumen QA supplier, nomor-nomor berikut digunakan untuk pengujian otomatis:

### A. Pulsa (Produk `TS10L3` / `TS5L3`)
- `081380312000` (TS10L3): Transaksi **Sukses** langsung (mewakili operator lain).
- `081380312001` (TS10L3): Transaksi **Gagal - Nomor salah** (RC `007`).
- `081380312002` (TS10L3): Transaksi **Gagal - Gangguan Biller** (RC `006`).
- `081380312003` (TS10L3): Transaksi **Pending** (RC `030`).
- `081380312000` (TS5L3): **Pending - Sukses via Callback**.
- `081380312001` (TS5L3): **Pending - Gagal via Callback**.

### B. PLN Token Prepaid (`PLN20`)
- `12000000001`: Query Berhasil & Purchase **Sukses** (SN Token PLN diterima).
- `12000000002`: Query Berhasil & Purchase **Pending - Sukses via Callback**.
- `12000000005`: Query Berhasil & Purchase **Gagal**.
- `12000000000`: Query **Gagal - Nomor tidak ditemukan**.
- `12000000003`: Query **Gagal - Timeout**.
- `12000000004`: Query **Gagal - Gangguan Biller**.

### C. E-Wallet (Produk `DNAP`)
- `081380312000` (Rp 20.000): **SUCCESS**.
- `081380312001` (Rp 20.000): **FAILED**.
- `081380312000` (Rp 5.000): **PENDING - SUCCESS** (Callback ±5 detik).
- `081380312003` (Rp 5.000): **PENDING - FAILED** (Callback ±5 detik).

---

## 7. Desain Komponen & Alur Integrasi di Laravel

```
┌────────────────────────────────────────────────────────┐
│                      Admin Panel                       │
│  - Sync Katalog: AdminProviderManagementService        │
│  - Cek Saldo: checkProviderBalance()                   │
│  - Kelola Mapping RC: provider_error_mappings          │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│                  MLinkGatewayDriver                    │
│   (App\Domain\Ppob\Drivers\MLinkGatewayDriver)         │
│  - inquiry()           : PLNPrepaidQuery, eWalletQuery │
│  - pay()               : TopupRequest, eWalletPayment, │
│                          PLNPrepaidTopup               │
│  - checkStatus()       : CekStatus                     │
│  - getPricelist()      : ProductInquiry                │
│  - checkBalance()      : Balance                       │
│  - verifyWebhookSignature() & handleWebhook()          │
└──────────────────────────┬─────────────────────────────┘
                           │ HTTP POST JSON (HMAC-SHA256)
                           ▼
┌────────────────────────────────────────────────────────┐
│                 M-Link Gateway Server                  │
│               http(s)://endpoint/json                  │
└────────────────────────────────────────────────────────┘
```

1. **Sinkronisasi Katalog Produk**:
   Saat admin menekan tombol sinkronisasi produk upstream atau command scheduler dijalankan, driver mengeksekusi `ProductInquiry`. Data `data.product[]` diparsing ke dalam tabel `provider_products` lengkap dengan modal (`cost_cents`), nama, operator, dan status (`active` / `maintenance`).
2. **Pengecekan Saldo Real-Time**:
   Driver mengeksekusi aksi `Balance` dan mengonversi nilai `balance` (Rupiah) ke integer cents untuk monitoring saldo deposit serta bot operational alerts.
3. **Penyelarasan Error Transaksi**:
   Ketika M-Link Gateway mengembalikan respon gagal atau pending, `ProviderErrorMappingService` mencocokkan field `body.rc` dengan database `provider_error_mappings`. Hasil mapping menentukan apakah sistem melakukan retry/failover (`is_retryable = true`) atau mengembalikan pesan kegagalan ramah pengguna kepada pelanggan.
4. **Webhook Callback**:
   M-Link Gateway mengirimkan callback notifikasi ke endpoint `/api/callback/mlink`. `WebhookController` memverifikasi signature dan memperbarui status transaksi menjadi `SUCCESS` atau `FAILED` beserta pencatatan serial number.
