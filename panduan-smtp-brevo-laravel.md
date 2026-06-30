# Panduan Konfigurasi SMTP Brevo di Laravel 13

Dokumen ini menjelaskan cara konfigurasi SMTP mail memakai **Brevo** pada aplikasi **Laravel 13**. Format dokumen mengikuti pola referensi **MCP Context7**: ada sumber referensi, topik inti, langkah implementasi, contoh konfigurasi, verifikasi, dan troubleshooting.

---

## Referensi Teknis

### Context7

- `/websites/developers_brevo`
- `/laravel/docs/__branch__13.x`

### Topik utama dari referensi

- host relay Brevo: `smtp-relay.brevo.com`
- port SMTP relay Brevo: `587`
- autentikasi SMTP memakai login + SMTP key
- konfigurasi mailer SMTP Laravel via `.env`
- clear cache konfigurasi Laravel setelah ubah `.env`
- verifikasi pengiriman email lewat route, tinker, atau fitur aplikasi

---

## Ringkas

Brevo menyediakan SMTP relay untuk kirim email transaksional dari aplikasi Laravel. Pada project ini, mailer SMTP sudah tersedia di `config/mail.php`, jadi konfigurasi utama cukup dilakukan lewat file `.env` dan identitas pengirim di dashboard Brevo.

File penting di project ini:

- `config/mail.php:17`
- `config/mail.php:40`
- `config/mail.php:113`

Nilai contoh yang dipakai di panduan ini:

- **SMTP Server**: `smtp-relay.brevo.com`
- **Port**: `587`
- **Login**: `your-smtp-login@smtp-brevo.com`
- **SMTP Key**: `your-smtp-key`

> **Penting**
>
> `SMTP Key` adalah kredensial rahasia. Jangan commit nilai asli ke repository. Simpan hanya di `.env`, secret manager, atau environment server.

---

## 1. Prasyarat

Sebelum mulai, pastikan hal berikut sudah siap:

1. Akun Brevo aktif.
2. Fitur **Transactional Email / SMTP** aktif di akun Brevo.
3. Minimal satu **sender identity** atau domain pengirim sudah diverifikasi di Brevo.
4. Aplikasi Laravel sudah punya file `.env`.
5. Konfigurasi mail bawaan Laravel masih memakai mailer `smtp` pada `config/mail.php`.

---

## 2. Siapkan sender di Brevo

Sebelum email bisa terkirim dengan baik, siapkan identitas pengirim dulu di dashboard Brevo.

### 2.1 Tambah sender email

1. Login ke dashboard Brevo.
2. Buka menu **Transactional** atau **Senders / Sender Domains**.
3. Tambah alamat pengirim, misalnya:
   - `MAIL_FROM_ADDRESS=no-reply@contohdomain.com`
   - `MAIL_FROM_NAME="B2 Dev"`
4. Lakukan verifikasi email atau domain sesuai instruksi Brevo.

### 2.2 Rekomendasi domain

Untuk produksi, pakai domain sendiri dan lengkapi DNS berikut:

- SPF
- DKIM
- DMARC

Tujuan:

- tingkat kirim lebih baik
- email tidak mudah masuk spam
- reputasi domain lebih stabil

---

## 3. Pahami mapping konfigurasi Laravel

Project ini sudah punya definisi mailer SMTP di `config/mail.php:40`.

Mapping utamanya:

- `MAIL_MAILER` → mailer default di `config/mail.php:17`
- `MAIL_HOST` → `config/mail.php:44`
- `MAIL_PORT` → `config/mail.php:45`
- `MAIL_USERNAME` → `config/mail.php:46`
- `MAIL_PASSWORD` → `config/mail.php:47`
- `MAIL_EHLO_DOMAIN` → `config/mail.php:49`
- `MAIL_FROM_ADDRESS` → `config/mail.php:114`
- `MAIL_FROM_NAME` → `config/mail.php:115`

Karena itu, integrasi Brevo tidak perlu ubah struktur `config/mail.php` bila cukup memakai 1 SMTP account.

---

## 4. Isi konfigurasi `.env`

Tambahkan atau ubah variabel berikut di file `.env`.

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-login@smtp-brevo.com
MAIL_PASSWORD=your-smtp-key
MAIL_EHLO_DOMAIN=localhost
MAIL_FROM_ADDRESS=no-reply@contohdomain.com
MAIL_FROM_NAME="B2 Dev"
```

### Penjelasan tiap variabel

- `MAIL_MAILER=smtp`  
  Pakai driver SMTP bawaan Laravel.

- `MAIL_HOST=smtp-relay.brevo.com`  
  Host relay resmi Brevo.

- `MAIL_PORT=587`  
  Port standar SMTP submission dengan STARTTLS.

- `MAIL_USERNAME=your-smtp-login@smtp-brevo.com`  
  Username SMTP dari Brevo.

- `MAIL_PASSWORD=your-smtp-key`  
  SMTP key dari Brevo.

- `MAIL_EHLO_DOMAIN=localhost`  
  Domain EHLO untuk handshake SMTP. Untuk production, lebih baik isi dengan domain app, misalnya `app.contohdomain.com`.

- `MAIL_FROM_ADDRESS`  
  Alamat pengirim default. Harus cocok dengan sender/domain yang sudah diverifikasi di Brevo.

- `MAIL_FROM_NAME`  
  Nama pengirim yang tampil di inbox penerima.

### Catatan soal enkripsi

Pada `config/mail.php` project ini, konfigurasi SMTP memakai field `scheme` dan tidak memakai `MAIL_ENCRYPTION` lama. Untuk Brevo di port `587`, konfigurasi di atas sudah cukup umum dipakai.

Jika environment tertentu mewajibkan skema eksplisit, Anda bisa tambahkan:

```env
MAIL_SCHEME=tls
```

Jika tidak dibutuhkan, biarkan kosong.

---

## 5. Contoh konfigurasi penuh `.env`

Berikut contoh blok konfigurasi lengkap yang bisa langsung dijadikan acuan.

```env
APP_NAME="B2 Dev"
APP_URL=http://localhost

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-login@smtp-brevo.com
MAIL_PASSWORD=your-smtp-key
MAIL_SCHEME=tls
MAIL_EHLO_DOMAIN=localhost
MAIL_FROM_ADDRESS=no-reply@contohdomain.com
MAIL_FROM_NAME="B2 Dev"
```

Untuk server production, disarankan ubah:

```env
APP_URL=https://app.contohdomain.com
MAIL_EHLO_DOMAIN=app.contohdomain.com
MAIL_FROM_ADDRESS=no-reply@contohdomain.com
```

---

## 6. Clear cache konfigurasi Laravel

Setelah mengubah `.env`, bersihkan cache konfigurasi agar nilai baru terbaca.

Jalankan perintah berikut di PowerShell:

```powershell
php artisan config:clear
php artisan cache:clear
```

Bila aplikasi memakai config cache di server, jalankan juga:

```powershell
php artisan config:cache
```

Urutan aman saat deploy:

1. update `.env`
2. `php artisan config:clear`
3. `php artisan cache:clear`
4. `php artisan config:cache`

---

## 7. Verifikasi konfigurasi dari aplikasi

Setelah konfigurasi aktif, lakukan pengujian kirim email.

### 7.1 Verifikasi cepat dengan Tinker

Jalankan:

```powershell
php artisan tinker --execute 'Illuminate\Support\Facades\Mail::raw("Tes SMTP Brevo dari Laravel.", function ($message) { $message->to("penerima@contohdomain.com")->subject("Tes SMTP Brevo"); });'
```

Hasil yang diharapkan:

- tidak ada error autentikasi
- email masuk ke inbox atau spam folder penerima

### 7.2 Verifikasi lewat route sementara

Jika ingin uji lewat browser, buat route sementara di `routes/web.php`:

```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/test-mail-brevo', function () {
    Mail::raw('Tes SMTP Brevo dari route Laravel.', function ($message) {
        $message->to('penerima@contohdomain.com')
            ->subject('Tes SMTP Brevo');
    });

    return 'Email test dikirim.';
});
```

Lalu akses:

```text
/test-mail-brevo
```

Setelah tes selesai, hapus route sementara itu.

### 7.3 Verifikasi lewat fitur aplikasi

Cara paling baik untuk final check:

1. pakai fitur asli aplikasi yang memang mengirim email
2. contoh: reset password, verifikasi email, notifikasi akun
3. cek inbox penerima dan log aplikasi

---

## 8. Troubleshooting

### 8.1 Error autentikasi SMTP

Gejala umum:

- `535 Authentication failed`
- `Expected response code "235" but got code "535"`

Penyebab biasa:

- `MAIL_USERNAME` salah
- `MAIL_PASSWORD` salah
- SMTP key sudah di-rotate atau tidak aktif
- copy-paste key mengandung spasi tersembunyi

Checklist:

1. pastikan username sama persis: `your-smtp-login@smtp-brevo.com`
2. pastikan password sama persis: `your-smtp-key`
3. generate ulang SMTP key dari dashboard Brevo bila perlu
4. jalankan ulang `php artisan config:clear`

### 8.2 Email tidak terkirim tapi tidak error

Penyebab biasa:

- sender belum diverifikasi
- domain pengirim belum valid
- email masuk spam
- queue aktif tapi worker belum jalan

Checklist:

1. cek `MAIL_FROM_ADDRESS`
2. pastikan sender/domain sudah verified di Brevo
3. cek folder spam
4. jika mail dikirim via queue, jalankan worker:

```powershell
php artisan queue:work
```

### 8.3 Timeout atau gagal konek ke SMTP host

Gejala umum:

- connection timed out
- connection refused
- stream socket error

Penyebab biasa:

- firewall server blok port `587`
- hosting blok koneksi SMTP keluar
- DNS host bermasalah

Checklist:

1. pastikan server boleh koneksi keluar ke `smtp-relay.brevo.com`
2. pastikan port `587` terbuka
3. coba dari environment lain bila perlu

### 8.4 Nama pengirim tampil benar, tapi alamat pengirim berubah

Penyebab biasa:

- Brevo memaksa sender sesuai identitas verified
- `MAIL_FROM_ADDRESS` tidak cocok dengan sender yang diizinkan

Fix:

- samakan `MAIL_FROM_ADDRESS` dengan sender verified di Brevo
- bila pakai domain sendiri, verifikasi domain penuh

---

## 9. Rekomendasi produksi

Untuk environment production, pakai praktik berikut:

1. Jangan simpan SMTP key asli di dokumentasi publik.
2. Simpan kredensial di `.env` server atau secret manager.
3. Pakai alamat `from` dari domain sendiri.
4. Lengkapi SPF, DKIM, dan DMARC.
5. Gunakan queue untuk email volume sedang atau besar.
6. Monitor bounce, block, dan delivery log di dashboard Brevo.
7. Rotate SMTP key secara berkala.

---

## 10. Contoh checklist implementasi cepat

Gunakan checklist ini bila ingin setup cepat:

1. Verifikasi sender/domain di Brevo.
2. Isi `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-login@smtp-brevo.com
MAIL_PASSWORD=your-smtp-key
MAIL_SCHEME=tls
MAIL_EHLO_DOMAIN=localhost
MAIL_FROM_ADDRESS=no-reply@contohdomain.com
MAIL_FROM_NAME="B2 Dev"
```

3. Clear cache:

```powershell
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

4. Kirim email test:

```powershell
php artisan tinker --execute 'Illuminate\Support\Facades\Mail::raw("Tes SMTP Brevo dari Laravel.", function ($message) { $message->to("penerima@contohdomain.com")->subject("Tes SMTP Brevo"); });'
```

5. Cek inbox dan dashboard log Brevo.

---

## 11. Ringkasan akhir

Konfigurasi SMTP Brevo di project ini cukup fokus pada tiga hal:

1. sender Brevo harus valid
2. variabel `.env` harus benar
3. cache konfigurasi Laravel harus dibersihkan setelah perubahan

Dengan konfigurasi contoh berikut, Laravel dapat mengirim email lewat Brevo:

- host: `smtp-relay.brevo.com`
- port: `587`
- username: `your-smtp-login@smtp-brevo.com`
- password: `your-smtp-key`

Jika ingin dipakai untuk production, lanjutkan dengan verifikasi domain, DNS email, queue mail, dan monitoring deliverability di dashboard Brevo.
