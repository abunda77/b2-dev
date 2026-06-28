<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestB2Connection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'b2:test
                            {--disk=b2 : Disk yang diuji (b2 atau r2)}
                            {--upload : Uji upload file kecil}
                            {--cleanup : Hapus file test setelah upload}';

    /**
     * The console command description.
     */
    protected $description = 'Menguji koneksi ke Backblaze B2 (atau disk S3-compatible lain) via Laravel Storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = $this->option('disk');

        $this->info("🔍 Menguji koneksi disk '{$disk}'...");
        $this->newLine();

        $diskConfig = config("filesystems.disks.{$disk}");

        if (! $diskConfig) {
            $this->error("Disk '{$disk}' tidak ditemukan di config/filesystems.php");

            return self::FAILURE;
        }

        $this->table([], [
            ['Endpoint', $diskConfig['endpoint'] ?? '-'],
            ['Bucket', $diskConfig['bucket'] ?? '-'],
            ['Region', $diskConfig['region'] ?? '-'],
            ['Key ID', substr((string) ($diskConfig['key'] ?? '-'), 0, 8).'...'],
        ]);
        $this->newLine();

        // --- Test 1: List files ---
        try {
            $files = Storage::disk($disk)->files('/');
            $this->line('✅ Koneksi berhasil! Bucket dapat diakses.');
            $this->line('   File di root bucket: '.count($files).' file(s)');
        } catch (\Exception $e) {
            $this->error('❌ Koneksi gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        // --- Test 2: Upload (optional) ---
        if ($this->option('upload')) {
            $this->newLine();
            $this->line('📤 Mengunggah file test...');

            $testPath = '_b2_connection_test/'.date('Ymd_His').'.txt';
            $content = 'B2 connection test - '.now()->toIso8601String();

            try {
                Storage::disk($disk)->put($testPath, $content, 'private');
                $this->line("✅ Upload berhasil: {$testPath}");

                // Verify file exists
                $exists = Storage::disk($disk)->exists($testPath);
                $this->line('✅ Verifikasi file: '.($exists ? 'ada' : 'tidak ditemukan'));

                if ($this->option('cleanup')) {
                    Storage::disk($disk)->delete($testPath);
                    $this->line("🗑️  File test dihapus: {$testPath}");
                }
            } catch (\Exception $e) {
                $this->error('❌ Upload gagal: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("✅ Disk '{$disk}' siap digunakan via Storage::disk('{$disk}')");

        return self::SUCCESS;
    }
}
