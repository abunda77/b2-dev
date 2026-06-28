<?php

namespace App\Models;

use Database\Factories\WargaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Get the full URL for the pas foto.
     */
    public function getPasFotoUrlAttribute(): string
    {
        return \Storage::url($this->pas_foto);
    }

    /**
     * Get the full URL for the dokumen, or null if not set.
     */
    public function getDokumenUrlAttribute(): ?string
    {
        return $this->dokumen ? \Storage::url($this->dokumen) : null;
    }
}
