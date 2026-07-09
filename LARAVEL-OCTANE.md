# Panduan Laravel Octane — Instalasi & Konfigurasi

Dokumen ini menjelaskan cara instalasi dan konfigurasi **Laravel Octane** pada aplikasi **Laravel 13**. Format dokumen mengikuti pola referensi **MCP Context7**: ada sumber referensi, topik inti, langkah implementasi, contoh konfigurasi, verifikasi, dan troubleshooting.

---

## Referensi Teknis

### Context7

- `/laravel/octane`

### Topik utama dari referensi

- Laravel Octane menggunakan application server bertenaga tinggi: FrankenPHP, Open Swoole, Swoole, atau RoadRunner
- Aplikasi di-boot sekali, disimpan di memori, lalu melayani request dengan kecepatan tinggi
- Mendukung concurrent tasks, tick/interval, shared memory tables (Swoole), dan cache driver bawaan
- Konfigurasi server lewat `config/octane.php` dan environment variable `OCTANE_SERVER`
- Dapat di-reload tanpa downtime dengan `octane:reload`

---

## Ringkas

Laravel Octane meningkatkan performa aplikasi Laravel secara signifikan dengan menyimpan aplikasi di memori setelah booting pertama. Setiap request dilayani tanpa perlu me-reboot seluruh framework, sehingga throughput meningkat drastis.

Empat server yang didukung:
- **RoadRunner** — application server berbasis Go, multi-process, stabil
- **Swoole** — extension PHP, mendukung coroutine, shared memory tables, dan tick/interval
- **Open Swoole** — fork dari Swoole, kompatibel secara API
- **FrankenPHP** — server PHP modern berbasis Caddy, mendukung HTTP/3, Early Hints, dan worker mode

File penting di project ini:
- `config/octane.php` — konfigurasi utama server Octane
- `.env` — environment variable `OCTANE_SERVER`

---

## 1. Prasyarat

Sebelum mulai, pastikan hal berikut sudah siap:

| Prasyarat | Keterangan |
|---|---|
| **PHP 8.3+** | Laravel 13 mensyaratkan PHP 8.3 minimum |
| **Composer** | Untuk instalasi package `laravel/octane` |
| **Server pilihan** | RoadRunner (binary Go), Swoole (extension PHP), atau FrankenPHP (binary PHP standalone) |

### Cek Extension PHP

```bash
php -m | Select-String -Pattern 'swoole|roadrunner'
```

---

## 2. Instalasi Package

Instal package Laravel Octane via Composer:

```bash
composer require laravel/octane
```

Kemudian jalankan perintah instalasi untuk memilih server:

```bash
# RoadRunner (default)
php artisan octane:install --server=roadrunner

# Swoole
php artisan octane:install --server=swoole

# FrankenPHP
php artisan octane:install --server=frankenphp
```

Perintah `octane:install` akan:
- Mem-publish file konfigurasi `config/octane.php`
- Menginstal binary server (jika diperlukan)

---

## 3. Konfigurasi

### 3.1 File `.env`

Tentukan server yang digunakan di file `.env`:

```env
OCTANE_SERVER=roadrunner
```

Nilai yang didukung: `roadrunner`, `swoole`, `frankenphp`.

### 3.2 File `config/octane.php`

Konfigurasi utama tersimpan di `config/octane.php`. Bagian penting:

```php
// config/octane.php
'server' => env('OCTANE_SERVER', 'roadrunner'),
```

### 3.3 Konfigurasi Worker (Swoole)

```php
// config/octane.php
'swoole' => [
    'options' => [
        'worker_num' => env('OCTANE_SWOOLE_WORKERS', 4),
        'task_worker_num' => env('OCTANE_SWOOLE_TASK_WORKERS', 0),
        'max_request' => env('OCTANE_SWOOLE_MAX_REQUESTS', 500),
    ],
],
```

### 3.4 Konfigurasi Swoole Table (Cache & Shared Memory)

```php
// config/octane.php
'tables' => [
    'cache:10000' => [
        'key' => 'string:255',
        'value' => 'string:65536',
        'expires' => 'int',
    ],
    'stats:100' => [
        'name' => 'string:100',
        'count' => 'int',
        'total' => 'float',
    ],
],
```

### 3.5 Octane Cache Driver

Untuk menggunakan cache driver bawaan Octane (Swoole table):

```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'octane'),

'stores' => [
    'octane' => [
        'driver' => 'octane',
    ],
],
```

---

## 4. Menjalankan Server

### 4.1 RoadRunner

```bash
# Default
php artisan octane:start

# Dengan jumlah worker kustom
php artisan octane:start --workers=8

# Dengan watch mode (auto-reload saat file berubah)
php artisan octane:start --workers=4 --watch

# Port kustom
php artisan octane:start --port=8080
```

### 4.2 Swoole

```bash
# Default
php artisan octane:start --server=swoole

# Production-ready
php artisan octane:start --server=swoole \
    --workers=16 \
    --task-workers=32 \
    --memory=256 \
    --max-requests=500 \
    --timeout=30

# Watch mode
php artisan octane:start --server=swoole --watch
```

### 4.3 FrankenPHP

```bash
php artisan octane:start --server=frankenphp

# HTTPS
php artisan octane:start --server=frankenphp --https

# Watch mode
php artisan octane:start --server=frankenphp --watch
```

---

## 5. Reload & Stop Server

### Graceful Reload (Tanpa Downtime)

```bash
php artisan octane:reload
```

### Stop Server

```bash
php artisan octane:stop
```

### Cek Status

```bash
php artisan octane:status
```

---

## 6. Fitur Utama

### 6.1 Concurrent Tasks

Jalankan beberapa task secara paralel:

```php
use Laravel\Octane\Facades\Octane;

[$users, $orders] = Octane::concurrently([
    fn () => User::all(),
    fn () => Order::where('status', 'pending')->get(),
]);

// Atau dengan timeout kustom
$results = Octane::concurrently([
    'primary' => fn () => call_primary_service(),
    'secondary' => fn () => call_secondary_service(),
], waitMilliseconds: 2000);
```

### 6.2 Tick / Interval

Registrasi handler yang berjalan secara periodik:

```php
// Di App\Providers\OctaneServiceProvider atau boot()
use Laravel\Octane\Facades\Octane;

Octane::tick('cleanup-expired-sessions', function () {
    DB::table('sessions')->where('last_activity', '<', now()->subHours(2)->timestamp)->delete();
}, seconds: 60, immediate: false);
```

### 6.3 Swoole Tables (Shared Memory)

```php
use Laravel\Octane\Facades\Octane;

// Menulis data
Octane::table('stats')->set('page-views', [
    'name' => 'homepage',
    'count' => 1200,
    'total' => 45000,
]);

// Membaca data
$stats = Octane::table('stats')->get('page-views');
```

### 6.4 Error Handling pada Concurrent Tasks

```php
try {
    $results = Octane::concurrently([
        'primary' => fn () => call_primary_service(),
        'secondary' => fn () => call_secondary_service(),
    ], waitMilliseconds: 2000);
} catch (TaskException $e) {
    Log::error('Task failed', ['class' => $e->getClass()]);
    return response()->json(['error' => 'Service unavailable'], 503);
} catch (TaskTimeoutException $e) {
    Log::warning('Tasks timed out');
    return response()->json(['error' => 'Request timeout'], 504);
}
```

---

## 7. Hal Penting & Best Practices

### 7.1 State Management

Karena aplikasi di-boot sekali dan tetap di memori, hindari menyimpan state di properti statis:

```php
// ❌ Buruk — state akan bertahan antar request
class UserService
{
    protected static $currentUser;

    public function setUser($user)
    {
        static::$currentUser = $user;
    }
}

// ✅ Baik — gunakan DI atau closure
class UserService
{
    public function process($user)
    {
        // ...
    }
}
```

### 7.2 Middleware

Gunakan middleware yang stateless. Hindari middleware yang menyimpan state internal:

```php
// ✅ Baik — stateless
middleware: [CheckIpWhitelist::class]

// ❌ Buruk — menyimpan state
middleware: [RateLimiter::class]  // Gunakan Octane table sebagai gantinya
```

### 7.3 Service Providers

Service provider di-boot sekali. Jangan daftarkan singleton yang state-nya berubah antar request. Gunakan `scoped()` binding jika perlu:

```php
// Di AppServiceProvider
$this->app->scoped(SomeService::class, function ($app) {
    return new SomeService($app['request']);
});
```

### 7.4 Session & Cookie

Pastikan session driver kompatibel dengan Octane. Gunakan `database`, `redis`, atau `memcached` — hindari `file` session driver jika memungkinkan.

---

## 8. Troubleshooting

| Masalah | Solusi |
|---|---|
| **Port already in use** | Ganti port dengan `--port=8080` atau stop proses sebelumnya: `php artisan octane:stop` |
| **Extension Swoole tidak terdeteksi** | Instal ekstensi Swoole: `pecl install swoole` lalu aktifkan di `php.ini` |
| **RoadRunner binary tidak ditemukan** | Jalankan ulang `php artisan octane:install --server=roadrunner` |
| **Memory leak** | Atur `--max-requests=500` agar worker di-recycle setelah jumlah request tertentu |
| **Cache tidak berfungsi** | Pastikan cache driver di `config/cache.php` menggunakan `octane` driver (hanya untuk Swoole) |
| **Session hilang antar request** | Gunakan `database`, `redis`, atau `memcached` session driver |
| **Static property persisting** | Reset atau hindari static property; gunakan `scoped()` binding |

---

## 9. Perintah Octane Lengkap

| Perintah | Keterangan |
|---|---|
| `php artisan octane:install` | Instal dan konfigurasi Octane |
| `php artisan octane:start` | Mulai server Octane |
| `php artisan octane:start-roadrunner` | Mulai server dengan RoadRunner |
| `php artisan octane:start-swoole` | Mulai server dengan Swoole |
| `php artisan octane:start-frankenphp` | Mulai server dengan FrankenPHP |
| `php artisan octane:reload` | Reload worker tanpa downtime |
| `php artisan octane:stop` | Stop server Octane |
| `php artisan octane:status` | Cek status server |

---

## 10. Deployment Production

### Supervisor (Linux)

```ini
[program:laravel-octane]
command=php /path/to/project/artisan octane:start --server=swoole --workers=16 --max-requests=500
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/laravel-octane.log
```

### Nginx Reverse Proxy

```nginx
server {
    listen 80;
    server_name example.com;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

> **Sumber**: Dokumentasi resmi Laravel Octane via Context7 (`/laravel/octane`). Panduan ini disusun untuk project Laravel 13 `b2-dev`.