<?php

namespace App\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class QrCodeTemporaryFileService
{
    public const Disk = 'local';

    public const Directory = 'qr-codes-tmp';

    public const ExpiryHours = 24;

    /**
     * @return array{png_filename:string,jpg_filename:string,preview_data_uri:string}
     */
    public function generate(string $content): array
    {
        $this->cleanupExpiredFiles();

        $token = (string) Str::uuid();
        $storage = Storage::disk($this->disk());
        $storage->makeDirectory($this->directory());

        $pngFilename = $token.'.png';
        $jpgFilename = $token.'.jpg';

        $storage->put($this->path($pngFilename), $this->renderQrCode($content, 'png'));
        $storage->put($this->path($jpgFilename), $this->renderQrCode($content, 'jpg'));

        return [
            'png_filename' => $pngFilename,
            'jpg_filename' => $jpgFilename,
            'preview_data_uri' => 'data:image/png;base64,'.base64_encode((string) $storage->get($this->path($pngFilename))),
        ];
    }

    public function delete(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        if (! $this->isValidFilename($filename)) {
            return;
        }

        Storage::disk($this->disk())->delete($this->path($filename));
    }

    /**
     * @param  array<int, string|null>  $filenames
     */
    public function deleteMany(array $filenames): void
    {
        foreach ($filenames as $filename) {
            $this->delete($filename);
        }
    }

    public function cleanupExpiredFiles(): int
    {
        $storage = Storage::disk($this->disk());
        $directory = $this->directory();

        if (! $storage->directoryExists($directory)) {
            return 0;
        }

        $cutoff = Carbon::now()->subHours($this->expiryHours())->getTimestamp();
        $deletedCount = 0;

        foreach ($storage->allFiles($directory) as $path) {
            if ($storage->lastModified($path) > $cutoff) {
                continue;
            }

            if ($storage->delete($path)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    public function disk(): string
    {
        return self::Disk;
    }

    public function directory(): string
    {
        return self::Directory;
    }

    public function expiryHours(): int
    {
        return self::ExpiryHours;
    }

    public function path(string $filename): string
    {
        if (! $this->isValidFilename($filename)) {
            throw new RuntimeException('Nama file QR code temporary tidak valid.');
        }

        return trim($this->directory(), '/').'/'.$filename;
    }

    public function mimeType(string $filename): string
    {
        return str_ends_with(Str::lower($filename), '.png') ? 'image/png' : 'image/jpeg';
    }

    private function isValidFilename(string $filename): bool
    {
        return preg_match('/\A[0-9a-f-]+\.(png|jpg)\z/i', $filename) === 1;
    }

    private function renderQrCode(string $content, string $format): string
    {
        $compressionQuality = $format === 'png' ? 9 : 90;

        return (new Writer(new GDLibRenderer(size: 800, margin: 4, imageFormat: $format, compressionQuality: $compressionQuality)))
            ->writeString($content);
    }
}
