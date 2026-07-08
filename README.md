# B2 Dev — Aplikasi Manajemen Warga

Aplikasi web berbasis **Laravel 13** dan **Livewire 4** untuk mengelola data warga (NIK, nama, alamat, pas foto, dan dokumen). Dibangun di atas Laravel Livewire Starter Kit dengan autentikasi modern (passkey + 2FA via Fortify + OTP), penyimpanan file ke storage S3-compatible (Backblaze B2 / Cloudflare R2 / AWS S3), dan integrasi **WhatsApp Gateway** untuk pengiriman pesan dan kode OTP.

## Fitur

- **Manajemen Warga** — pencatatan data warga beserta unggahan pas foto dan dokumen.
- **Autentikasi lengkap** — login, registrasi, reset password, verifikasi email.
- **OAuth Google Login** — login alternatif via akun Google menggunakan `laravel/socialite`, tetap melewati alur OTP dua langkah.
- **Passkey & Two-Factor Authentication** — keamanan akun via `laravel/fortify` dan `@laravel/passkeys`.
- **OTP Login** — verifikasi dua langkah setelah login berhasil menggunakan kode OTP 6 digit, dikirim via WhatsApp atau email. Mendukung kirim ulang, pembatasan percobaan, dan kedaluwarsa otomatis.
- **Penyimpanan fleksibel** — disk `local`, `public`, `s3` (B2), dan `r2` (Cloudflare R2).
- **WhatsApp Gateway** — kirim pesan WhatsApp melalui REST API gateway dengan dukungan Basic Auth, `X-Device-Id`, debug konfigurasi `.env`, dan tampilan detail error pengiriman.
- **SMTP Email Dashboard** — kirim email SMTP Brevo dari dashboard dengan debug konfigurasi `.env` dan status pengiriman via toaster.
- **Generate QR Code** — buat QR code dari input teks, preview hasil, dan unduh file PNG/JPG dari temporary storage lokal.
- **Cetak Faktur / Invoice PDF** — buat faktur dengan item dinamis, total otomatis, terbilang rupiah, pilihan ukuran kertas, upload logo, preview PDF, riwayat faktur, dan penyimpanan file ke Backblaze B2.
- **AI Chatbot** — percakapan AI multi-provider (OpenAI, Anthropic, Gemini, 9Router, dll.) dengan dukungan lampiran file, percakapan berkelanjutan, dan pemilihan model dinamis via `laravel/ai`.
- **Markdown Reader (Docs)** — viewer dokumen Markdown dengan sidebar + preview, upload file `.md`, sync otomatis dari root project dan folder `docs/`, rendering GFM via `league/commonmark`, dan download dokumen.
- **UI modern** — Flux UI + TailwindCSS 4, halaman pengaturan (profil, keamanan, tampilan).


## Tech Stack

| Kategori        | Teknologi                                             |
| --------------- | ----------------------------------------------------- |
| Backend         | Laravel 13.x, PHP 8.3                                 |
| Frontend        | Livewire 4.x, Flux UI, TailwindCSS 4, Vite, Alpine.js |
| Autentikasi     | Laravel Fortify, Passkeys, 2FA, OTP Login, OAuth Google (`laravel/socialite`) |
| Queue           | Laravel Queue (driver `database` / `sync`), queue `otp` |
| Integrasi       | WhatsApp Gateway REST API (Go), QR Code Generator, Faktur PDF Generator, Laravel AI (chatbot), league/commonmark (Markdown) |
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
- OAuth client Google (Client ID & Secret) bila ingin memakai fitur login Google

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
├── Ai/
│   ├── Agents/    # AI agent (ChatAgent)
│   ├── Gateways/  # Custom AI gateway (NineRouterGateway)
│   └── Providers/ # Custom AI provider (NineRouterProvider)
├── Http/
│   ├── Middleware/  # Middleware (mis. EnsureLoginOtpVerified)
│   └── Responses/   # Custom response (mis. LoginOtpLoginResponse)
├── Jobs/
│   └── SendOtpJob.php   # Job pengiriman OTP via WhatsApp / email
├── Livewire/      # Komponen Livewire
├── Models/
│   ├── Document.php               # Model dokumen Markdown (Docs)
│   ├── LoginOtpChallenge.php  # Model tantangan OTP
│   └── User.php               # Model pengguna
├── Services/
│   ├── LoginOtpService.php         # Logika OTP (issue, verify, resend)
│   ├── MarkdownRendererService.php # Render Markdown ke HTML (league/commonmark)
│   └── QrCodeTemporaryFileService.php # Generator + file temporary QR code
routes/
├── web.php        # Rute web (memetakan langsung ke komponen Livewire)
├── settings.php   # Rute halaman pengaturan
└── console.php    # Command artisan + schedule cleanup temporary
database/
├── migrations/    # Skema database (termasuk login_otp_challenges)
├── factories/     # Factory untuk testing
└── seeders/
resources/views/
├── pages/warga/      # Halaman manajemen warga
├── pages/auth/       # Halaman autentikasi (termasuk otp-challenge)
├── pages/email/      # Halaman kirim email SMTP
├── pages/qr-code/    # Halaman generate QR code
├── pages/whatsapp/   # Halaman kirim pesan WhatsApp
├── pages/chat/       # Halaman chatbot AI
└── pages/docs/       # Halaman Markdown Reader (Docs)
```

## OAuth Google Login

Fitur login Google memakai `laravel/socialite` sebagai klien OAuth. Setelah login Google berhasil, user tetap melewati alur OTP dua langkah (`LoginOtpService::issueChallenge()`) lalu diarahkan ke `/auth/otp-challenge` — konsisten dengan login email/password.

### Variabel `.env`

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

### Konfigurasi `config/services.php`

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

Verifikasi nilai sudah terbaca:

```bash
php artisan config:show services.google
```

### Skema Database

Migrasi menambahkan kolom `google_id` (unique, nullable) dan `avatar` ke tabel `users`:

```bash
php artisan migrate
```

### Rute

Rute berada di luar middleware `auth` (diakses sebelum login):

| Method | Path                       | Name             | Deskripsi                        |
| ------ | -------------------------- | ---------------- | -------------------------------- |
| GET    | `/auth/google/redirect`    | `google.redirect` | Arahkan user ke halaman izin Google |
| GET    | `/auth/google/callback`    | `google.callback` | Tangani callback dari Google         |

Verifikasi rute:

```bash
php artisan route:list --path=auth/google
```

### Alur Login Google

1. User klik "Masuk dengan Google" di halaman login → redirect ke Google consent.
2. Google callback → `GoogleController::callback()` mengambil profil Google.
3. `findOrCreateUser()` mencari user berdasarkan `google_id` atau `email`; bila belum ada, dibuatkan user baru tanpa password dengan `email_verified_at = now()`.
4. `Auth::login()` lalu `LoginOtpService::issueChallenge()` men-dispatch OTP ke queue `otp`.
5. User diarahkan ke `/auth/otp-challenge` untuk memasukkan kode OTP.

### Catatan Keamanan

- `google_id` dipakai sebagai kunci utama; email hanya fallback untuk menautkan akun existing.
- Google `email_verified` wajib dicek sebelum menautkan `google_id` ke akun existing berdasarkan email agar tidak terjadi account takeover.
- User OAuth tidak punya password (kolom `password` null); login manual via form tidak mungkin.
- State parameter CSRF otomatis disertakan oleh Socialite — jangan dimatikan.
- Alur OTP dua langkah tetap berlaku; jalur Google tidak boleh membypass middleware `login-otp`.

Lihat panduan lengkap:
- [`OAUTHLOGIN.md`](OAUTHLOGIN.md)

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

## Generate QR Code

Fitur generate QR code tersedia dari sidebar dashboard. Pengguna memasukkan teks, lalu aplikasi membuat preview QR code dan menyediakan unduhan siap pakai dalam format PNG dan JPG.

Endpoint aplikasi:
- Halaman generate QR code: `/qr-code/generate`
- Download PNG/JPG temporary: `/qr-code/download/{filename}`

Penyimpanan temporary QR code:
- File disimpan sementara pada disk `local` di direktori `qr-codes-tmp`.
- File tidak disimpan permanen.
- File dapat dihapus manual dari halaman melalui tombol **Hapus Temporary**.
- File lama ikut dibersihkan oleh command `php artisan livewire:clear-tmp`.
- Schedule harian menjalankan cleanup otomatis untuk file temporary.

## Cetak Faktur / Invoice PDF

Fitur cetak faktur tersedia dari sidebar dashboard. Pengguna dapat mengisi nama pelanggan, daftar item dinamis, qty, harga satuan, memo, logo opsional, dan ukuran kertas. Total dihitung otomatis, nilai terbilang rupiah dapat digenerate otomatis, lalu hasil PDF disimpan ke Backblaze B2 dan ditampilkan kembali sebagai preview.

Endpoint aplikasi:
- Halaman generate faktur: `/faktur/generate`

Kemampuan utama:
- Tambah/hapus item tagihan secara dinamis.
- Hitung subtotal per item dan grand total otomatis.
- Generate terbilang rupiah otomatis dari total nominal.
- Pilihan ukuran PDF: `a4`, `half_a4`, dan `third_a4`.
- Upload logo opsional untuk ditampilkan pada faktur.
- Preview PDF terakhir lewat signed temporary URL.
- Riwayat faktur tersimpan dan bisa diunduh ulang atau dihapus.
- File PDF dan logo disimpan pada disk `b2`.

## Markdown Reader (Docs)

Fitur Docs menyediakan viewer dokumen Markdown dengan layout sidebar + preview. Dokumen bisa berasal dari tiga sumber: file `.md` di root project, folder `docs/`, atau upload manual dari komputer.

Endpoint aplikasi:
- Halaman Docs: `/docs`

Kemampuan utama:
- Sidebar daftar dokumen dengan ikon sumber (Project, docs/, Upload).
- Preview konten Markdown dirender ke HTML menggunakan `league/commonmark` v2.
- Dukungan GitHub Flavored Markdown (GFM): tabel, task list, autolink, strikethrough.
- Heading Permalink dan Table of Contents (placeholder `[TOC]`).
- Upload file `.md` / `.markdown` (maks. 2 MB) dengan judul otomatis dari heading pertama.
- Tombol **Sync** untuk memperbarui daftar file dari root project dan folder `docs/`.
- Download dokumen sebagai file Markdown.
- Hapus dokumen upload (file project bersifat read-only dari UI).
- Styling konten dengan `@tailwindcss/typography` (kelas `prose`), mendukung dark mode.

File upload disimpan di disk `local` (`storage/app/documents/`). File project root dan `docs/` dibaca langsung dari filesystem tanpa disalin ke storage.

Lihat panduan lengkap:
- [`PANDUAN-MARKDOWN-READER.md`](PANDUAN-MARKDOWN-READER.md)

## Konfigurasi AI Chatbot

Fitur chatbot memakai `laravel/ai` dengan multi-provider. Konfigurasi dibaca dari `config/ai.php` dan `config/ai-chat.php`.

### Variabel `.env`

```env
# Provider default
AI_CHAT_DEFAULT_PROVIDER=openai
AI_CHAT_DEFAULT_MODEL=gpt-4o
AI_CHAT_SYSTEM_PROMPT="You are a helpful AI assistant."

# OpenAI
OPENAI_API_KEY=sk-...

# Anthropic
ANTHROPIC_API_KEY=sk-ant-...

# 9Router (OpenAI-compatible proxy)
NINEROUTER_API_KEY=sk-...
NINEROUTER_URL=http://localhost:20128/v1
NINEROUTER_MODELS=CHINAMOD
```

### Provider & Model

Provider dan model yang tersedia didefinisikan di `config/ai-chat.php`. Provider tanpa API key otomatis disembunyikan dari UI.

| Provider       | Driver         | Endpoint API         |
| -------------- | -------------- | -------------------- |
| OpenAI         | `openai`       | Responses API        |
| Anthropic      | `anthropic`    | Messages API         |
| Gemini         | `gemini`       | GenerateContent API  |
| DeepSeek       | `deepseek`     | Chat Completions API |
| Groq           | `groq`         | Chat Completions API |
| Mistral        | `mistral`      | Chat Completions API |
| OpenRouter     | `openrouter`   | Chat Completions API |
| 9Router        | `9router` (custom) | Chat Completions API |
| xAI            | `xai`          | Responses API        |
| Ollama         | `ollama`       | Chat Completions API |

### Custom Driver 9Router

Provider 9Router memakai custom gateway (`NineRouterGateway`) yang extend `OpenRouterGateway` dengan menambahkan header `Accept: application/json`. Header ini diperlukan agar proxy 9Router mengembalikan response JSON valid (bukan SSE streaming) untuk request non-streaming.

Custom driver di-register di `AppServiceProvider::configureAiDrivers()` via `Ai::extend('9router', ...)`.

### Lampiran File

Chatbot mendukung lampiran gambar dan dokumen. Validasi dikonfigurasi di `config/ai-chat.php` (`attachments`):

| Parameter              | Nilai default |
| ---------------------- | ------------- |
| Maks. file per pesan   | 5             |
| Maks. ukuran file      | 10 MB         |
| Format gambar          | jpg, jpeg, png, webp, gif |
| Format dokumen         | pdf, txt, md, csv, json, xml, doc, docx |

### Endpoint

- Halaman chatbot: `/chat`

### Catatan Operasional

- Setelah ubah `.env`, jalankan `php artisan config:clear`.
- Model name untuk 9Router harus sesuai dengan yang tersedia di proxy (tanpa prefix `9router/`).
- Provider yang memakai driver `openai` mengirim ke endpoint Responses API (`/responses`). Provider yang memakai driver `openrouter` atau custom `9router` mengirim ke Chat Completions API (`/chat/completions`).


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

Menghapus file temporary upload Livewire secara manual. Command ini juga membersihkan file temporary QR code yang sudah kedaluwarsa.

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
