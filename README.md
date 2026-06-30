# B2 Dev — Aplikasi Manajemen Warga

Aplikasi web berbasis **Laravel 13** dan **Livewire 4** untuk mengelola data warga (NIK, nama, alamat, pas foto, dan dokumen). Dibangun di atas Laravel Livewire Starter Kit dengan autentikasi modern (passkey + 2FA via Fortify + OTP), penyimpanan file ke storage S3-compatible (Backblaze B2 / Cloudflare R2 / AWS S3), dan integrasi **WhatsApp Gateway** untuk pengiriman pesan dan kode OTP.

## Fitur

- **Manajemen Warga** — pencatatan data warga beserta unggahan pas foto dan dokumen.
- **Autentikasi lengkap** — login, registrasi, reset password, verifikasi email.
- **Passkey & Two-Factor Authentication** — keamanan akun via `laravel/fortify` dan `@laravel/passkeys`.
- **OTP Login** — verifikasi dua langkah setelah login berhasil menggunakan kode OTP 6 digit, dikirim via WhatsApp atau email. Mendukung kirim ulang, pembatasan percobaan, dan kedaluwarsa otomatis.
- **Penyimpanan fleksibel** — disk `local`, `public`, `s3` (B2), dan `r2` (Cloudflare R2).
- **WhatsApp Gateway** — kirim pesan WhatsApp melalui REST API gateway dengan dukungan Basic Auth, `X-Device-Id`, debug konfigurasi `.env`, dan tampilan detail error pengiriman.
- **SMTP Email Dashboard** — kirim email SMTP Brevo dari dashboard dengan debug konfigurasi `.env` dan status pengiriman via toaster.
- **UI modern** — Flux UI + TailwindCSS 4, halaman pengaturan (profil, keamanan, tampilan).

## Tech Stack

| Kategori        | Teknologi                                             |
| --------------- | ----------------------------------------------------- |
| Backend         | Laravel 13.x, PHP 8.3                                 |
| Frontend        | Livewire 4.x, Flux UI, TailwindCSS 4, Vite, Alpine.js |
| Autentikasi     | Laravel Fortify, Passkeys, 2FA, OTP Login             |
| Queue           | Laravel Queue (driver `database` / `sync`), queue `otp` |
| Integrasi       | WhatsApp Gateway REST API (Go)                        |
| Penyimpanan     | S3-compatible (Backblaze B2, Cloudflare R2, AWS S3)   |
| Database        | SQLite (dev), PostgreSQL/MySQL (prod)                 |
| Testing         | PHPUnit 12.x                                          |
| Kualitas Kode   | Laravel Pint, Larastan (PHPStan)                      |

## Persyaratan

- PHP 8.3+
- Composer
- Node.js & npm
- Layanan WhatsApp Gateway aktif bila ingin memakai fitur kirim pesan WhatsApp / OTP via WhatsApp
- Kredensial SMTP Brevo aktif bila ingin memakai fitur kirim email dashboard / OTP via email
- Queue worker aktif (`php artisan queue:work --queue=otp,default`) agar pengiriman OTP diproses

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Atau gunakan skrip setup bawaan:

```bash
composer run setup
```

## Menjalankan Aplikasi

Menjalankan server pengembangan dengan Vite + hot reload:

```bash
composer run dev
# atau
npm run dev
```

Build aset untuk produksi:

```bash
npm run build
```

Menjalankan queue worker (wajib untuk pengiriman OTP):

```bash
php artisan queue:work --queue=otp,default
```

## Struktur Direktori

```
app/
├── Actions/       # Logika use-case (mis. Fortify, Livewire)
├── Http/
│   ├── Middleware/  # Middleware (mis. EnsureLoginOtpVerified)
│   └── Responses/   # Custom response (mis. LoginOtpLoginResponse)
├── Jobs/
│   └── SendOtpJob.php   # Job pengiriman OTP via WhatsApp / email
├── Livewire/      # Komponen Livewire
├── Models/
│   ├── LoginOtpChallenge.php  # Model tantangan OTP
│   └── User.php               # Model pengguna
├── Services/
│   └── LoginOtpService.php    # Logika OTP (issue, verify, resend)
routes/
├── web.php        # Rute web (memetakan langsung ke komponen Livewire)
└── settings.php   # Rute halaman pengaturan
database/
├── migrations/    # Skema database (termasuk login_otp_challenges)
├── factories/     # Factory untuk testing
└── seeders/
resources/views/
├── pages/warga/    # Halaman manajemen warga
├── pages/auth/     # Halaman autentikasi (termasuk otp-challenge)
├── pages/email/    # Halaman kirim email SMTP
└── pages/whatsapp/ # Halaman kirim pesan WhatsApp
```

## Konfigurasi Penyimpanan

Disk penyimpanan didefinisikan di `config/filesystems.php`. Pilih disk default lewat `FILESYSTEM_DISK` di `.env`.

- **Backblaze B2** — set variabel `B2_*` dan gunakan disk `s3`.
- **Cloudflare R2** — set variabel `R2_*` dan gunakan disk `r2`.
- **AWS S3** — set variabel `AWS_*`.

Akses file publik: `Storage::disk('b2')->url($path)`
Akses file privat: `Storage::disk('b2')->temporaryUrl($path, $expiration)`

Lihat panduan lengkap:
- [`panduan-backblaze-b2-laravel-livewire.md`](panduan-backblaze-b2-laravel-livewire.md)
- [`panduan-r2-cloudflare-laravel-livewire.md`](panduan-r2-cloudflare-laravel-livewire.md)
- [`panduan-smtp-brevo-laravel.md`](panduan-smtp-brevo-laravel.md)

## Konfigurasi SMTP Email

Fitur kirim email dashboard memakai mailer SMTP Laravel. Konfigurasi dibaca dari `config/mail.php` dan `.env`.

Contoh variabel `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-login@smtp-brevo.com
MAIL_PASSWORD=your-smtp-key
MAIL_EHLO_DOMAIN=localhost
MAIL_FROM_ADDRESS=no-reply@contohdomain.com
MAIL_FROM_NAME="B2 Dev"
```

Keterangan singkat:
- `MAIL_MAILER` — harus `smtp` agar halaman dashboard bisa kirim email.
- `MAIL_SCHEME` — skema koneksi SMTP, umumnya `tls` untuk Brevo.
- `MAIL_HOST` — host relay SMTP.
- `MAIL_PORT` — port SMTP relay.
- `MAIL_USERNAME` — username SMTP Brevo.
- `MAIL_PASSWORD` — SMTP key Brevo.
- `MAIL_EHLO_DOMAIN` — domain EHLO handshake SMTP.
- `MAIL_FROM_ADDRESS` — alamat pengirim default.
- `MAIL_FROM_NAME` — nama pengirim default.

Endpoint aplikasi:
- Halaman kirim email: `/email/send-message`

Catatan operasional:
- Sender email harus sudah diverifikasi di Brevo.
- Setelah ubah `.env`, jalankan `php artisan config:clear`.
- Halaman kirim email menampilkan debug konfigurasi `.env` dan toaster status pengiriman.

Lihat panduan lengkap:
- [`panduan-smtp-brevo-laravel.md`](panduan-smtp-brevo-laravel.md)

## Konfigurasi WhatsApp Gateway

Fitur kirim pesan WhatsApp memakai gateway REST terpisah. Konfigurasi aplikasi Laravel dibaca dari `config/whatsapp.php` dan `.env`.

Contoh variabel `.env`:

```env
WHATSAPP_AUTH=admin:example
WHATSAPP_IP=127.0.0.1
WHATSAPP_PORT=3000
WHATSAPP_DEVICE_ID=628123456789@s.whatsapp.net
WHATSAPP_ACTION=stop
WHATSAPP_DURATION=86400
```

Keterangan singkat:
- `WHATSAPP_AUTH` — pasangan `username:password` untuk Basic Auth gateway.
- `WHATSAPP_IP` — host atau IP service gateway.
- `WHATSAPP_PORT` — port service gateway.
- `WHATSAPP_DEVICE_ID` — device id WhatsApp aktif, dikirim lewat header `X-Device-Id`.
- `WHATSAPP_ACTION` — action konteks pesan.
- `WHATSAPP_DURATION` — durasi dalam detik. Nilai harus integer, mis. `86400`.

Endpoint aplikasi:
- Halaman kirim pesan: `/whatsapp/send-message`

Catatan operasional:
- Gateway harus aktif dalam mode REST.
- Minimal satu device WhatsApp harus sudah terhubung.
- Halaman kirim pesan menampilkan debug konfigurasi `.env` dan detail error dari gateway bila pengiriman gagal.

Lihat dokumentasi lengkap gateway:
- [`WHATSAPPGATEWAY.md`](WHATSAPPGATEWAY.md)

## Konfigurasi OTP Login

Fitur OTP Login mengirimkan kode 6 digit setelah login berhasil. OTP dikirim via WhatsApp (prioritas) atau email, diproses secara asinkron melalui Laravel Queue.

### Variabel `.env` tambahan

```env
# Pilihan channel OTP per-user disimpan di kolom `otp_channel_preference` (nilai: 'whatsapp' atau 'email')
# Konfigurasi WhatsApp Gateway (lihat bagian Konfigurasi WhatsApp Gateway)
WHATSAPP_AUTH=admin:example
WHATSAPP_IP=127.0.0.1
WHATSAPP_PORT=3000
WHATSAPP_DEVICE_ID=628123456789@s.whatsapp.net

# Driver queue (gunakan 'database' untuk produksi, 'sync' hanya untuk dev/test)
QUEUE_CONNECTION=database
```

### Perilaku OTP

| Parameter          | Nilai default |
| ------------------ | ------------- |
| Panjang kode       | 6 digit       |
| Masa berlaku       | 5 menit       |
| Maks. percobaan    | 5 kali        |
| Maks. kirim ulang  | 3 kali        |
| Cooldown kirim ulang | 60 detik    |

### Menjalankan Queue Worker

Job OTP di-dispatch ke queue bernama **`otp`**. Worker harus dijalankan dengan opsi `--queue=otp,default` agar job diproses:

```bash
php artisan queue:work --queue=otp,default
```

> **Catatan:** Menjalankan `php artisan queue:work` tanpa `--queue` hanya memproses queue `default`, sehingga OTP tidak akan pernah terkirim dan halaman akan terus menampilkan status *mengirim*.

### Endpoint

- Halaman verifikasi OTP: `/auth/otp-challenge`

### Alur OTP

1. Pengguna login → `LoginOtpLoginResponse` mengarahkan ke `/auth/otp-challenge`.
2. `LoginOtpService::issueChallenge()` membuat record `LoginOtpChallenge` dan men-dispatch `SendOtpJob` ke queue `otp`.
3. Queue worker mengirim OTP via WhatsApp atau email, lalu memperbarui `sent_status` menjadi `sent` / `failed`.
4. Halaman melakukan polling setiap 2 detik untuk memperbarui status pengiriman.
5. Pengguna memasukkan kode → `LoginOtpService::verifyChallenge()` memvalidasi dan menandai sesi sebagai terverifikasi.

## Artisan Commands

### `livewire:clear-tmp`

Menghapus file temporary upload Livewire secara manual.

```bash
# Hapus file temporary yang lebih tua dari 24 jam (default)
php artisan livewire:clear-tmp

# Hapus file temporary yang lebih tua dari 6 jam
php artisan livewire:clear-tmp --hours=6

# Hapus semua file temporary tanpa cek umur
php artisan livewire:clear-tmp --all

# Dry run — tampilkan file yang akan dihapus tanpa menghapusnya
php artisan livewire:clear-tmp --dry-run
```

## Pengujian & Kualitas Kode

```bash
# Jalankan semua test
php artisan test --compact

# Jalankan test tertentu
php artisan test --compact --filter=testName

# Format kode
vendor/bin/pint --dirty

# Analisis statis
php artisan types:check
```

## Lisensi

Dirilis di bawah lisensi [MIT](https://opensource.org/licenses/MIT).
