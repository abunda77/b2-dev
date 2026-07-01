<?php

namespace App\Console\Commands;

use App\Services\QrCodeTemporaryFileService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('livewire:clear-tmp {--hours=24 : Hapus file yang lebih tua dari jumlah jam ini} {--all : Hapus semua file temporary tanpa cek umur} {--dry-run : Tampilkan file yang akan dihapus tanpa menghapusnya}')]
#[Description('Menghapus file temporary upload Livewire secara manual')]
class CleanupLivewireTemporaryUploads extends Command
{
    public function __construct(private QrCodeTemporaryFileService $qrCodeTemporaryFileService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $disk = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default');
        $directory = trim((string) (config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp'), '/');
        $storage = Storage::disk($disk);

        if (! $storage->directoryExists($directory)) {
            $this->info("Direktori temporary [{$directory}] pada disk [{$disk}] tidak ditemukan.");
        } else {
            $paths = collect($storage->allFiles($directory));

            if ($paths->isEmpty()) {
                $this->info("Tidak ada file temporary di [{$directory}] pada disk [{$disk}].");
            } else {
                $deleteAll = (bool) $this->option('all');
                $dryRun = (bool) $this->option('dry-run');
                $hours = max(0, (int) $this->option('hours'));
                $cutoff = Carbon::now()->subHours($hours)->getTimestamp();

                $filesToDelete = $paths
                    ->filter(function (string $path) use ($storage, $deleteAll, $cutoff): bool {
                        if ($deleteAll) {
                            return true;
                        }

                        return $storage->lastModified($path) <= $cutoff;
                    })
                    ->values();

                if ($filesToDelete->isEmpty()) {
                    $this->info('Tidak ada file temporary yang memenuhi kriteria hapus.');
                } elseif ($dryRun) {
                    $this->table(['File'], $filesToDelete->map(fn (string $path): array => [$path])->all());
                    $this->info("Dry run selesai. {$filesToDelete->count()} file akan dihapus.");
                } else {
                    $deletedCount = 0;

                    foreach ($filesToDelete as $path) {
                        if ($storage->delete($path)) {
                            $deletedCount++;
                        }
                    }

                    $this->info("{$deletedCount} file temporary dihapus dari [{$directory}] pada disk [{$disk}].");
                }
            }
        }

        $qrDeletedCount = $this->qrCodeTemporaryFileService->cleanupExpiredFiles();
        $qrDisk = $this->qrCodeTemporaryFileService->disk();
        $qrDirectory = $this->qrCodeTemporaryFileService->directory();
        $this->info("{$qrDeletedCount} file QR code temporary dihapus dari [{$qrDirectory}] pada disk [{$qrDisk}].");

        return self::SUCCESS;
    }
}
