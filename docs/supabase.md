# Panduan Database Supabase untuk Laravel 13

Panduan penggunaan **Supabase** sebagai database PostgreSQL untuk project Laravel ini.
Struktur panduan disesuaikan dengan stack project: Laravel 13 · PHP 8.3 · Livewire 4 · Flux UI v2 · Eloquent · SQLite (dev).

---

## 1. Mengapa Supabase?

Supabase adalah **platform open-source berbasis PostgreSQL** yang menyediakan:

| Fitur                       | Keterangan                                                                 |
|-----------------------------|----------------------------------------------------------------------------|
| **Database Postgres**       | Bisa dipakai langsung sebagai connection `pgsql` Laravel (managed, auto-backup). |
| **Storage**                 | Object storage S3-compatible (untuk upload gambar/PDF, mirip disk `b2`/`r2`).  |
| **Auth**                    | User management + JWT (opsional, bisa diabaikan jika pakai Fortify).          |
| **Realtime**                | Websocket untuk update data live (opsional).                                 |
| **Auto backup & PITR**      | Backup otomatis harian, Point-In-Time-Recovery.                               |

> **Catatan penting:** Pada project ini, autentikasi sudah ditangani **Fortify + OTP/passkey**. Karena itu Supabase dipakai **hanya sebagai database Postgres + Storage**, bukan sebagai penyedia auth. Auth Laravel tetap berjalan normal.

---

## 2. Persiapan Project Supabase

1. Daftar di [https://supabase.com](https://supabase.com) → **Create New Project**.
2. Pilih **Region** terdekat (misal `ap-southeast-1` / singapura).
3. Set **Database Password** (jangan lupa, dipakai untuk koneksi Laravel).
4. Setelah project jadi, buka **Project Settings → Database → Connection string**.
5. Salin **connection string** (URI) atau parameter host/port/db:
   ```
   postgresql://postgres.<project-ref>:<password>@aws-0-ap-southeast-1.pooler.supabase.com:6543/postgres
   ```
   - Port `6543` = **Transaction Pooler** (disarankan untuk shared/connection pooling).
   - Port `5432` = **Direct connection** (untuk migrasi / worker berat).

---

## 3. Konfigurasi `.env` Laravel

Buka `.env` dan ganti **connection database** dari `sqlite` ke `pgsql`, lalu isi kredensial Supabase:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.<project-ref>
DB_PASSWORD=<database-password>
DB_SSLMODE=require
```

> `config/database.php` sudah memiliki connection `pgsql` (line 87–100) yang membaca variabel di atas. Tidak perlu mengubah file config.

### 3.1. SSL (wajib diaktifkan)

Supabase mewajibkan koneksi SSL. Pastikan `DB_SSLMODE=require`. Jika butuh verify cert, unduh `root.crt` dari Supabase dan tambahkan:

```dotenv
# Unduh root.crt dari Dashboard Supabase dan simpan di storage/app/
DB_SSLMODE=verify-ca
DB_SSLROOTCERT=/absolute/path/storage/app/root.crt
```

Jika `pdo_pgsql` tidak mendukung `sslmode` via env, gunakan URI connection string langsung:

```dotenv
DB_CONNECTION=pgsql
DB_URL=pgsql://postgres.<ref>:<password>@aws-0-ap-southeast-1.pooler.supabase.com:6543/postgres?sslmode=require
```

---

## 4. Menjalankan Migrasi

Project ini memakai **Eloquent migrations** (`database/migrations/*`). Untuk menerapkan ke Supabase:

```bash
# Bersihkan cache config dulu
php artisan config:clear

# Jalankan migrasi ke Postgres Supabase
php artisan migrate --force
```

Berikut tabel yang akan dibuat (sesuai file migrasi project):

| Tabel                    | Migrasi                                             |
|--------------------------|-----------------------------------------------------|
| `users`                  | `0001_01_01_000000_create_users_table.php`          |
| `cache`, `jobs`, `sessions` | `0001_01_01_00000x_*`                            |
| `passkeys`               | `2024_01_01_000000_create_passkeys_table.php`       |
| `wargas`                 | `2026_06_28_110440_create_wargas_table.php`         |
| `login_otp_challenges`   | `2026_06_30_042240_create_login_otp_challenges_table.php` |
| `agent_conversations`    | `2026_07_01_130411_create_agent_conversations_table.php`  |
| `fakturs`                | `2026_07_02_052034_create_fakturs_table.php`        |
| `notes`                  | `2026_07_03_053804_create_notes_table.php`          |
| `documents`              | `2026_07_04_122538_create_documents_table.php`      |
| `gallery_photos`         | `2026_07_24_100046_create_gallery_photos_table.php` |
| `file_hosts`             | `2026_07_27_000000_create_file_hosts_table.php`     |

> **Tips:** Jangan jalankan `migrate:fresh` di produksi. Gunakan `php artisan migrate` dan untuk rollback gunakan `php artisan migrate:rollback --step=1`.

---

## 5. Menggunakan Eloquent (Model)

Model Eloquent **tidak perlu diubah sama sekali**. Selama connection aktif, query berjalan normal.

Contoh `app/Models/Faktur.php` (sudah ada di project):

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Faktur extends Model
{
    protected $fillable = [
        'user_id', 'nomor_faktur', 'nama', 'nominal',
        'items', 'terbilang', 'memo', 'paper_size',
        'logo_path', 'pdf_path',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'items'   => 'array',   // json column di Postgres → otomatis ter-cast
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

> **Perbedaan Postgres vs SQLite yang perlu diwaspadai:**
> - `json` column di Postgres dikembalikan sebagai PHP array via cast (sudah benar di project).
> - `decimal` di Postgres bertipe `numeric` (presisi penuh), tidak ada issue seperti SQLite.
> - Pastikan tidak memakai method/feature yang spesifik SQLite (misal `asJson` store path).

---

## 6. Supabase Storage (Object Storage)

Supabase Storage **S3-compatible**. Bisa ditambahkan sebagai disk Laravel baru, mengikuti pola disk `b2`/`r2` yang sudah ada di `config/filesystems.php`.

### 6.1. Buat Bucket & Dapatkan Kredensial

1. Dashboard Supabase → **Storage → New bucket** (misal `uploads`).
2. **Project Settings → Storage → S3 Access Keys** → buat **Access Key & Secret Key**.
3. Catat **endpoint storage**:
   ```
   https://<project-ref>.supabase.co/storage/v1/s3
   ```

### 6.2. Tambahkan Disk `supabase` di `config/filesystems.php`

Di dalam array `disks` (setelah disk `r2`), tambahkan:

```php
'supabase' => [
    'driver' => 's3',
    'key' => env('SUPABASE_S3_ACCESS_KEY'),
    'secret' => env('SUPABASE_S3_SECRET_KEY'),
    'region' => env('SUPABASE_S3_REGION', 'ap-southeast-1'),
    'bucket' => env('SUPABASE_S3_BUCKET', 'uploads'),
    'url' => env('SUPABASE_S3_URL'),
    'endpoint' => env('SUPABASE_S3_ENDPOINT'),
    'use_path_style_endpoint' => true,
    'throw' => false,
    'report' => false,
],
```

### 6.3. Environment Variables

```dotenv
SUPABASE_S3_ACCESS_KEY=your-s3-access-key
SUPABASE_S3_SECRET_KEY=your-s3-secret-key
SUPABASE_S3_REGION=ap-southeast-1
SUPABASE_S3_BUCKET=uploads
SUPABASE_S3_ENDPOINT=https://<project-ref>.supabase.co/storage/v1/s3
SUPABASE_S3_URL=https://<project-ref>.supabase.co/storage/v1/object/public/uploads
```

### 6.4. Gunakan di Model / Service

```php
use Illuminate\Support\Facades\Storage;

// Upload
$path = Storage::disk('supabase')->put('photos/'.$request->file('photo')->getClientOriginalName(), $request->file('photo'));

// URL publik
$url = Storage::disk('supabase')->url($path);

// Temporary URL (jika private)
$signedUrl = Storage::disk('supabase')->temporaryUrl($path, now()->addHours(3));

// Delete
Storage::disk('supabase')->delete($path);
```

> **Pola serupa dengan `deleteAllFiles()` di `app/Models/Faktur.php`** yang memakai `Storage::disk('b2')`. Ganti nama disk-nya menjadi `supabase` sesuai kebutuhan.

---

## 7. Row Level Security (RLS)

Supabase mengaktifkan **RLS** default. Karena Laravel mengakses database **langsung** (bukan lewat REST API), RLS **tidak memengaruhi** koneksi `pgsql` Eloquent — kredensial `postgres` adalah superuser dan dilewati RLS.

Aturan praktis:
- **Jangan** pakai `service_role`/`anon` key untuk koneksi Laravel. Gunakan **Database Password** (user `postgres`) dari tab *Connection string*.
- Jika suatu saat memakai Supabase REST API (lewat `Http`), barulah `service_role` key relevan — dan hanya boleh di backend, tidak pernah di client.

---

## 8. Queue & Scheduler di Produksi

Supabase **tidak** menjalankan Laravel scheduler/queue worker. Tabel `jobs` dibuat oleh migrasi, tapi worker harus dijalankan dari server Anda.

```bash
# Jalankan queue worker (seperti di AGENTS.md)
php artisan queue:work --queue=otp,default

# Jalankan scheduler (cron tiap menit)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Untuk produksi, pertimbangkan **queue driver** yang lebih handal daripada `database` (misal Redis/SQS) karena `jobs` di Postgres bisa menjadi bottleneck jika volume tinggi.

---

## 9. Testing (Tetap SQLite)

Konfigurasi test di project (`phpunit.xml`) memakai **SQLite in-memory** dan tidak bergantung pada database eksternal. Ini **tetap berlaku** walau produksi memakai Supabase — test tidak perlu Sulit diubah.

```xml
<!-- phpunit.xml (jangan diubah) -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Jalankan test seperti biasa:

```bash
php artisan test --compact
composer run lint:check
composer run types:check
```

> **Catatan:** Karena test memakai SQLite, hindari menulis test yang bergantung pada behavior spesifik Postgres (mis. `jsonb`, `ILIKE`). Untuk query yang berbeda antar driver, gunakan fork sesuai `DB_CONNECTION`.

---

## 10. Troubleshooting

| Masalah                        | Solusi                                                                                     |
|--------------------------------|--------------------------------------------------------------------------------------------|
| `SQLSTATE[08006] SSL error`    | Pastikan `DB_SSLMODE=require` atau `?sslmode=require` di URI.                               |
| `Too many connections`         | Gunakan **Transaction Pooler** (port `6543`) untuk koneksi web.    |
| Migrasi gagal (type mismatch)  | Hapus `config:cache`, pastikan `php artisan config:clear` sebelum `migrate`.                |
| `password authentication failed` | Cek `DB_PASSWORD` cocok dengan *Database Password* project (bukan anon key).              |
| Waktu query lambat             | Pastikan kolom yang di-`where`/`orderBy` punya index (Postgres auto-index pk; tambah index manual untuk `user_id`, `created_at`). |
| Perbedaan behavior SQLite/Postgres | Uji di lokal dengan `DB_CONNECTION=pgsql` juga, jangan hanya SQLite.                       |

---

## 11. Ceklis Sebelum Produksi

- [ ] Project Supabase dibuat & region terdekat dipilih
- [ ] `.env` diubah ke `DB_CONNECTION=pgsql` dengan kredensial Supabase
- [ ] `DB_SSLMODE=require` aktif
- [ ] `php artisan config:clear && php artisan migrate --force` berhasil
- [ ] Eloquent query normal (test `php artisan tinker` → `Faktur::count()`)
- [ ] Bucket Storage dibuat & disk `supabase` dikonfigurasi (opsional, jika pakai Storage)
- [ ] Queue worker/scheduler dijalankan di server (bukan di Supabase)
- [ ] Test tetap hijau (`php artisan test --compact`)

---

## Referensi

- [Supabase Database (Postgres)](https://supabase.com/docs/guides/database)
- [Supabase Connection String](https://supabase.com/docs/guides/database/connecting-to-postgres)
- [Supabase Storage S3 API](https://supabase.com/docs/guides/storage/s3)
- [Laravel Database (Postgres)](https://laravel.com/docs/database)
- [Laravel Migration](https://laravel.com/docs/migrations)
- [Laravel File Storage](https://laravel.com/docs/filesystem)