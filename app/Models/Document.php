<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'filename',
        'disk_path',
        'source',
        'file_size',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the raw markdown content of this document.
     */
    public function getContent(): ?string
    {
        if ($this->source === 'project_root' || $this->source === 'docs_folder') {
            $fullPath = base_path($this->disk_path);

            return file_exists($fullPath) ? file_get_contents($fullPath) : null;
        }

        // Uploaded files are stored on the local disk
        return Storage::disk('local')->exists($this->disk_path)
            ? Storage::disk('local')->get($this->disk_path)
            : null;
    }

    /**
     * Get a human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    /**
     * Delete the uploaded file from storage (only for uploaded documents).
     */
    public function deleteFile(): void
    {
        if ($this->source === 'upload' && Storage::disk('local')->exists($this->disk_path)) {
            Storage::disk('local')->delete($this->disk_path);
        }
    }
}
