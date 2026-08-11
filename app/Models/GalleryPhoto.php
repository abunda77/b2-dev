<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryPhoto extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'original_path',
        'edited_path',
        'disk',
        'original_width',
        'original_height',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'original_width' => 'integer',
        'original_height' => 'integer',
        'file_size' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * URL publik dari foto yang aktif (edited jika ada, otherwise original).
     */
    public function getActiveUrlAttribute(): string
    {
        $path = $this->edited_path ?? $this->original_path;

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * URL publik foto original.
     */
    public function getOriginalUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->original_path);
    }

    /**
     * URL publik foto edited (null jika belum pernah diedit).
     */
    public function getEditedUrlAttribute(): ?string
    {
        if (! $this->edited_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->edited_path);
    }

    /**
     * Human-readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
