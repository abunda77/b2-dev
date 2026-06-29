# B2 Dev — Aplikasi Manajemen Warga

Aplikasi web berbasis **Laravel 13** dan **Livewire 4** untuk mengelola data warga (NIK, nama, alamat, pas foto, dan dokumen). Dibangun di atas Laravel Livewire Starter Kit dengan autentikasi modern (passkey + 2FA via Fortify) dan penyimpanan file ke storage S3-compatible (Backblaze B2 / Cloudflare R2 / AWS S3).

## Fitur

- **Manajemen Warga** — pencatatan data warga beserta unggahan pas foto dan dokumen.
- **Autentikasi lengkap** — login, registrasi, reset password, verifikasi email.
- **Passkey & Two-Factor Authentication** — keamanan akun via `laravel/fortify` dan `@laravel/passkeys`.
- **Penyimpanan fleksibel** — disk `local`, `public`, `s3` (B2), dan `r2` (Cloudflare R2).
- **UI modern** — Flux UI + TailwindCSS 4, halaman pengaturan (profil, keamanan, tampilan).

## Tech Stack

| Kategori        | Teknologi                                             |
| --------------- | ----------------------------------------------------- |
| Backend         | Laravel 13.x, PHP 8.3                                  |
| Frontend        | Livewire 4.x, Flux UI, TailwindCSS 4, Vite, Alpine.js |
| Autentikasi     | Laravel Fortify, Passkeys, 2FA                        |
| Penyimpanan     | S3-compatible (Backblaze B2, Cloudflare R2, AWS S3)   |
| Database        | SQLite (dev), PostgreSQL/MySQL (prod)                 |
| Testing         | PHPUnit 12.x                                           |
| Kualitas Kode   | Laravel Pint, Larastan (PHPStan)                       |

## Persyaratan

- PHP 8.3+
- Composer
- Node.js & npm

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

## Struktur Direktori

```
app/
├── Actions/       # Logika use-case (mis. Fortify, Livewire)
├── Livewire/      # Komponen Livewire
├── Models/        # Model Eloquent (User, Warga)
routes/
├── web.php        # Rute web (memetakan langsung ke komponen Livewire)
└── settings.php   # Rute halaman pengaturan
database/
├── migrations/    # Skema database
├── factories/     # Factory untuk testing
└── seeders/
resources/views/
├── pages/warga/   # Halaman manajemen warga
└── pages/auth/    # Halaman autentikasi
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
