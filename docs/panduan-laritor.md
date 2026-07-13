# Panduan Teknis `binarybuilds/laritor-client` (Laritor)

Panduan komprehensif untuk mengintegrasikan **Laritor** — platform observability & APM (Application Performance Monitoring) yang dibangun khusus untuk Laravel — ke dalam aplikasi Laravel Anda.

Paket klien: [`binarybuilds/laritor-client`](https://github.com/binarybuilds/laritor-client) · Dokumentasi resmi: [https://laritor.com/docs](https://laritor.com/docs)

---

## Daftar Isi

1. [Apa itu Laritor](#1-apa-itu-laritor)
2. [Persyaratan (Requirements)](#2-persyaratan-requirements)
3. [Persiapan Akun & Onboarding](#3-persiapan-akun--onboarding)
4. [Instalasi](#4-instalasi)
5. [Konfigurasi Environment](#5-konfigurasi-environment)
6. [Sinkronisasi & Metrik Server](#6-sinkronisasi--metrik-server)
7. [Event yang Dilacak](#7-event-yang-dilacak)
8. [Kustomisasi & Filtering](#8-kustomisasi--filtering)
9. [Redaksi Data Sensitif](#9-redaksi-data-sensitif)
10. [Sampling & Rate Limiting](#10-sampling--rate-limiting)
11. [Panduan Penggunaan Lanjutan](#11-panduan-penggunaan-lanjutan)
12. [Integrasi & Notifikasi](#12-integrasi--notifikasi)
13. [Performa & Tagihan Event](#13-performa--tagihan-event)
14. [Referensi Cepat Perintah Artisan](#14-referensi-cepat-perintah-artisan)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. Apa itu Laritor

Laritor adalah platform **observability full-stack** yang dirancang khusus untuk arsitektur Laravel. Ia menangkap:

- Request HTTP (durasi, memori, status, user terautentikasi)
- Query database (SQL, bindings, durasi, deteksi N+1)
- Exception dengan stack trace & snippet kode
- Queued jobs, commands, dan scheduled tasks
- Outbound HTTP requests, cache, logs, mails, notifications
- Server metrics (CPU, memori, disk) dan health checks

Keunggulan utama: **tanpa agen/sidecar**. Cukup install Composer package, tambahkan 3 environment variable, dan data langsung mengalir. Berjalan di Vapor, Forge, Cloud, Docker, maupun VPS kustom.

---

## 2. Persyaratan (Requirements)

### Versi Laravel yang Didukung
`9.x`, `10.x`, `11.x`, `12.x`, `13.x`

### Versi PHP yang Didukung
`PHP 8.0`, `8.1`, `8.2`, `8.3`, `8.4`, `8.5`

### Dependensi Paket
- `php: ^7.4 | ^8.0 | ^8.1 | ^8.2 | ^8.3 | ^8.4`
- `ext-json`
- `guzzlehttp/guzzle: ^7.2`
- `laravel/framework: ^9.0.0 | ^10.0.0 | ^11.0.0 | ^12.0.0`

### Arsitektur Deployment
| Setup | Status |
|-------|--------|
| Custom VPS | ✅ Didukung |
| Shared Hosting | ✅ Didukung |
| Laravel Octane | ✅ Didukung |
| Laravel Vapor | ✅ Didukung |
| Serverless | ✅ Didukung |
| Auto scaling | ✅ Didukung |
| Long Running PHP (tanpa Octane) | ❌ Tidak didukung |

> **Catatan:** Laritor tidak mendukung PHP long-running yang tidak menggunakan Octane, karena model ingest-nya mengandalkan siklus request/command.

### Region Penyimpanan Data
- 🇺🇸 United States — New York City
- 🇳🇱 Netherlands — Amsterdam

> Region dipilih saat pendaftaran akun dan **tidak dapat diubah**. Pilih lokasi terdekat dengan Anda (bukan lokasi server), karena ingest selalu diproses dari lokasi terdekat dengan server Anda.

---

## 3. Persiapan Akun & Onboarding

1. **Sign Up** di [https://laritor.com/signup](https://laritor.com/signup). Pilih region penyimpanan data (permanen) dan buat akun (manual atau Google/GitHub).
2. **Buat Team.** Isi nama tim dan billing address. Tidak ada kartu kredit yang diperlukan (paket gratis).
3. **Buat Application.** Beri nama aplikasi Laravel yang akan dimonitor. Environment dibuat otomatis saat event pertama masuk dari tiap environment unik.
4. **Install Laritor Client** mengikuti langkah di bawah (lihat [Instalasi](#4-instalasi)). Setelah event pertama diterima, dashboard otomatis menampilkan data.
5. **Selesai 🎉** — akun, tim, aplikasi, dan klien sudah terhubung. Data real-time mengalir ke dashboard.

---

## 4. Instalasi

Jalankan perintah Composer berikut di root project Laravel Anda:

```bash
composer require binarybuilds/laritor-client
```

Paket ini secara otomatis mendaftarkan service provider (auto-discovery Laravel). Tidak ada langkah publish config manual yang wajib untuk mulai mengirim event.

---

## 5. Konfigurasi Environment

Tambahkan variabel berikut ke file `.env` Anda:

```env
LARITOR_ENABLED=true
LARITOR_INGEST_ENDPOINT=your-ingest-url
LARITOR_BACKEND_KEY=your-backend-key
```

Ganti `your-ingest-url` dan `your-backend-key` dengan nilai yang diberikan di dashboard Laritor saat setup.

### Daftar Lengkap Environment Variables

| Fitur | Variable | Default | Deskripsi |
|-------|----------|---------|-----------|
| Backend API Key | `LARITOR_BACKEND_KEY` | — | API key untuk autentikasi ingest event |
| Ingest Endpoint | `LARITOR_INGEST_ENDPOINT` | — | URL lengkap tujuan ingest event |
| Enable/Disable | `LARITOR_ENABLED` | `false` | Jeda semua pengumpulan event sementara tanpa uninstall |
| Environment Name | `LARITOR_ENV` | nilai `APP_ENV` | Override nama environment yang terdeteksi |
| Server Name | `LARITOR_SERVER_NAME` | Hostname | Nama server kustom (berguna di serverless) |
| Max Events / Occurrence | `LARITOR_MAX_EVENTS_PER_OCCURRENCE` | `5000` | Batas event per request/command |
| Laravel Context | `LARITOR_RECORD_CONTEXT` | `true` | Capture Laravel 11+ context |
| DB Schema Tracking | `LARITOR_RECORD_DB_SCHEMA` | `true` | Lacak schema untuk visualisasi & optimasi query AI |
| Log Level | `LARITOR_LOG_LEVEL` | `debug` | Level log minimum (`debug`, `info`, `error`, dst) |
| Query Bindings | `LARITOR_RECORD_QUERY_BINDINGS` | `true` | Sertakan nilai parameter SQL |
| Query String | `LARITOR_RECORD_QUERY_STRING` | `false` | Capture HTTP query string |
| Request Headers | `LARITOR_RECORD_REQUEST_HEADERS` | `false` | Capture header request masuk |
| Request Body | `LARITOR_RECORD_REQUEST_BODY` | `false` | Capture body request masuk |
| Response Headers | `LARITOR_RECORD_REQUEST_RESPONSE_HEADERS` | `false` | Capture header response masuk |
| Response Body | `LARITOR_RECORD_REQUEST_RESPONSE_BODY` | `false` | Capture body response masuk |
| Outbound Request Headers | `LARITOR_RECORD_OUTBOUND_REQUEST_HEADERS` | `false` | Capture header outbound request |
| Outbound Request Body | `LARITOR_RECORD_OUTBOUND_REQUEST_BODY` | `false` | Capture body outbound request |
| Outbound Response Headers | `LARITOR_RECORD_OUTBOUND_REQUEST_RESPONSE_HEADERS` | `false` | Capture header outbound response |
| Outbound Response Body | `LARITOR_RECORD_OUTBOUND_REQUEST_RESPONSE_BODY` | `false` | Capture body outbound response |
| Rate Limit Requests | `LARITOR_RATE_LIMIT_REQUESTS` | `false` | Aktifkan sampling per-URL |
| Requests per URL / Menit | `LARITOR_RATE_LIMIT_REQUESTS_ATTEMPTS` | — | Jumlah request per URL per menit saat rate limit aktif |

> Field sensitif (headers, body, query string) **tidak** dikumpulkan secara default. Aktifkan eksplisit melalui environment variable di atas bila diperlukan.

---

## 6. Sinkronisasi & Metrik Server

### Sync Setelah Setiap Deployment

Jalankan perintah ini setelah setiap deployment agar scheduled tasks, perubahan schema database, custom health checks, dan metadata server tetap mutakhir di Laritor:

```bash
php artisan laritor:sync
```

> Tip: Otomatiskan dengan menambahkannya ke hook post-deploy atau pipeline CI/CD Anda.

### Kumpulkan Metrik Server (Opsional)

Untuk memantau CPU, memori, dan penggunaan disk, jadwalkan perintah berikut berjalan **setiap menit** melalui Laravel Scheduler (atau cron sistem):

```bash
php artisan laritor:send-metrics
```

Tambahkan ke `routes/console.php` atau `App\Console\Kernel`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('laritor:send-metrics')->everyMinute();
```

Setelah langkah di atas, aplikasi mulai mengirim event ke Laritor.

---

## 7. Event yang Dilacak

Laritor otomatis melacak berbagai event runtime. Anda mengontrol sepenuhnya apa yang direkam melalui filtering & redaksi.

| Event | Yang Dilacak |
|-------|-------------|
| **Requests** | Route, URL, IP, user agent, durasi, memori, status code, controller, user terautentikasi. Context Laravel 11+ (jika diaktifkan). |
| **Commands** | Signature, start/end time, exit code, context. Output bisa di-capture via trait. |
| **Scheduled Tasks** | Cron expression, last/next run, durasi tiap eksekusi, exit code, output (jika diaktifkan). |
| **Queued Jobs** | Job class, queue, connection, dispatch/start/end, durasi, delay, wait time, link ke event pemicu. |
| **Exceptions** | Class, message, file, line, stack trace lengkap dengan snippet kode tiap frame. |
| **Queries** | Raw SQL, bindings, file/line asal, durasi (ms), pengelompokan, flag duplikat & N+1. |
| **Outbound Requests** | URL target, status, durasi. Headers/body opsional. |
| **Cache Hits** | Cache key, timestamp, occurrence asal. |
| **Logs** | Level, timestamp, context — terhubung ke request/job/command pembuatnya. |
| **Mails** | Mailable class, penerima, subject, waktu kirim. |
| **Notifications** | Notification class, notifiable, waktu, event pemicu. |
| **Server Metrics** | CPU %, memori %, disk % (via `laritor:send-metrics`). |
| **Health Checks** | Queue backlog, scheduled task failure, DB connectivity, session, cache, filesystem + custom health checks. |

---

## 8. Kustomisasi & Filtering

Secara default Laritor merekam semua event yang didukung. Untuk mengabaikan event tertentu (mengurangi noise & biaya), buat filter class kustom.

### Langkah 1: Publish Filter Class

```bash
php artisan make:laritor-filter
```

File dibuat di `app/Laritor/LaritorDataFilter.php`. Override method untuk mengembalikan `false` pada event yang ingin diabaikan.

### Langkah 2: Daftarkan di `AppServiceProvider`

```php
public function boot(): void
{
    $this->app->bind(
        \BinaryBuilds\LaritorClient\Override\LaritorOverride::class,
        \App\Laritor\LaritorDataFilter::class
    );
}
```

### Method Filter yang Tersedia

| Method | Fungsi |
|--------|--------|
| `recordRequest($request)` | `false` untuk abaikan request tertentu |
| `recordQuery($query, $duration)` | `false` untuk abaikan query tertentu |
| `recordException($e)` | `false` untuk abaikan exception tertentu |
| `recordQueuedJob($job)` | `false` untuk abaikan job tertentu |
| `recordMail($message)` | `false` untuk abaikan mail tertentu |
| `recordNotification($notifiable, $notification)` | `false` untuk abaikan notification tertentu |
| `recordCommandOrScheduledTask($command)` | `false` untuk abaikan command tertentu |
| `recordOutboundRequest($url)` | `false` untuk abaikan outbound request tertentu |
| `recordCacheHit($cacheKey)` | `false` untuk abaikan cache key tertentu |
| `recordTaskScheduler()` | `false` untuk abaikan health tracking scheduler |
| `isBot($request)` | tentukan request berasal dari bot atau bukan |

### Contoh Filter

**Abaikan request tertentu (health, telescope, horizon, asset):**

```php
public function recordRequest($request): bool
{
    $path = $request->path();

    if (
        $path === 'health' ||
        str_starts_with($path, 'telescope') ||
        str_starts_with($path, 'horizon') ||
        preg_match('/\.(js|css|jpg|jpeg|png|svg|gif|ico)$/i', $path)
    ) {
        return false;
    }

    return true;
}
```

**Abaikan query cepat & query Telescope/Horizon:**

```php
public function recordQuery($query, $duration): bool
{
    if ($duration < 5) {
        return false;
    }

    if (preg_match('/\b(telescope_entries|horizon_jobs|horizon_tags)\b/i', $query)) {
        return false;
    }

    return true;
}
```

**Tandai bot internal sebagai bukan bot:**

```php
public function isBot($request): bool
{
    $ua = strtolower($request->userAgent());

    if (str_contains($ua, 'internal-bot')) {
        return false;
    }

    return parent::isBot($request);
}
```

---

## 9. Redaksi Data Sensitif

Laritor otomatis meredaksi data sensitif sebelum keluar dari server Anda. Anda dapat memperluas logika redaksi.

### Langkah 1: Publish Redactor Class

```bash
php artisan make:laritor-redactor
```

File dibuat di `app/Laritor/LaritorDataRedactor.php`.

### Langkah 2: Daftarkan di `AppServiceProvider`

```php
public function boot(): void
{
    $this->app->bind(
        \BinaryBuilds\LaritorClient\Redactor\DataRedactor::class,
        \App\Laritor\LaritorDataRedactor::class
    );
}
```

### Method Redactor yang Tersedia

| Method | Meredaksi |
|--------|-----------|
| `redactEmailAddress($email)` | Alamat email |
| `redactString($text)` | String umum (log line, body pesan) |
| `redactArrayValue($key, $value)` | Pasangan key-value array (password, token, dst) |
| `redactAuthenticatedUser(): array` | Detail user terautentikasi |
| `redactIPAddress($ip)` | Alamat IP |
| `redactUserAgent($ua)` | Header User-Agent |

### Contoh Redaksi

**Redaksi email:**

```php
public function redactEmailAddress($address): string
{
    [$user, $domain] = explode('@', $address);

    return str_repeat('*', strlen($user)) . '@' . $domain;
}
```

**Redaksi array (password, token, dll):**

```php
public function redactArrayValue($key, $value): string
{
    $sensitiveKeys = [
        'password', 'token', 'access_token', 'refresh_token',
        'api_key', 'secret', 'authorization', 'auth_token',
    ];

    if (in_array(strtolower($key), $sensitiveKeys, true)) {
        return '*****';
    }

    return $value;
}
```

**Kirim atribut tambahan user terautentikasi** (id, name, email wajib selalu ada):

```php
public function redactAuthenticatedUser(): array
{
    $user = Auth::user();

    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'team' => [
                'id' => $user->team->id,
                'name' => $user->team->name,
            ],
            'role' => $user->role,
        ];
    }

    return parent::redactAuthenticatedUser();
}
```

---

## 10. Sampling & Rate Limiting

Untuk trafik tinggi, batasi jumlah event via sampling per-URL agar biaya & noise terkendali.

Aktifkan di `.env`:

```env
LARITOR_RATE_LIMIT_REQUESTS=true
LARITOR_RATE_LIMIT_REQUESTS_ATTEMPTS=5
```

Mekanisme:
- Laritor menghitung berapa kali tiap URL unik tercatat per menit.
- Setelah batas tercapai, event berikutnya untuk URL tersebut dibuang hingga menit berikutnya.
- Batas berlaku **independen per URL** → endpoint kritis tidak ter-throttle.

Contoh: dengan limit 5, dari 20 request ke `/api/orders` dalam 1 menit, hanya 5 yang direkam.

---

## 11. Panduan Penggunaan Lanjutan

### Custom Event Tracking

Tandai momen bisnis/teknis penting di produksi:

```php
use BinaryBuilds\LaritorClient\Laritor;

Laritor::addCustomEvent('checkout.started', [
    'cart_id' => $cart->id,
    'user_id' => $user->id,
    'items_count' => $cart->items()->count(),
]);

Laritor::addCustomEvent('checkout.payment_authorized', [
    'cart_id' => $cart->id,
    'payment_provider' => 'stripe',
    'amount' => $cart->total(),
]);
```

Custom event muncul di **Timeline view** (request/job/task/command) dan di halaman **Custom Events** navigasi utama. Gunakan nama event stabil (mis. `checkout.started`, `tenant-sync.completed`).

### Capture Custom Logs

Untuk log yang tidak lewat channel Laravel (audit DB, activity log, webhook vendor):

```php
use Carbon\Carbon;
use BinaryBuilds\LaritorClient\Laritor;

$logs = [
    [
        'level' => 'INFO',      // DEBUG, NOTICE, INFO, WARNING, ERROR, ALERT, CRITICAL, EMERGENCY
        'message' => 'custom log 1',
        'type' => 'custom-logs',
        'written_at' => Carbon::parse('2025-10-24 12:55:55'),
        'context' => ['user_id' => 1, 'account_id' => 2, 'source' => 'db-audit'],
    ],
    // ... log lainnya
];

$laritor = new Laritor();
foreach ($logs as $log) {
    $laritor->addCustomLog(
        $log['type'], $log['level'], $log['message'], $log['context'], $log['written_at']
    );
}
$laritor->sendEvents(); // kirim dalam 1 batch
```

Jalankan rutin ini via scheduled task agar Laritor tetap mutakhir.

### Record Command Output

Tambahkan trait `SendOutputToLaritor` ke class command untuk menangkap output (`info`, `warn`, `error`, `comment`, `table`, dll):

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use BinaryBuilds\LaritorClient\SendOutputToLaritor;

class ImportUsers extends Command
{
    use SendOutputToLaritor;

    protected $signature = 'users:import';
    protected $description = 'Import users from external service';

    public function handle()
    {
        $this->info('Starting import...');
        // logika import
        $this->info('Import complete!');
    }
}
```

---

## 12. Integrasi & Notifikasi

Laritor mendukung berbagai integrasi untuk alert & kolaborasi:
- **GitHub**, **Linear**, **Slack**, **Discord**, **Telegram**, **Microsoft Teams**
- **Webhooks** (custom)
- **AI Provider** (insight berbasis AI)
- **Email Lists**

Konfigurasi integrasi dilakukan melalui dashboard Laritor (bukan di klien), lalu alert & dashboard dapat disetel sesuai kebutuhan.

---

## 13. Performa & Tagihan Event

Laritor dihitung berdasarkan **jumlah data** (increment 2 KB), bukan sekadar jumlah request:
- Event < 2 KB → dihitung 1 event.
- Event > 2 KB → dihitung proporsional per 2 KB.

Contoh: request 1.5 KB = 1 event; request 3 KB = 2 event; command 5 KB = 3 event.

Untuk menjaga performa & biaya:
- Nonaktifkan `LARITOR_ENABLED=false` saat tidak perlu (tanpa uninstall).
- Gunakan filter untuk mengabaikan event tidak penting.
- Aktifkan sampling untuk trafik tinggi.
- Hati-hati mengaktifkan capture headers/body (menambah ukuran event).

---

## 14. Referensi Cepat Perintah Artisan

| Perintah | Fungsi |
|----------|--------|
| `php artisan laritor:sync` | Sinkronisasi task, schema, health checks, metadata server (setelah deploy) |
| `php artisan laritor:send-metrics` | Kirim metrik server (CPU/memori/disk), jalankan tiap menit |
| `php artisan make:laritor-filter` | Generate class filter kustom (`app/Laritor/LaritorDataFilter.php`) |
| `php artisan make:laritor-redactor` | Generate class redactor kustom (`app/Laritor/LaritorDataRedactor.php`) |

---

## 15. Troubleshooting

**Event tidak muncul di dashboard:**
- Pastikan `LARITOR_ENABLED=true` dan `.env` sudah di-cache ulang (`php artisan config:clear`).
- Pastikan `LARITOR_INGEST_ENDPOINT` & `LARITOR_BACKEND_KEY` benar (dari dashboard).
- Kirim trafik nyata (request/command) — event dikirim di akhir siklus.

**Data tidak sinkron (task/schema berubah):**
- Jalankan `php artisan laritor:sync` setelah setiap deployment.

**Metrik server kosong:**
- Pastikan `php artisan laritor:send-metrics` terjadwal tiap menit (cek `php artisan schedule:list`).

**Terlalu banyak event / biaya tinggi:**
- Aktifkan filter (`make:laritor-filter`) dan sampling (`LARITOR_RATE_LIMIT_REQUESTS=true`).

**Long-running PHP tanpa Octane:**
- Tidak didukung. Gunakan Octane atau arsitektur serverless/queue-based.

**Bantuan lebih lanjut:**
- Email: support@laritor.com
- Discord: [discord.laritor.com](https://discord.laritor.com)
- GitHub: [github.com/binarybuilds/laritor-client](https://github.com/binarybuilds/laritor-client)

---

> Panduan ini disusun berdasarkan dokumentasi resmi Laritor (`https://laritor.com/docs`) dan metadata paket di Packagist. Selalu rujuk dokumentasi resmi untuk pembaruan terbaru.
