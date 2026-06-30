<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

/**
 * Script untuk menguji koneksi ke Backblaze B2 (S3-compatible API).
 * Menggunakan GuzzleHttp (sudah tersedia di Laravel) + AWS Signature V4 manual.
 *
 * Cara pakai:
 *   php scripts/test_b2_connection.php
 */

require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

$key = $_ENV['B2_ACCESS_KEY_ID'] ?? '';
$secret = $_ENV['B2_SECRET_ACCESS_KEY'] ?? '';
$region = $_ENV['B2_REGION'] ?? '';
$endpoint = $_ENV['B2_ENDPOINT'] ?? '';
$bucket = $_ENV['B2_BUCKET'] ?? '';

if (empty($key) || empty($secret) || empty($bucket) || empty($region) || empty($endpoint)) {
    exit("❌ Missing required B2 environment variables.\n   Pastikan B2_ACCESS_KEY_ID, B2_SECRET_ACCESS_KEY, B2_BUCKET, B2_REGION, B2_ENDPOINT sudah diisi di .env\n");
}

echo "🔍 Menguji koneksi ke Backblaze B2...\n";
echo "   Endpoint : {$endpoint}\n";
echo "   Bucket   : {$bucket}\n";
echo "   Region   : {$region}\n\n";

/**
 * Build AWS Signature V4 untuk HEAD /{bucket}
 */
function buildAwsSignatureV4(
    string $method,
    string $url,
    string $accessKey,
    string $secretKey,
    string $region,
    string $service = 's3'
): array {
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'];
    $path = $parsedUrl['path'] ?? '/';

    $datetime = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');

    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        'x-amz-date' => $datetime,
    ];

    // Canonical request
    $canonicalHeaders = '';
    $signedHeadersList = [];
    ksort($headers);
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= strtolower($k).':'.$v."\n";
        $signedHeadersList[] = strtolower($k);
    }
    $signedHeaders = implode(';', $signedHeadersList);

    $canonicalRequest = implode("\n", [
        strtoupper($method),
        $path,
        '',  // query string
        $canonicalHeaders,
        $signedHeaders,
        'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    ]);

    // String to sign
    $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $datetime,
        $credentialScope,
        hash('sha256', $canonicalRequest),
    ]);

    // Signing key
    $signingKey = hash_hmac('sha256', 'aws4_request',
        hash_hmac('sha256', $service,
            hash_hmac('sha256', $region,
                hash_hmac('sha256', $date, 'AWS4'.$secretKey, true),
                true),
            true),
        true);

    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $authorization = sprintf(
        'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $accessKey,
        $credentialScope,
        $signedHeaders,
        $signature
    );

    return array_merge($headers, ['Authorization' => $authorization]);
}

try {
    $url = rtrim($endpoint, '/').'/'.ltrim($bucket, '/');
    $headers = buildAwsSignatureV4('HEAD', $url, $key, $secret, $region);

    $guzzle = new Client(['http_errors' => false, 'timeout' => 10]);

    $response = $guzzle->request('HEAD', $url, ['headers' => $headers]);

    $status = $response->getStatusCode();

    if ($status === 200) {
        echo "✅ Koneksi berhasil! Bucket '{$bucket}' dapat diakses.\n";
    } elseif ($status === 301 || $status === 302) {
        echo "↩️  Redirect ({$status}): endpoint mungkin perlu disesuaikan.\n";
        echo '   Location: '.$response->getHeaderLine('Location')."\n";
    } elseif ($status === 403) {
        echo "⚠️  Autentikasi gagal (403 Forbidden).\n";
        echo "   Periksa B2_ACCESS_KEY_ID dan B2_SECRET_ACCESS_KEY di .env\n";
    } elseif ($status === 404) {
        echo "⚠️  Bucket tidak ditemukan (404 Not Found).\n";
        echo "   Periksa B2_BUCKET dan B2_ENDPOINT di .env\n";
    } else {
        echo "⚠️  Response HTTP {$status}. Periksa konfigurasi B2 di .env\n";
    }
} catch (ConnectException $e) {
    echo "❌ Koneksi gagal: tidak dapat terhubung ke endpoint.\n";
    echo '   Pesan: '.$e->getMessage()."\n";
    echo "   Pastikan B2_ENDPOINT benar dan jaringan tersedia.\n";
} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
}
