# Panduan Penggunaan Backblaze B2 untuk Penyimpanan Asset
## Laravel 13 + TailwindCSS + Livewire

---

## 1. Persiapan Backblaze B2

### 1.1 Daftar & Buat Bucket
1. Buka [Backblaze B2 Console](https://secure.backblaze.com/b2_buckets.htm)
2. Klik **Create a Bucket**
3. Isi:
   - **Bucket name**: `myapp-assets` (harus unik secara global)
   - **Bucket type**: Pilih `Private` (atau `Public` jika file perlu diakses langsung via URL)
   - **Encryption**: Default SSE-B2 (gratis)
   - Klik **Create a Bucket**

> **Catatan**: Backblaze tidak menyediakan custom domain langsung via B2. Untuk akses publik dengan domain sendiri, Anda perlu setup CDN (Cloudflare/BunnyCDN) atau reverse proxy. Alternatif backend akan dijelaskan di bagian 7.

### 1.2 Generate Application Key
1. Buka [App Keys](https://secure.backblaze.com/app_keys.htm)
2. Klik **Add a New Application Key**
3. Isi:
   - **Name of Key**: `myapp-laravel-key`
   - **Allow access to Bucket(s)**: Pilih bucket `myapp-assets`
   - **Type of Access**: `Read and Write`
   - Klik **Create New Key**
4. **Simpan** (hanya tampil sekali!):
   - `keyID` → misal: ``
   - `applicationKey` → misal: ``
   - `S3 Endpoint` → misal: ``
   - `Endpoint` → misal: ``

### 1.3 Catat Informasi Penting
| Variabel | Contoh | Keterangan |
|----------|--------|------------|
| `keyID` | `` | Application Key ID |
| `applicationKey` | `` | Application Key (secret) |
| `endpoint` | `` | S3-compatible endpoint |
| `region` | `` | Region dari endpoint |
| `bucket` | `` | Nama bucket |

---

## 2. Konfigurasi Laravel

### 2.1 Install S3 Adapter
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

### 2.2 Tambah Disk B2 di `config/filesystems.php`
```php
'disks' => [

    // ... disk lainnya ...

    'b2' => [
        'driver' => 's3',
        'key' => env('B2_ACCESS_KEY_ID'),
        'secret' => env('B2_SECRET_ACCESS_KEY'),
        'region' => env('B2_REGION'),
        'bucket' => env('B2_BUCKET'),
        'endpoint' => env('B2_ENDPOINT'),
        'url' => env('B2_URL'),
        'use_path_style_endpoint' => true,
        'throw' => false,
    ],
],
```

### 2.3 Environment Variables di `.env`
```env
# Backblaze B2 Configuration
B2_ACCESS_KEY_ID=
B2_SECRET_ACCESS_KEY=
B2_BUCKET=
B2_REGION=
B2_ENDPOINT=
B2_URL=
```

### 2.4 Clear Config Cache
```bash
php artisan config:clear
php artisan config:cache
```

---

## 3. Setup Livewire Upload File

### 3.1 Pastikan Livewire Terinstall
```bash
composer require livewire/livewire "^3.0"
php artisan livewire:publish --config
```

### 3.2 Komponen Upload File

Buat komponen:
```bash
php artisan make:livewire B2FileUpload
```

**`app/Livewire/B2FileUpload.php`**
```php
<?php

namespace App\Livewire;

use Livewire\WithFileUploads;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class B2FileUpload extends Component
{
    use WithFileUploads;

    public $file;
    public $uploadedPath;
    public $uploadedUrl;
    public $fileName;
    public $mimeType;

    protected $rules = [
        'file' => 'required|file|max:20480', // max 20MB per file
    ];

    public function upload()
    {
        $this->validate();

        try {
            // Generate nama file unik
            $this->fileName = time() . '_' . preg_replace(
                '/[^a-zA-Z0-9._-]/',
                '_',
                $this->file->getClientOriginalName()
            );

            // Tentukan direktori berdasarkan ekstensi
            $ext = strtolower($this->file->getClientOriginalExtension());
            $dir = $this->directoryFor($ext);

            // Upload ke B2
            $this->uploadedPath = $this->file->storeAs(
                $dir,
                $this->fileName,
                'b2'
            );

            // Dapatkan URL (untuk bucket public)
            $this->uploadedUrl = Storage::disk('b2')->url($this->uploadedPath);
            $this->mimeType = Storage::disk('b2')->mimeType($this->uploadedPath);

            session()->flash('success', 'File berhasil diupload ke Backblaze B2!');

        } catch (\Exception $e) {
            session()->flash('error', 'Upload gagal: ' . $e->getMessage());
            \Log::error('B2 Upload Error: ' . $e->getMessage());
        }
    }

    /**
     * Hapus file dari B2
     */
    public function delete($path)
    {
        try {
            if (Storage::disk('b2')->exists($path)) {
                Storage::disk('b2')->delete($path);
                $this->reset(['uploadedPath', 'uploadedUrl', 'fileName', 'mimeType']);
                session()->flash('success', 'File berhasil dihapus!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /**
     * Mapping ekstensi → direktori
     */
    private function directoryFor(string $ext): string
    {
        return match (true) {
            in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp']) => 'images',
            in_array($ext, ['css'])                                     => 'css',
            in_array($ext, ['js','mjs'])                               => 'js',
            in_array($ext, ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv']) => 'documents',
            in_array($ext, ['mp4','avi','mov','wmv','mkv','webm'])     => 'videos',
            in_array($ext, ['mp3','wav','ogg','aac','flac'])           => 'audio',
            in_array($ext, ['zip','rar','7z','tar','gz'])              => 'archives',
            default                                                     => 'others',
        };
    }

    public function render()
    {
        return view('livewire.b2-file-upload');
    }
}
```

### 3.3 View Upload File

**`resources/views/livewire/b2-file-upload.blade.php`**
```blade
<div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        {{-- Icon Backblaze --}}
        <svg class="w-8 h-8 text-red-600" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
            <path d="M12 6l-4 4h8l-4-4z"/>
        </svg>
        <h2 class="text-2xl font-bold text-gray-800">Upload File ke Backblaze B2</h2>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if(!$uploadedPath)
        {{-- Form Upload --}}
        <div class="mb-5">
            <label class="block text-gray-700 text-sm font-bold mb-2">Pilih File</label>
            <input
                type="file"
                wire:model="file"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
            />
            @error('file')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        @if($file)
            <div class="mb-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                <strong>File dipilih:</strong> {{ $file->getClientOriginalName() }}
                <span class="text-gray-500 ml-2">
                    ({{ number_format($file->getSize() / 1024, 1) }} KB)
                </span>
            </div>

            {{-- Progress Upload --}}
            <div class="mb-4" wire:loading wire:target="upload">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm text-gray-600">Mengupload ke B2...</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-red-600 h-2 rounded-full animate-pulse w-full"></div>
                </div>
            </div>
        @endif

        <button
            wire:click="upload"
            wire:loading.attr="disabled"
            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-4 rounded-md transition duration-200 disabled:opacity-50"
        >
            <span wire:loading.remove>Upload ke Backblaze B2</span>
            <span wire:loading>⏳ Mengupload...</span>
        </button>
    @endif

    {{-- Hasil Upload --}}
    @if($uploadedUrl)
        <div class="mt-6 p-5 bg-gray-50 rounded-md border border-gray-200">
            <h3 class="font-bold text-gray-800 mb-3 text-lg">✅ Upload Berhasil</h3>

            <div class="space-y-2 text-sm">
                <div class="flex gap-2">
                    <span class="text-gray-500 w-24 shrink-0">Nama:</span>
                    <span class="font-mono text-gray-800 break-all">{{ $fileName }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-gray-500 w-24 shrink-0">Path:</span>
                    <span class="font-mono text-gray-800 break-all">{{ $uploadedPath }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-gray-500 w-24 shrink-0">Type:</span>
                    <span class="text-gray-800">{{ $mimeType }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-gray-500 w-24 shrink-0">URL:</span>
                    <a href="{{ $uploadedUrl }}" target="_blank"
                       class="text-blue-600 hover:text-blue-800 underline break-all font-mono">
                        {{ $uploadedUrl }}
                    </a>
                </div>
            </div>

            {{-- Preview Gambar --}}
            @if(str_starts_with($mimeType, 'image/'))
                <div class="mt-4">
                    <p class="text-xs text-gray-500 mb-1">Preview:</p>
                    <img src="{{ $uploadedUrl }}" alt="Preview"
                         class="max-w-full h-auto max-h-64 rounded-md border" />
                </div>
            @endif

            {{-- Preview Audio --}}
            @if(str_starts_with($mimeType, 'audio/'))
                <div class="mt-4">
                    <audio controls class="w-full">
                        <source src="{{ $uploadedUrl }}" type="{{ $mimeType }}" />
                    </audio>
                </div>
            @endif

            {{-- Preview Video --}}
            @if(str_starts_with($mimeType, 'video/'))
                <div class="mt-4">
                    <video controls class="max-w-full rounded-md border" style="max-height:300px;">
                        <source src="{{ $uploadedUrl }}" type="{{ $mimeType }}" />
                    </video>
                </div>
            @endif

            {{-- Tombol Aksi --}}
            <div class="flex gap-2 mt-5">
                <button
                    wire:click="delete('{{ $uploadedPath }}')"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md text-sm transition"
                >
                    🗑 Hapus File
                </button>
                <button
                    wire:click="$set('uploadedPath', null)"
                    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md text-sm transition"
                >
                    📤 Upload Baru
                </button>
            </div>
        </div>
    @endif
</div>
```

### 3.4 Route
```php
// routes/web.php
Route::get('/b2-upload', \App\Livewire\B2FileUpload::class)->name('b2.upload');
```

---

## 4. Full CRUD dengan B2 Storage

### 4.1 Model & Migration
```bash
php artisan make:model Product -m
```

**Migration** `database/migrations/xxxx_create_products_table.php`:
```php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('image_path')->nullable();    // path gambar di B2
        $table->decimal('price', 12, 2);
        $table->integer('stock');
        $table->string('brochure_path')->nullable();  // path dokumen di B2
        $table->string('gallery_files')->nullable(); // JSON array path gambar gallery
        $table->timestamps();
    });
}
```
```bash
php artisan migrate
```

**Model** `app/Models/Product.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'name', 'description', 'image_path',
        'price', 'stock', 'brochure_path', 'gallery_files',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'gallery_files' => 'array',
    ];

    // --- Accessors ---

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        return Storage::disk('b2')->url($this->image_path);
    }

    public function getBrochureUrlAttribute(): ?string
    {
        if (!$this->brochure_path) return null;
        return Storage::disk('b2')->url($this->brochure_path);
    }

    /**
     * Mendapatkan array URL gallery
     */
    public function getGalleryUrlsAttribute(): array
    {
        if (!$this->gallery_files) return [];
        return array_map(
            fn($path) => Storage::disk('b2')->url($path),
            $this->gallery_files
        );
    }

    // --- Helper: hapus semua file terkait ---

    public function deleteAllFiles(): void
    {
        if ($this->image_path) {
            Storage::disk('b2')->delete($this->image_path);
        }
        if ($this->brochure_path) {
            Storage::disk('b2')->delete($this->brochure_path);
        }
        if ($this->gallery_files) {
            foreach ($this->gallery_files as $path) {
                Storage::disk('b2')->delete($path);
            }
        }
    }
}
```

### 4.2 Komponen Livewire CRUD
```bash
php artisan make:livewire ProductCrud
```

**`app/Livewire/ProductCrud.php`**
```php
<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\WithFileUploads;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class ProductCrud extends Component
{
    use WithFileUploads;

    // --- Daftar ---
    public $products;

    // --- Form fields ---
    public $product_id;
    public $name;
    public $description;
    public $price;
    public $stock;
    public $image;
    public $brochure;
    public $gallery = [];

    // --- Existing file paths ---
    public $existing_image;
    public $existing_brochure;
    public $existing_gallery = [];

    // --- Mode ---
    public $isEditing = false;

    // --- Validation ---
    protected $rules = [
        'name'            => 'required|string|max:255',
        'description'     => 'nullable|string|max:5000',
        'price'           => 'required|numeric|min:0',
        'stock'           => 'required|integer|min:0',
        'image'           => 'nullable|image|max:5120',
        'brochure'        => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        'gallery.*'       => 'nullable|image|max:5120',
    ];

    protected $messages = [
        'image.max'    => 'Ukuran gambar maksimal 5MB.',
        'brochure.max' => 'Ukuran dokumen maksimal 10MB.',
    ];

    public function mount()
    {
        $this->loadProducts();
    }

    public function loadProducts()
    {
        $this->products = Product::latest()->get();
    }

    // --- CREATE / UPDATE ---

    public function save()
    {
        $this->validate();

        try {
            // Handle single image
            $imagePath = $this->existing_image;
            if ($this->image) {
                if ($this->existing_image) {
                    Storage::disk('b2')->delete($this->existing_image);
                }
                $imagePath = $this->image->store('products/images', 'b2');
            }

            // Handle brochure
            $brochurePath = $this->existing_brochure;
            if ($this->brochure) {
                if ($this->existing_brochure) {
                    Storage::disk('b2')->delete($this->existing_brochure);
                }
                $brochurePath = $this->brochure->store('products/brochures', 'b2');
            }

            // Handle gallery (multiple images)
            $galleryPaths = $this->existing_gallery;
            if (!empty($this->gallery)) {
                foreach ($this->gallery as $galleryImage) {
                    $galleryPaths[] = $galleryImage->store('products/gallery', 'b2');
                }
            }

            $data = [
                'name'           => $this->name,
                'description'    => $this->description,
                'price'          => $this->price,
                'stock'          => $this->stock,
                'image_path'     => $imagePath,
                'brochure_path'  => $brochurePath,
                'gallery_files'  => $galleryPaths,
            ];

            if ($this->isEditing) {
                Product::find($this->product_id)->update($data);
                session()->flash('success', 'Produk berhasil diupdate!');
            } else {
                Product::create($data);
                session()->flash('success', 'Produk berhasil dibuat!');
            }

            $this->resetForm();
            $this->loadProducts();

        } catch (\Exception $e) {
            \Log::error('B2 CRUD Error: ' . $e->getMessage());
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    // --- EDIT ---

    public function edit($id)
    {
        $product = Product::findOrFail($id);

        $this->product_id       = $product->id;
        $this->name             = $product->name;
        $this->description      = $product->description;
        $this->price            = $product->price;
        $this->stock            = $product->stock;
        $this->existing_image   = $product->image_path;
        $this->existing_brochure= $product->brochure_path;
        $this->existing_gallery = $product->gallery_files ?? [];
        $this->isEditing        = true;
    }

    // --- DELETE ---

    public function delete($id)
    {
        $product = Product::findOrFail($id);

        // Hapus semua file terkait dari B2
        $product->deleteAllFiles();

        $product->delete();

        $this->loadProducts();
        session()->flash('success', 'Produk berhasil dihapus beserta file-file terkait.');
    }

    // --- REMOVE SINGLE GALLERY IMAGE (saat edit) ---

    public function removeGalleryImage($index)
    {
        $path = $this->existing_gallery[$index] ?? null;
        if ($path) {
            Storage::disk('b2')->delete($path);
            unset($this->existing_gallery[$index]);
            $this->existing_gallery = array_values($this->existing_gallery);
        }
    }

    // --- RESET ---

    public function resetForm()
    {
        $this->reset([
            'product_id', 'name', 'description', 'price', 'stock',
            'image', 'brochure', 'gallery',
            'existing_image', 'existing_brochure', 'existing_gallery',
            'isEditing',
        ]);
    }

    public function render()
    {
        return view('livewire.product-crud')
            ->layout('layouts.app');
    }
}
```

### 4.3 View CRUD

**`resources/views/livewire/product-crud.blade.php`**
```blade
<div x-data="{}" class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Product CRUD</h1>
    <p class="text-gray-500 text-sm mb-8">Storage: Backblaze B2</p>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        {{-- ==================== FORM ==================== --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-5">
                {{ $isEditing ? '✏️ Edit Produk' : '➕ Tambah Produk' }}
            </h2>

            <form wire:submit.prevent="save" class="space-y-5">

                {{-- Nama --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1.5">Nama Produk *</label>
                    <input type="text" wire:model="name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:outline-none"
                           placeholder="Nama produk" />
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Deskripsi --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1.5">Deskripsi</label>
                    <textarea wire:model="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:outline-none"
                              placeholder="Deskripsi produk..."></textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Harga & Stok --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1.5">Harga *</label>
                        <input type="number" wire:model="price" step="0.01" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:outline-none"
                               placeholder="0.00" />
                        @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1.5">Stok *</label>
                        <input type="number" wire:model="stock" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:outline-none"
                               placeholder="0" />
                        @error('stock') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Gambar Utama --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1.5">Gambar Utama</label>
                    @if($existing_image)
                        <div class="flex items-center gap-3 mb-2">
                            <img src="{{ Storage::disk('b2')->url($existing_image) }}"
                                 class="w-20 h-20 object-cover rounded-md border" />
                            <button type="button" wire:click="$set('existing_image', null)"
                                    class="text-red-600 hover:text-red-800 text-sm font-bold">✕ Hapus</button>
                        </div>
                    @endif
                    <input type="file" wire:model="image" accept="image/*" class="w-full text-sm" />
                    @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                    @if($image)
                        <img src="{{ $image->temporaryUrl() }}" class="mt-2 max-h-40 rounded-md border" />
                    @endif
                </div>

                {{-- Brosur / Dokumen --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1.5">Brosur (PDF/DOC)</label>
                    @if($existing_brochure)
                        <div class="mb-2">
                            <a href="{{ Storage::disk('b2')->url($existing_brochure) }}" target="_blank"
                               class="text-blue-600 hover:text-blue-800 underline text-sm font-bold">
                                📄 Lihat brosur saat ini
                            </a>
                            <button type="button" wire:click="$set('existing_brochure', null)"
                                    class="ml-3 text-red-600 hover:text-red-800 text-sm font-bold">✕ Hapus</button>
                        </div>
                    @endif
                    <input type="file" wire:model="brochure" accept=".pdf,.doc,.docx" class="w-full text-sm" />
                    @error('brochure') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Gallery (Multiple) --}}
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1.5">
                        Gallery
                        <span class="text-gray-400 font-normal">(multiple, max 5MB per gambar)</span>
                    </label>

                    {{-- Existing gallery --}}
                    @if(!empty($existing_gallery))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($existing_gallery as $index => $path)
                                <div class="relative">
                                    <img src="{{ Storage::disk('b2')->url($path) }}"
                                         class="w-16 h-16 object-cover rounded-md border" />
                                    <button type="button"
                                            wire:click="removeGalleryImage({{ $index }})"
                                            class="absolute -top-1.5 -right-1.5 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                                        ✕
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <input type="file" wire:model="gallery" accept="image/*" multiple class="w-full text-sm" />
                    @error('gallery.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                    @if(!empty($gallery))
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($gallery as $img)
                                <img src="{{ $img->temporaryUrl() }}" class="w-16 h-16 object-cover rounded-md border" />
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Tombol Submit --}}
                <div class="flex gap-2 pt-2">
                    <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-4 rounded-md transition disabled:opacity-50">
                        <span wire:loading.remove>{{ $isEditing ? '💾 Update Produk' : '💾 Simpan Produk' }}</span>
                        <span wire:loading>⏳ Menyimpan...</span>
                    </button>
                    @if($isEditing)
                        <button type="button" wire:click="resetForm"
                                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2.5 px-4 rounded-md transition">
                            Batal
                        </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- ==================== LIST ==================== --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Daftar Produk ({{ $products->count() }})</h2>

            <div class="space-y-4 max-h-[650px] overflow-y-auto pr-1">
                @forelse($products as $product)
                    <div class="border border-gray-200 rounded-lg p-4 relative group">
                        <div class="flex gap-4">
                            {{-- Gambar --}}
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}"
                                     class="w-24 h-24 object-cover rounded-md border shrink-0" />
                            @else
                                <div class="w-24 h-24 bg-gray-100 rounded-md border shrink-0 flex items-center justify-center">
                                    <span class="text-gray-400 text-xs">No Image</span>
                                </div>
                            @endif

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-gray-800 truncate">{{ $product->name }}</h3>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ Str::limit($product->description, 100) }}</p>

                                <div class="flex gap-3 mt-2 text-sm">
                                    <span class="text-green-700 font-bold">
                                        Rp {{ number_format($product->price, 0, ',', '.') }}
                                    </span>
                                    <span class="text-blue-600">Stok: {{ $product->stock }}</span>
                                </div>

                                {{-- Link brosur --}}
                                @if($product->brochure_url)
                                    <a href="{{ $product->brochure_url }}" target="_blank"
                                       class="inline-block mt-2 text-xs text-blue-600 hover:text-blue-800 underline">
                                        📄 Download Brosur
                                    </a>
                                @endif

                                {{-- Gallery thumbnails --}}
                                @if(!empty($product->gallery_urls))
                                    <div class="flex gap-1 mt-2">
                                        @foreach(array_slice($product->gallery_urls, 0, 4) as $gUrl)
                                            <img src="{{ $gUrl }}"
                                                 class="w-8 h-8 object-cover rounded border" />
                                        @endforeach
                                        @if(count($product->gallery_urls) > 4)
                                            <span class="text-xs text-gray-400 self-center">
                                                +{{ count($product->gallery_urls) - 4 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Tombol Aksi --}}
                            <div class="flex flex-col gap-1.5 shrink-0">
                                <button wire:click="edit({{ $product->id }})"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-bold py-1.5 px-3 rounded transition">
                                    ✏️ Edit
                                </button>
                                <button wire:click="delete({{ $product->id }})"
                                        class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-1.5 px-3 rounded transition">
                                    🗑 Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-4xl mb-2">📦</p>
                        <p>Belum ada produk</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
```

### 4.4 Route
```php
// routes/web.php
Route::get('/products', \App\Livewire\ProductCrud::class)->name('products');
```

---

## 5. Layout Blade (TailwindCSS)

**`resources/views/layouts/app.blade.php`**
```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel B2 Demo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-white shadow-md sticky top-0 z-10">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <span class="text-lg font-bold text-gray-800">☁️ B2 Storage Demo</span>
            <div class="flex gap-6 text-sm">
                <a href="{{ route('b2.upload') }}" class="text-gray-700 hover:text-red-600 transition font-medium">
                    Upload
                </a>
                <a href="{{ route('products') }}" class="text-gray-700 hover:text-red-600 transition font-medium">
                    Products
                </a>
            </div>
        </div>
    </nav>

    <main>{{ $slot }}</main>

    @livewireScripts
</body>
</html>
```

---

## 6. Menampilkan Asset dari Backblaze B2

### 6.1 Public Bucket — Direct URL
URL default B2 untuk file di bucket public:
```
https://f004.backblazeb2.com/file/BUCKET_NAME/path/to/file.jpg
```

```blade
{{-- Blade --}}
<img src="{{ Storage::disk('b2')->url('images/photo.jpg') }}" alt="Photo" />
<a href="{{ Storage::disk('b2')->url('documents/doc.pdf') }}" target="_blank">Download</a>
```

### 6.2 Private Bucket — Signed URL
```php
// Di controller / Livewire
$url = Storage::disk('b2')->temporaryUrl(
    'documents/report.pdf',
    now()->addHours(3)
);
```

```blade
<a href="{{ $url }}">Download (valid 3 jam)</a>
```

### 6.3 Via Model Accessor
```php
// Model
public function getImageUrlAttribute()
{
    return Storage::disk('b2')->url($this->image_path);
}
```

```blade
<img src="{{ $product->image_url }}" alt="{{ $product->name }}" />
```

### 6.4 Download Response
```php
// Controller
public function download($filename)
{
    return Storage::disk('b2')->download('documents/' . $filename);
}

// Route
Route::get('/download/{filename}', [DownloadController::class, 'download'])->name('download');
```

---

## 7. Setup CDN (Custom Domain)

Karena Backblaze B2 tidak menyediakan custom domain langsung, Anda perlu menambahkan CDN di depannya.

### 7.1 Opsi A: Cloudflare (Gratis)
1. Tambahkan domain Anda di Cloudflare
2. Buat Worker untuk proxy request ke B2:

```js
// Cloudflare Worker — proxy ke B2
export default {
    async fetch(request) {
        const url = new URL(request.url);
        const bucketUrl = 'https://f004.backblazeb2.com/file/myapp-assets';
        return fetch(bucketUrl + url.pathname);
    }
};
```

3. Setup route di Worker (`assets.example.com/*`)
4. Masukkan URL Worker ke `.env`:
```env
B2_URL=https://assets.example.com
```

### 7.2 Opsi B: BunnyCDN
1. Daftar [BunnyCDN](https://bunny.net)
2. Create Pull Zone → Origin URL: `https://f004.backblazeb2.com/file/myapp-assets`
3. Aktifkan custom domain di pull zone
4. Update `.env`:
```env
B2_URL=https://cdn-assets.example.com
```

---

## 8. Best Practices

### 8.1 Struktur Folder B2
```
myapp-assets/
├── products/
│   ├── images/
│   ├── brochures/
│   └── gallery/
├── articles/
│   ├── covers/
│   └── attachments/
├── assets/
│   ├── css/
│   └── js/
└── uploads/
    └── temp/
```

### 8.2 Validasi File Ketat
```php
$rules = [
    'image' => 'required|image|mimes:jpg,jpeg,png|dimensions:min_width=200,min_height=200|max:5120',
    'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:20480',
    'css_file' => 'required|file|mimes:css|max:1024',
];
```

### 8.3 Batch Delete
```php
// Hapus banyak file sekaligus
$paths = ['images/a.jpg', 'images/b.jpg', 'documents/c.pdf'];
Storage::disk('b2')->delete($paths);
```

### 8.4 Tracking Upload dengan Log
```php
Log::info('B2 Upload', [
    'path' => $path,
    'size' => $this->file->getSize(),
    'bucket' => config('filesystems.disks.b2.bucket'),
    'user' => auth()->id(),
]);
```

### 8.5 Background Processing
```bash
php artisan make:job UploadToB2
```

```php
class UploadToB2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(
        private string $path,
        private string $contents
    ) {}

    public function handle(): void
    {
        Storage::disk('b2')->put($this->path, $this->contents);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('B2 Job Failed: ' . $e->getMessage());
    }
}
```

### 8.6 Lifecycle Rules (di B2 Console)
- **Keep only last N versions**: Hemat biaya untuk file yang sering diupdate
- **Hide old versions setelah X days**: Otomatis bersihkan
- Setup di B2 Console → Bucket → Lifecycle Settings

---

## 9. Perbandingan B2 vs R2

| Fitur | Backblaze B2 | Cloudflare R2 |
|-------|-------------|---------------|
| **Harga storage** | $0.006/GB/bulan | $0.015/GB/bulan |
| **Egress gratis** | 3x storage (banded) | Gratis (no egress fee) |
| **Custom domain langsung** | ❌ (perlu CDN) | ✅ |
| **S3 compatible** | ✅ | ✅ |
| **Free tier** | 10 GB gratis | 10 GB gratis |
| **Upload speed** | Cepat | Cepat |
| **Cocok untuk** | Backup, arsip, high volume | Asset delivery, website traffic tinggi |

---

## 10. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| **403 Forbidden** | Pastikan bucket adalah **Public** atau gunakan signed URL |
| **Could not resolve host** | Cek `B2_ENDPOINT` di `.env` — harus format `https://s3.xx-xxx-xxx.backblazeb2.com` |
| **Invalid Access Key** | Pastikan `B2_ACCESS_KEY_ID` adalah `keyID` (bukan `applicationKey`) |
| **Upload timeout** | Tambah `'timeout' => 300` ke config disk `b2` |
| **File tidak muncul via URL** | Verifikasi URL: `https://{endpoint}/file/{bucket}/{path}` |
| **CORS error dari browser** | Tambah CORS rules di bucket B2 → CORS Rules |
| **Slow upload** | Gunakan endpoint region terdekat, atau aktifkan multipart upload |

### CORS Rules untuk B2
```json
[
    {
        "corsRuleName": "allowFromAny",
        "allowedOrigins": ["*"],
        "allowedHeaders": ["*"],
        "allowedOperations": ["b2_upload_file", "b2_download_file_by_name"],
        "exposeHeaders": ["x-bz-content-sha1"],
        "maxAgeSeconds": 3600
    }
]
```

---

## 11. Ceklis Final

- [ ] Akun Backblaze B2 aktif
- [ ] Bucket `myapp-assets` dibuat
- [ ] Application Key dibuat & `keyID` + `applicationKey` disimpan
- [ ] `league/flysystem-aws-s3-v3` terinstall
- [ ] `config/filesystems.php` dikonfigurasi untuk disk `b2`
- [ ] `.env` berisi `B2_ACCESS_KEY_ID`, `B2_SECRET_ACCESS_KEY`, `B2_BUCKET`, `B2_ENDPOINT`
- [ ] Bucket di-set **Public** (jika akses langsung diperlukan)
- [ ] CDN/Worker disetup untuk custom domain (jika diperlukan)
- [ ] Komponen Livewire upload/file berfungsi
- [ ] CRUD product berjalan dengan storage B2
- [ ] Gallery multiple upload berfungsi
- [ ] File handling lifecycle dikonfigurasi
- [ ] CORS rules ditambahkan (jika diakses dari browser)

---

## Referensi
- [Backblaze B2 Documentation](https://www.backblaze.com/docs/cloud-storage)
- [B2 S3 Compatible API](https://www.backblaze.com/docs/cloud-storage-s3-compatible-api)
- [Laravel File Storage (13.x)](https://laravel.com/docs/13.x/filesystem)
- [Livewire File Uploads](https://livewire.laravel.com/docs/uploads)
- [Flysystem AWS S3 v3](https://flysystem.thephpleague.com/docs/adapter/aws-s3-v3/)