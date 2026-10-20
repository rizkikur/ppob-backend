# Panduan Operasional & Setup Cloudflare, Nginx, & Rate Limiting

Dokumen ini adalah panduan resmi arsitektur dan operasional untuk mengamankan backend PPOB dari serangan **bot flooding, brute-force login, DDoS, dan kebocoran IP publik VPS (Origin Masking)**.

---

## 🏛️ 1. Arsitektur Pertahanan Berlapis (Defense in Depth)

Semua traffic dari aplikasi mobile, web, dan publik **WAJIB melewati Cloudflare Anycast Network**. Server VPS Backend tidak boleh bisa diakses langsung via IP publiknya.

```
┌─────────────────────────────────┐
│     Client (Mobile App / Web)   │
└────────────────┬────────────────┘
                 │ 1. Request HTTPS
                 ▼
┌────────────────────────────────────────────────────────┐
│ Cloudflare Edge Network (Anycast)                      │
│ - Origin IP Masking (IP asli VPS disembunyikan)        │
│ - WAF Rate Limiting (Maks 15 req/menit per IP)         │
│ - Bot Fight Mode & Managed Challenge (Turnstile)       │
│ - DDoS Mitigation Layer 3/4/7                          │
└────────────────┬───────────────────────────────────────┘
                 │ 2. Proxied HTTPS (Header: CF-Connecting-IP)
                 ▼
┌────────────────────────────────────────────────────────┐
│ Nginx Web Server (VPS Backend)                         │
│ - Drop Direct IP Access (return 444 jika tanpa domain) │
│ - Restore Real Client IP via ngx_http_realip_module   │
│ - Early Rate Limiting (limit_req_zone: 5 req/s burst)  │
│ - SSL Full (Strict) via Cloudflare Origin CA Cert      │
└────────────────┬───────────────────────────────────────┘
                 │ 3. FastCGI Socket (PHP 8.3)
                 ▼
┌────────────────────────────────────────────────────────┐
│ Laravel Engine (Application Layer)                     │
│ - Trust Proxies (Membaca IP asli dari Cloudflare)      │
│ - Dual-Key Throttling (IP + Target Phone Number)       │
│ - Zero Database & Zero Bcrypt CPU Load saat limit      │
└────────────────────────────────────────────────────────┘
```

---

## ☁️ 2. Konfigurasi Dashboard Cloudflare

### A. Pengaturan DNS (Origin IP Masking)
1. Buka menu **DNS ➔ Records**.
2. Pastikan domain API dan Backoffice menggunakan **Proxy Status: Proxied (Orange Cloud ☁️)**:
   - `A` | `api` | `<IP_PUBLIK_VPS>` | **Proxied**
   - `A` | `office` | `<IP_PUBLIK_VPS>` | **Proxied**
3. ⚠️ **Larangan Keras**:
   - Jangan arahkan record `MX` (email) atau `FTP` ke subdomain yang sama dengan API. Gunakan layanan email terpisah (misal Mailgun/Sendgrid via CNAME khusus).
   - Jangan buat record wildcard `*` yang mengarah ke IP asli tanpa proxy.

### B. Konfigurasi SSL/TLS (Full Strict)
1. Buka menu **SSL/TLS ➔ Overview**:
   - Pilih mode **Full (Strict)**.
2. Buka tab **Origin Server**:
   - Klik tombol **Create Certificate**.
   - Pilih Hostnames: `*.domainanda.com`, `domainanda.com`.
   - Validity: **15 years**.
   - Klik **Create**.
   - Salin **Origin Certificate** simpan sebagai file di VPS: `/etc/ssl/cloudflare/origin.crt`.
   - Salin **Private Key** simpan sebagai file di VPS: `/etc/ssl/cloudflare/origin.key` (atur permission `chmod 600`).

### C. Konfigurasi WAF Rate Limiting (Layer 7 Defense)
1. Buka menu **Security ➔ WAF ➔ Rate Limiting Rules**.
2. Klik **Create Rule**:
   - **Rule Name**: `Protect Auth & Login API`
   - **Expression**:
     ```
     (http.request.uri.path contains "/auth/" or http.request.uri.path contains "/office/login")
     ```
   - **Rate limiting criteria**:
     - Requests: **15 requests**
     - Period: **1 minute**
   - **Action**: **Block** (atau **Managed Challenge** untuk bot pintar).
   - **Duration**: **10 minutes**.

### D. Aktifkan Bot Fight Mode
1. Buka menu **Security ➔ Bots**.
2. Aktifkan toggle **Bot Fight Mode**. Cloudflare akan otomatis menantang request dari scraper dan library bot seperti Python-requests, Postman otomatis, curl flood, dll.

---

## 🖥️ 3. Konfigurasi Nginx di Server VPS Backend

### A. Restore Real Client IP (`/etc/nginx/conf.d/cloudflare.conf`)
Agar Nginx dan Laravel tidak menganggap seluruh traffic berasal dari IP Cloudflare, buat file `/etc/nginx/conf.d/cloudflare.conf`:

```nginx
# IP Range Resmi Cloudflare (IPv4)
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;

# IP Range Resmi Cloudflare (IPv6)
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;

# Gunakan header dari Cloudflare
real_ip_header CF-Connecting-IP;
```

### B. Konfigurasi Virtual Host API (`/etc/nginx/sites-available/ppob-api.conf`)
Konfigurasi ini memblokir akses langsung via IP dan memberikan rate limiting dini di memory Nginx:

```nginx
# 1. Zona Memory Rate Limit Nginx (10MB muat ~160.000 IP aktif)
limit_req_zone $binary_remote_addr zone=api_auth_limit:10m rate=5r/s;
limit_req_status 429;

# 2. Blokir Direct IP Access (Jika ada penyerang menembak https://<IP_VPS>/)
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    server_name _;

    ssl_certificate /etc/ssl/cloudflare/origin.crt;
    ssl_certificate_key /etc/ssl/cloudflare/origin.key;

    # Return 444 = Close connection seketika tanpa mengirim response byte apapun
    return 444;
}

# 3. Server Block Resmi Domain API
server {
    listen 443 ssl http2;
    server_name api.domainanda.com;

    root /var/www/laravel-project/public;
    index index.php;

    ssl_certificate /etc/ssl/cloudflare/origin.crt;
    ssl_certificate_key /etc/ssl/cloudflare/origin.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Header Keamanan
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Proteksi Rate Limit Khusus Endpoint Auth
    location ~* ^/(auth|api/v1/auth|office/login) {
        limit_req zone=api_auth_limit burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Kirim header real IP ke PHP-FPM
        fastcgi_param HTTP_CF_CONNECTING_IP $http_cf_connecting_ip;
        fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 🔒 4. Firewall VPS (Origin Shielding dengan UFW)

Agar IP asli VPS tidak bisa diakses dari internet publik pada port web (80/443), pasang firewall agar **hanya menerima koneksi dari IP Cloudflare**:

### Opsi A: Menggunakan Script Otomatis Cloudflare UFW
Simpan script berikut di `/usr/local/bin/update-cloudflare-ufw.sh`:

```bash
#!/bin/bash
# Hapus aturan Cloudflare lama dan update IP terbaru
for ip in $(curl -s https://www.cloudflare.com/ips-v4); do
    sudo ufw allow proto tcp from $ip to any port 80,443 comment 'Cloudflare IPv4'
done
for ip in $(curl -s https://www.cloudflare.com/ips-v6); do
    sudo ufw allow proto tcp from $ip to any port 80,443 comment 'Cloudflare IPv6'
done

# Pastikan port SSH tetap buka
sudo ufw allow 22/tcp comment 'SSH'

# Aktifkan UFW
sudo ufw enable
sudo ufw reload
```
Jalankan script tersebut via cron bulanan:
```bash
sudo chmod +x /usr/local/bin/update-cloudflare-ufw.sh
sudo /usr/local/bin/update-cloudflare-ufw.sh
```

---

## ⚡ 5. Implementasi di Sisi Aplikasi Laravel

Aplikasi backend kita sudah terkonfigurasi untuk skenario ini:

### A. Trusted Proxies (`bootstrap/app.php`)
```php
$middleware->trustProxies(at: '*');
```
*Efek*: Laravel otomatis membaca IP asli dari header `CF-Connecting-IP` atau `X-Forwarded-For`. Method `$request->ip()` akan mengembalikan IP smartphone/laptop pengguna asli, bukan IP server Cloudflare.

### B. Dual-Key Throttling (`app/Providers/AppServiceProvider.php`)
Rate limiter menggunakan **Redis In-Memory** dengan dua kunci pengaman:

1. **`api-auth-login`** (Login, Registrasi, Verifikasi OTP):
   - **Key 1 (IP)**: Maksimal **10 request/menit**.
   - **Key 2 (Phone Number)**: Maksimal **5 request/5 menit** per nomor HP target.
   - *Tujuan*: Mencegah serangan bot terdistribusi (*distributed brute-force*) yang menggunakan 1.000 proxy IP berbeda untuk menyerang 1 nomor akun yang sama.
   - Response: `429 TOO_MANY_ATTEMPTS` (0 database query, 0 Bcrypt hash computation).

2. **`api-auth-otp`** (Request Kirim Kode OTP):
   - **Key 1 (IP)**: Maksimal **5 request/menit**.
   - **Key 2 (Phone Number)**: Maksimal **3 request/10 menit** per nomor target.
   - *Tujuan*: Mencegah pembengkakan tagihan vendor SMS/WhatsApp Gateway akibat dibom bot.
   - Response: `429 OTP_RATE_LIMIT_EXCEEDED`.

---

## 🧪 6. Checklist Pengujian & Verifikasi

Setelah server dideploy ke produksi, jalankan checklist verifikasi berikut:

| No | Tes Pengujian | Command / Aksi | Hasil yang Diharapkan |
| :--- | :--- | :--- | :--- |
| 1 | **Verifikasi Cloudflare Proxy** | `curl -I https://api.domainanda.com` | Muncul header `cf-ray: ...` dan `server: cloudflare`. IP publik VPS tidak terlihat. |
| 2 | **Tes Direct IP Bypass** | `curl -k https://<IP_PUBLIK_VPS>/` | Menghasilkan `Empty reply from server` (HTTP 444 Connection Dropped). |
| 3 | **Tes Real IP di Laravel** | Hit endpoint `GET /up` atau cek log `storage/logs/laravel.log` | IP yang tercatat adalah IP ISP/koneksi internet Anda, bukan IP 172.64.x.x Cloudflare. |
| 4 | **Tes Bom Login (Rate Limiter)** | Kirim POST `/auth/login` 6x berturut-turut untuk nomor yang sama | Request ke-6 langsung menerima HTTP 429 `TOO_MANY_ATTEMPTS` tanpa jeda waktu CPU. |
| 5 | **Tes Bom OTP (Rate Limiter)** | Kirim POST `/auth/otp` 4x berturut-turut untuk nomor yang sama | Request ke-4 langsung menerima HTTP 429 `OTP_RATE_LIMIT_EXCEEDED`. |
