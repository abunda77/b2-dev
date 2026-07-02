<?php

namespace App\Models;

use Database\Factories\FakturFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Faktur extends Model
{
    /** @use HasFactory<FakturFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nomor_faktur',
        'nama',
        'nominal',
        'items',
        'terbilang',
        'memo',
        'paper_size',
        'logo_path',
        'pdf_path',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'items' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPdfUrlAttribute(): string
    {
        return Storage::disk('b2')->temporaryUrl($this->pdf_path, now()->addHours(3));
    }

    public function getNominalRupiahAttribute(): string
    {
        return 'Rp '.number_format((float) $this->nominal, 0, ',', '.');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('b2')->url($this->logo_path);
    }

    public function deleteAllFiles(): void
    {
        if ($this->pdf_path) {
            Storage::disk('b2')->delete($this->pdf_path);
        }
        if ($this->logo_path) {
            Storage::disk('b2')->delete($this->logo_path);
        }
    }
}
