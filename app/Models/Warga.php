<?php

namespace App\Models;

use Database\Factories\WargaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Warga extends Model
{
    /** @use HasFactory<WargaFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nik',
        'nama',
        'alamat',
        'pas_foto',
        'dokumen',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'nik' => 'string',
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
     * Get the full URL for the pas foto.
     */
    public function getPasFotoUrlAttribute(): string
    {
        return self::b2Url($this->pas_foto);
    }

    /**
     * Get the full URL for the dokumen, or null if not set.
     */
    public function getDokumenUrlAttribute(): ?string
    {
        return $this->dokumen ? self::b2Url($this->dokumen) : null;
    }
}
