<?php

/**
 * Script untuk mengatur CORS di bucket Backblaze B2
 * agar browser bisa upload langsung (direct upload) tanpa CORS error.
 *
 * Cara pakai:
 *   php scripts/configure_b2_cors.php
 *   php scripts/configure_b2_cors.php http://localhost:8000 https://domain-anda.com
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Services\B2UploadService;
use Illuminate\Contracts\Console\Kernel;

$service = new B2UploadService;

$allowedOrigins = array_slice($argv, 1);

if (empty($allowedOrigins)) {
    $appUrl = config('app.url');
    $allowedOrigins = $appUrl ? [$appUrl] : ['http://localhost:8000'];
}

echo "🔧 Mengatur CORS di bucket Backblaze B2...\n";
echo '   Bucket         : '.config('filesystems.disks.b2.bucket')."\n";
echo '   Allowed Origins: '.implode(', ', $allowedOrigins)."\n\n";

try {
    $before = $service->getCorsConfig();
    if ($before) {
        echo "📋 Konfigurasi CORS sebelumnya:\n";
        foreach ($before as $rule) {
            echo '   Origins: '.implode(', ', $rule['AllowedOrigins'] ?? [])."\n";
            echo '   Methods: '.implode(', ', $rule['AllowedMethods'] ?? [])."\n\n";
        }
    } else {
        echo "📋 Belum ada konfigurasi CORS.\n\n";
    }

    $service->configureCors($allowedOrigins);

    echo "✅ CORS berhasil dikonfigurasi!\n";

    $after = $service->getCorsConfig();
    if ($after) {
        echo "\n📋 Konfigurasi CORS saat ini:\n";
        foreach ($after as $rule) {
            echo '   Origins: '.implode(', ', $rule['AllowedOrigins'] ?? [])."\n";
            echo '   Methods: '.implode(', ', $rule['AllowedMethods'] ?? [])."\n";
        }
    }
} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
}
