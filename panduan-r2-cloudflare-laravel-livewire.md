# Panduan Penggunaan Cloudflare R2 untuk Penyimpanan Asset
## Laravel 13 + TailwindCSS + Livewire

---

## 1. Persiapan Cloudflare R2

### 1.1 Buat Bucket R2
1. Login ke [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Sidebar kiri → **R2**
3. Klik **Create bucket**
4. Isi:
   - **Bucket name**: `myapp-assets` (nama unik)
   - **Region**: Pilih `APAC` (terdekat)

### 1.2 Generate API Token
1. Dashboard R2 → tab **Manage R2 API Tokens**
2. Klik **Create API Token**
3. Permission: **Object Read & Write**
4. Pilih bucket yang baru dibuat
5. Klik **Create**
6. **Simpan**:
   - `Access Key ID`
   - `Secret Access Key`
   - `R2 Endpoint` → format: `https://<account_id>.r2.cloudflarestorage.com`

### 1.3 Public Access (Opsional)
1. Buka bucket → **Settings** → **Public Access**
2. Aktifkan **Allow Public Access**
3. Klik **Connect Domain** → tambahkan custom domain (misal `assets.example.com`)

---

## 2. Konfigurasi Laravel

### 2.1 Install Flysystem S3 Adapter
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

### 2.2 Tambah R2 Disk di `config/filesystems.php`
```php
'disks' => [

    // ... disk lokal

    'r2' => [
        'driver' => 's3',
        'key' => env('R2_ACCESS_KEY_ID'),
        'secret' => env('R2_SECRET_ACCESS_KEY'),
        'region' => env('R2_REGION', 'auto'),
        'bucket' => env('R2_BUCKET'),
        'endpoint' => env('R2_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'url' => env('R2_URL'),
        'throw' => false,
    ],
],
```

### 2.3 Tambah Environment Variables di `.env`
```env
R2_ACCESS_KEY_ID=access_key_kamu
R2_SECRET_ACCESS_KEY=secret_key_kamu
R2_BUCKET=myapp-assets
R2_REGION=auto
R2_ENDPOINT=https://xxxxxxxxx.r2.cloudflarestorage.com
R2_URL=https://assets.example.com
```

---

## 3. Setup Livewire Upload File

### 3.1 Pastikan Livewire Sudah Terinstall
```bash
composer require livewire/livewire "^3.0"
php artisan livewire:publish --config
```

### 3.2 Komponen Upload File

Buat komponen:
```bash
php artisan make:livewire R2ImageUpload
```

**`app/Livewire/R2ImageUpload.php`**
```php
<?php

namespace App\Livewire;

use Livewire\WithFileUploads;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class R2ImageUpload extends Component
{
    use WithFileUploads;

    public $file;
    public $fileName;
    public $uploadedUrl;

    protected $rules = [
        'file' => 'required|file|max:10240', // max 10MB
    ];

    public function upload()
    {
        $this->validate();

        try {
            $extension = $this->file->getClientOriginalExtension();
            $directory = match (strtolower($extension)) {
                'jpg','jpeg','png','gif','webp','svg' => 'images',
                'css','js' => 'assets',
                'pdf','doc','docx' => 'documents',
                'mp4','mov','avi' => 'videos',
                default => 'others',
            };

            $this->fileName = time() . '_' . $this->file->getClientOriginalName();
            $path = $this->file->storeAs($directory, $this->fileName, 'r2');
            $this->uploadedUrl = Storage::disk('r2')->url($path);

            session()->flash('success', 'File berhasil diupload ke R2!');

        } catch (\Exception $e) {
            session()->flash('error', 'Gagal upload: ' . $e->getMessage());
        }
    }

    public function deleteFile($path)
    {
        if (Storage::disk('r2')->exists($path)) {
            Storage::disk('r2')->delete($path);
            $this->uploadedUrl = null;
            session()->flash('success', 'File berhasil dihapus!');
        }
    }

    public function render()
    {
        return view('livewire.r2-image-upload');
    }
}
```

**`resources/views/livewire/r2-image-upload.blade.php`**
```blade
<div class="bg-white rounded-lg shadow-md p-6 max-w-xl mx-auto">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Upload File ke R2</h2>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Upload Form -->
    <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2">Pilih File</label>
        <input
            type="file"
            wire:model="file"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        @error('file')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    @if($file)
        <div class="mb-4 p-3 bg-blue-50 rounded text-sm text-blue-700">
            File terpilih: <strong>{{ $file->getClientOriginalName() }}</strong>
        </div>
    @endif

    <button
        wire:click="upload"
        wire:loading.attr="disabled"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition disabled:opacity-50"
    >
        <span wire:loading.remove>Upload ke R2</span>
        <span wire:loading>Uploading...</span>
    </button>

    <!-- Hasil Upload -->
    @if($uploadedUrl)
        <div class="mt-6 p-4 bg-gray-50 rounded-md">
            <h3 class="font-semibold text-gray-800 mb-2">File Berhasil Diupload!</h3>
            <p class="text-sm break-all mb-2">{{ $uploadedUrl }}</p>

            {{-- Preview Gambar --}}
            @php $isImage = in_array(pathinfo($fileName, PATHINFO_EXTENSION), ['jpg','jpeg','png','gif','webp','svg']); @endphp
            @if($isImage)
                <img src="{{ $uploadedUrl }}" alt="Preview" class="max-w-full h-auto rounded-md border" />
            @endif

            <button
                wire:click="deleteFile('{{ str_replace(Storage::disk('r2')->url(''), '', $uploadedUrl) }}')"
                class="mt-3 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md text-sm"
            >
                Hapus File
            </button>
        </div>
    @endif
</div>
```

### 3.3 Register Route
```php
// routes/web.php
Route::get('/r2-upload', \App\Livewire\R2ImageUpload::class)->name('r2.upload');
```

---

## 4. Full CRUD dengan R2 Storage

### 4.1 Buat Model & Migration
```bash
php artisan make:model Article -mc
```

**Migration** `database/migrations/xxxx_create_articles_table.php`:
```php
public function up(): void
{
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body');
        $table->string('cover_image')->nullable();   // path gambar di R2
        $table->string('attachment')->nullable();     // path dokumen di R2
        $table->timestamps();
    });
}
```
```bash
php artisan migrate
```

**Model** `app/Models/Article.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $fillable = [
        'title', 'body', 'cover_image', 'attachment',
    ];

    // Accessor: URL gambar cover
    public function getCoverUrlAttribute(): string
    {
        if (!$this->cover_image) {
            return asset('img/placeholder.png');
        }
        return Storage::disk('r2')->url($this->cover_image);
    }

    // Accessor: URL attachment
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment) {
            return null;
        }
        return Storage::disk('r2')->url($this->attachment);
    }
}
```

### 4.2 Komponen Livewire CRUD
```bash
php artisan make:livewire ArticleCrud
```

**`app/Livewire/ArticleCrud.php`**
```php
<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\WithFileUploads;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class ArticleCrud extends Component
{
    use WithFileUploads;

    // Daftar
    public $articles;

    // Form
    public $article_id;
    public $title;
    public $body;
    public $cover_image;
    public $attachment;
    public $existing_cover;
    public $existing_attachment;
    public $isEditing = false;

    protected $rules = [
        'title' => 'required|string|max:255',
        'body' => 'required|string',
        'cover_image' => 'nullable|image|max:5120',
        'attachment' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
    ];

    public function mount()
    {
        $this->articles = Article::latest()->get();
    }

    // --- Simpan / Update ---
    public function save()
    {
        $this->validate();

        try {
            // Handle cover image
            $coverPath = $this->existing_cover;
            if ($this->cover_image) {
                if ($this->existing_cover) {
                    Storage::disk('r2')->delete($this->existing_cover);
                }
                $coverPath = $this->cover_image->store('articles/covers', 'r2');
            }

            // Handle attachment
            $attachmentPath = $this->existing_attachment;
            if ($this->attachment) {
                if ($this->existing_attachment) {
                    Storage::disk('r2')->delete($this->existing_attachment);
                }
                $attachmentPath = $this->attachment->store('articles/attachments', 'r2');
            }

            if ($this->isEditing) {
                Article::find($this->article_id)->update([
                    'title' => $this->title,
                    'body' => $this->body,
                    'cover_image' => $coverPath,
                    'attachment' => $attachmentPath,
                ]);
                session()->flash('success', 'Artikel berhasil diupdate!');
            } else {
                Article::create([
                    'title' => $this->title,
                    'body' => $this->body,
                    'cover_image' => $coverPath,
                    'attachment' => $attachmentPath,
                ]);
                session()->flash('success', 'Artikel berhasil dibuat!');
            }

            $this->resetForm();
            $this->articles = Article::latest()->get();

        } catch (\Exception $e) {
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    // --- Edit ---
    public function edit($id)
    {
        $article = Article::find($id);

        $this->article_id = $article->id;
        $this->title = $article->title;
        $this->body = $article->body;
        $this->existing_cover = $article->cover_image;
        $this->existing_attachment = $article->attachment;
        $this->isEditing = true;
    }

    // --- Delete ---
    public function delete($id)
    {
        $article = Article::find($id);

        if ($article->cover_image) {
            Storage::disk('r2')->delete($article->cover_image);
        }
        if ($article->attachment) {
            Storage::disk('r2')->delete($article->attachment);
        }

        $article->delete();

        $this->articles = Article::latest()->get();
        session()->flash('success', 'Artikel berhasil dihapus!');
    }

    // --- Reset ---
    public function resetForm()
    {
        $this->reset([
            'article_id', 'title', 'body',
            'cover_image', 'attachment',
            'existing_cover', 'existing_attachment',
            'isEditing',
        ]);
    }

    public function render()
    {
        return view('livewire.article-crud')
            ->layout('layouts.app');
    }
}
```

### 4.3 View CRUD

**`resources/views/livewire/article-crud.blade.php`**
```blade
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Article CRUD (R2 Storage)</h1>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        {{-- ============ FORM ============ --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                {{ $isEditing ? 'Edit' : 'Tambah' }} Artikel
            </h2>

            <form wire:submit.prevent="save" class="space-y-4">

                {{-- Title --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Judul *</label>
                    <input
                        type="text"
                        wire:model="title"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        placeholder="Masukkan judul artikel"
                    />
                    @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Body --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Isi *</label>
                    <textarea
                        wire:model="body"
                        rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        placeholder="Tulis isi artikel..."
                    ></textarea>
                    @error('body') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Cover Image --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Cover Image</label>
                    @if($existing_cover)
                        <div class="flex items-center gap-4 mb-2">
                            <img src="{{ Storage::disk('r2')->url($existing_cover) }}"
                                 class="w-24 h-24 object-cover rounded-md border" />
                            <button type="button"
                                    wire:click="$set('existing_cover', null)"
                                    class="text-red-600 hover:text-red-800 text-sm">Hapus</button>
                        </div>
                    @endif
                    <input
                        type="file"
                        wire:model="cover_image"
                        accept="image/*"
                        class="w-full text-sm"
                    />
                    @error('cover_image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Attachment --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Attachment (PDF/DOC)</label>
                    @if($existing_attachment)
                        <div class="mb-2">
                            <a href="{{ Storage::disk('r2')->url($existing_attachment) }}"
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 underline text-sm">Lihat dokumen</a>
                            <button type="button"
                                    wire:click="$set('existing_attachment', null)"
                                    class="ml-4 text-red-600 hover:text-red-800 text-sm">Hapus</button>
                        </div>
                    @endif
                    <input
                        type="file"
                        wire:model="attachment"
                        accept=".pdf,.doc,.docx"
                        class="w-full text-sm"
                    />
                    @error('attachment') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Buttons --}}
                <div class="flex gap-2 pt-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition disabled:opacity-50">
                        <span wire:loading.remove>{{ $isEditing ? 'Update' : 'Simpan' }}</span>
                        <span wire:loading>Menyimpan...</span>
                    </button>
                    @if($isEditing)
                        <button type="button"
                                wire:click="resetForm"
                                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md transition">
                            Batal
                        </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- ============ LIST ============ --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Daftar Artikel ({{ count($articles) }})</h2>

            @forelse($articles as $article)
                <div class="border border-gray-200 rounded-md p-4 mb-3">
                    <div class="flex gap-4">
                        {{-- Cover --}}
                        @if($article->cover_image)
                            <img src="{{ $article->cover_url }}"
                                 class="w-20 h-20 object-cover rounded-md shrink-0" />
                        @else
                            <div class="w-20 h-20 bg-gray-200 rounded-md shrink-0 flex items-center justify-center">
                                <span class="text-gray-400 text-xs">No Image</span>
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-800 truncate">{{ $article->title }}</h3>
                            <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ Str::limit($article->body, 80) }}</p>

                            @if($article->attachment)
                                <a href="{{ $article->attachment_url }}"
                                   target="_blank"
                                   class="inline-block mt-2 text-sm text-blue-600 hover:text-blue-800 underline">
                                    Download Attachment
                                </a>
                            @endif
                        </div>

                        <div class="flex flex-col gap-1 shrink-0">
                            <button wire:click="edit({{ $article->id }})"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-bold py-1 px-3 rounded transition">
                                Edit
                            </button>
                            <button wire:click="delete({{ $article->id }})"
                                    class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold py-1 px-3 rounded transition">
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">Belum ada artikel.</div>
            @endforelse
        </div>

    </div>
</div>
```

### 4.4 Route
```php
// routes/web.php
Route::get('/articles', \App\Livewire\ArticleCrud::class)->name('articles');
```

---

## 5. Layout Utama

**`resources/views/layouts/app.blade.php`**
```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel R2 Demo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <span class="text-xl font-bold text-gray-800">🔷 Laravel R2</span>
            <div class="space-x-4">
                <a href="{{ route('r2.upload') }}" class="text-gray-700 hover:text-blue-600 transition">Upload</a>
                <a href="{{ route('articles') }}"  class="text-gray-700 hover:text-blue-600 transition">Articles</a>
            </div>
        </div>
    </nav>

    <main>{{ $slot }}</main>

    @livewireScripts
</body>
</html>
```

---

## 6. Best Practices

### 6.1 Organisir Folder di R2
```
bucket/
├── images/
├── documents/
├── assets/       (css, js)
├── uploads/
└── backups/
```

### 6.2 Gunakan Signed URL untuk File Private
```php
// URL valid 1 jam
$url = Storage::disk('r2')->temporaryUrl(
    $path,
    now()->addHour()
);
```

### 6.3 Hapus File Lama Saat Update
Selalu hapus file lama sebelum mengganti dengan yang baru (lihat contoh `ArticleCrud.php` di atas).

### 6.4 Validasi File
```php
$rules = [
    'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|dimensions:max_width=4000,max_height=4000|max:5120',
    'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:20480',
];
```

### 6.5 Error Handling
```php
try {
    Storage::disk('r2')->put($path, $contents);
} catch (\Exception $e) {
    \Log::error('R2 upload failed: ' . $e->getMessage());
    session()->flash('error', 'Upload gagal. Silakan coba lagi.');
}
```

### 6.6 Gunakan Background Job untuk File Besar
```bash
php artisan make:job ProcessR2Upload
```
```php
class ProcessR2Upload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $path,
        private string $contents
    ) {}

    public function handle(): void
    {
        Storage::disk('r2')->put($this->path, $this->contents);
    }
}
```

---

## 7. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| **File tidak muncul** | Pastikan `R2_URL` di `.env` benar |
| **Upload timeout** | Tambahkan `'timeout' => 300` ke config disk R2 |
| **CORS error** | Aktifkan CORS di bucket settings Cloudflare |
| **404 saat akses file** | Aktifkan Public Access di bucket |
| **"Unable to resolve AWS endpoint"** | Cek kembali `R2_ENDPOINT` di `.env` |

---

## 8. Ceklis Final

- [ ] Bucket R2 dibuat
- [ ] API token dibuat & disimpan
- [ ] `league/flysystem-aws-s3-v3` terinstall
- [ ] `config/filesystems.php` dikonfigurasi untuk disk `r2`
- [ ] `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` di `.env`
- [ ] Public Access diaktifkan (jika file perlu diakses publik)
- [ ] Custom domain disetup (opsional)
- [ ] Komponen Livewire berjalan normal
- [ ] Upload & delete file berfungsi

---

## Referensi
- [Cloudflare R2 Docs](https://developers.cloudflare.com/r2/)
- [Laravel File Storage](https://laravel.com/docs/13.x/filesystem)
- [Livewire File Uploads](https://livewire.laravel.com/docs/uploads)