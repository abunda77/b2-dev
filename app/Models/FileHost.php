<?php

namespace App\Models;

use Database\Factories\FileHostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FileHost extends Model
{
    /** @use HasFactory<FileHostFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'original_name',
        'mime_type',
        'size',
        'path',
        'disk',
    ];

    /**
     * Build a public URL for a B2 storage path without instantiating
     * the AWS S3 client (~230ms saved per request on first call).
     */
    public static function b2Url(string $path): string
    {
        $base = config('filesystems.disks.b2.url');

        if ($base) {
            return rtrim((string) $base, '/').'/'.ltrim($path, '/');
        }

        return Storage::disk('b2')->url($path);
    }

    /**
     * Get the full URL for the file.
     */
    public function getUrlAttribute(): string
    {
        return self::b2Url($this->path);
    }

    /**
     * Format file size for display.
     */
    public function getSizeForHumansAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
