# Panduan Image Processing dengan Intervention Image v4
## Laravel 13 + Livewire + Backblaze B2

---

## 1. Arsitektur & Package

| Package | Versi | Fungsi |
|---------|-------|--------|
| `intervention/image` | ^4.2 | Core library image processing |
| `intervention/image-laravel` | ^4.1 | Laravel integration (facade, config, service provider) |

**Driver yang digunakan**: GD (bawaan PHP, tanpa install tambahan).

### 1.1 Instalasi

```bash
composer require intervention/image intervention/image-laravel

# Publish config
php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"
```

Hasil: `config/intervention-image.php`

### 1.2 Konfigurasi (`config/intervention-image.php`)

```php
use Intervention\Image\Drivers\Gd\Driver;

return [
    // Driver: Gd\Driver (default), Imagick\Driver, atau Vips\Driver
    'driver' => env('IMAGE_DRIVER', Driver::class),

    'options' => [
        'autoOrientation' => true,   // Auto-rotate berdasarkan EXIF
        'decodeAnimation' => true,   // Jaga frame animasi (GIF)
        'backgroundColor' => 'ffffff', // Warna background default
        'strip'           => false,  // Strip metadata saat encode
    ],
];
```

### 1.3 Cek PHP Extension

```bash
# Cek GD aktif
php -m | grep gd

# Detail fitur GD
php -r "print_r(gd_info());"
```

GD di project ini mendukung: JPEG, PNG, GIF, WebP, BMP, AVIF, FreeType.

---

## 2. Facade & API Dasar

### 2.1 Import Facade

```php
use Intervention\Image\Laravel\Facades\Image;
```

> **PENTING**: Gunakan facade dari `Intervention\Image\Laravel\Facades\Image`, bukan `Illuminate\Support\Facades\Image` (yang hanya tersedia di Laravel >= 13.20).

### 2.2 Membuat Image Baru (Blank Canvas)

```php
use Intervention\Image\Laravel\Facades\Image;

// Canvas 800x600 kosong
$image = Image::createImage(800, 600);

// Canvas dengan callback animasi (GIF)
$image = Image::createImage(200, 200, function ($animation) {
    // buat frame-frame animasi
});
```

### 2.3 Decode (Membaca Image)

`ImageManager` menyediakan beberapa method decode:

| Method | Input | Contoh |
|--------|-------|--------|
| `decode($source)` | Auto-detect (path, binary, base64, dll) | `Image::decode($anySource)` |
| `decodePath($path)` | File path string | `Image::decodePath('/path/to/image.jpg')` |
| `decodeBinary($binary)` | Raw binary data | `Image::decodeBinary($rawBytes)` |
| `decodeBase64($base64)` | Base64-encoded string | `Image::decodeBase64($b64String)` |
| `decodeDataUri($uri)` | Data URI | `Image::decodeDataUri('data:image/png;base64,...')` |
| `decodeStream($stream)` | PHP stream resource | `Image::decodeStream($resource)` |
| `decodeSplFileInfo($info)` | SplFileInfo object | `Image::decodeSplFileInfo($fileInfo)` |

**Contoh penggunaan umum:**

```php
// Dari file path
$image = Image::decode('/path/to/photo.jpg');

// Dari upload Livewire
$image = Image::decode($file->getRealPath());

// Dari binary (misal hasil download dari cloud)
$content = Storage::disk('b2')->get('gallery/original/photo.jpg');
$image = Image::decode($content);

// Dari base64
$image = Image::decodeBase64('iVBORw0KGgo...');
```

> **PERHATIAN**: Method `read()` tidak tersedia di facade `intervention/image-laravel`. Selalu gunakan `decode()` atau variant-nya.

---

## 3. Operasi Image

### 3.1 Informasi & Metadata

```php
$image = Image::decode($source);

$image->width();           // int: lebar (px)
$image->height();          // int: tinggi (px)
$image->size();            // SizeInterface: {width, height}
$image->colorspace();      // ColorspaceInterface
$image->resolution();      // ResolutionInterface (DPI)
$image->isAnimated();      // bool: apakah animated GIF
$image->count();           // int: jumlah frame
$image->exif();            // mixed: semua EXIF data
$image->exif('DateTimeOriginal'); // string: EXIF spesifik
$image->colorAt(100, 50);  // ColorInterface: warna pixel di koordinat
```

### 3.2 Resize

```php
use Intervention\Image\Fraction;

// Resize exact (bisa distorsi)
$image->resize(800, 600);

// Resize proporsional (aspect ratio terjaga)
$image->scale(width: 800);        // tinggi menyesuaikan
$image->scale(height: 600);       // lebar menyesuaikan
$image->scale(800, 600);          // fit dalam 800x600

// Resize hanya jika lebih besar
$image->scaleDown(1200, 900);

// Cover: crop & resize ke ukuran exact
$image->cover(400, 400);          // perfect square thumbnail

// Contain: fit di dalam ukuran, padding sisa dengan warna
$image->contain(800, 600, 'cccccc');  // fit + padding abu

// Menggunakan Fraction enum
$image->scale(Fraction::HALF);    // 50% dari ukuran asli
$image->scale(Fraction::QUARTER); // 25%
$image->scale(Fraction::DOUBLE);  // 200%
```

**Fraction enum values:**

| Enum | Multiplier |
|------|-----------|
| `Fraction::FULL` | 1.0x |
| `Fraction::HALF` | 0.5x |
| `Fraction::THIRD` | 0.333x |
| `Fraction::TWO_THIRDS` | 0.667x |
| `Fraction::QUARTER` | 0.25x |
| `Fraction::THREE_QUARTER` | 0.75x |
| `Fraction::ONE_AND_A_HALF` | 1.5x |
| `Fraction::DOUBLE` | 2.0x |
| `Fraction::TRIPLE` | 3.0x |

### 3.3 Crop

```php
// Crop 300x300 dari posisi (50, 50)
$image->crop(300, 300, 50, 50);

// Auto-trim border warna serupa (tolerance 0-100)
$image->trim(10);
```

### 3.4 Efek & Filter

```php
use Intervention\Image\Direction;

// Brightness: -100 (gelap) sampai +100 (terang)
$image->brightness(20);

// Contrast: -100 sampai +100
$image->contrast(15);

// Blur: 0 (tidak) sampai 100 (sangat blur)
$image->blur(5);

// Sharpen: level penajaman
$image->sharpen(15);

// Grayscale (hitam putih)
$image->grayscale();

// Invert warna
$image->invert();

// Pixelate
$image->pixelate(10);

// Gamma correction
$image->gamma(1.5);

// Colorize (RGB channel adjustment, masing-masing 0-100)
$image->colorize(red: 20, green: 0, blue: -10);

// Reduce color palette
$image->reduceColors(256);
```

> **PERHATIAN**: Method yang benar adalah `grayscale()` (American English), bukan `greyscale()`.

### 3.5 Rotasi & Flip

```php
use Intervention\Image\Direction;

// Rotate (clockwise, dalam derajat)
$image->rotate(90);
$image->rotate(180);
$image->rotate(270);
$image->rotate(45);    // sudut bebas

// Auto-orient berdasarkan EXIF
$image->orient();

// Flip horizontal (mirror)
$image->flip(Direction::HORIZONTAL);

// Flip vertikal
$image->flip(Direction::VERTICAL);

// Flip tanpa argumen = horizontal (default)
$image->flip();
```

> **PERHATIAN**: Method `flop()` tidak ada di Intervention Image v4. Gunakan `flip(Direction::VERTICAL)` untuk flip vertikal.

### 3.6 Drawing

```php
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\Geometry\Factories\LineFactory;
use Intervention\Image\Geometry\Factories\EllipseFactory;

// Pixel tunggal
$image->drawPixel(100, 50, 'ff0000');

// Rectangle
$image->drawRectangle(function (RectangleFactory $rect) {
    $rect->position(10, 10);
    $rect->size(200, 100);
    $rect->background('rgba(0, 0, 255, 0.5)');
    $rect->border('000000', 2);
});

// Circle
$image->drawCircle(function (CircleFactory $circle) {
    $circle->position(150, 150);
    $circle->radius(50);
    $circle->background('ff0000');
    $circle->border('000000', 1);
});

// Line
$image->drawLine(function (LineFactory $line) {
    $line->from(0, 0);
    $line->to(200, 200);
    $line->color('ff0000');
    $line->width(3);
});

// Ellipse
$image->drawEllipse(function (EllipseFactory $ellipse) {
    $ellipse->position(200, 200);
    $ellipse->size(300, 150);
    $ellipse->background('00ff00');
});
```

### 3.7 Typography (Text)

```php
$image->text('Hello World', 100, 200, function ($font) {
    $font->filename(resource_path('fonts/arial.ttf'));
    $font->size(36);
    $font->color('ffffff');
    $font->align('center');
    $font->valign('middle');
    $font->lineHeight(1.6);
    $font->angle(15);
    $font->wrap(400);    // word wrap width
});
```

### 3.8 Watermark / Insert Image

```php
use Intervention\Image\Alignment;

// Insert gambar lain sebagai watermark
$watermark = Image::decode(storage_path('watermark.png'));

$image->insert(
    $watermark,
    x: 10,
    y: 10,
    alignment: Alignment::BOTTOM_RIGHT,
    transparency: 50,  // opacity 0-100
);
```

**Alignment enum:**

| Value | Posisi |
|-------|--------|
| `Alignment::TOP_LEFT` | Kiri atas |
| `Alignment::TOP` | Tengah atas |
| `Alignment::TOP_RIGHT` | Kanan atas |
| `Alignment::LEFT` | Kiri tengah |
| `Alignment::CENTER` | Tengah |
| `Alignment::RIGHT` | Kanan tengah |
| `Alignment::BOTTOM_LEFT` | Kiri bawah |
| `Alignment::BOTTOM` | Tengah bawah |
| `Alignment::BOTTOM_RIGHT` | Kanan bawah |

### 3.9 Fill

```php
// Fill seluruh image dengan warna
$image->fill('ff5733');

// Flood-fill dari titik tertentu
$image->fill('00ff00', 100, 100);

// Ganti area transparan
$image->fillTransparentAreas('ffffff');
```

### 3.10 Canvas Resize

```php
// Resize canvas tanpa resample gambar (menambah ruang padding)
$image->resizeCanvas(1200, 800, 'ffffff', Alignment::CENTER);

// Resize canvas relatif (menambah/kurangi pixel dari masing-masing sisi)
$image->resizeCanvasRelative(50, 50, 'ffffff', Alignment::CENTER);
```

---

## 4. Encoding & Output

### 4.1 Encoder Classes

```php
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\BmpEncoder;
use Intervention\Image\Encoders\TiffEncoder;
```

| Encoder | Parameter | Default Quality |
|---------|-----------|----------------|
| `JpegEncoder` | `quality: 75`, `progressive: false`, `strip: null` | 75 |
| `PngEncoder` | `interlaced: false`, `indexed: false` | - |
| `WebpEncoder` | `quality: 75`, `strip: null` | 75 |
| `AvifEncoder` | `quality: 75`, `strip: null` | 75 |
| `GifEncoder` | `interlaced: false` | - |
| `BmpEncoder` | *(none)* | - |
| `TiffEncoder` | `quality: 75`, `strip: null` | 75 |

### 4.2 Encode ke Format Tertentu

```php
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\PngEncoder;

// Encode ke JPEG dengan kualitas 85
$encoded = $image->encode(new JpegEncoder(quality: 85));

// Progressive JPEG
$encoded = $image->encode(new JpegEncoder(quality: 90, progressive: true));

// Encode ke WebP
$encoded = $image->encode(new WebpEncoder(quality: 80));

// Encode ke PNG (lossless)
$encoded = $image->encode(new PngEncoder(interlaced: true));
```

### 4.3 Encode via Format/MediaType/Extension

```php
use Intervention\Image\Format;
use Intervention\Image\MediaType;
use Intervention\Image\FileExtension;

$encoded = $image->encodeUsingFormat(Format::WEBP, quality: 80);
$encoded = $image->encodeUsingMediaType('image/jpeg', quality: 85);
$encoded = $image->encodeUsingFileExtension('webp', quality: 80);
$encoded = $image->encodeUsingPath('output.jpg', quality: 90);  // infer dari extension
```

### 4.4 Output EncodedImage

```php
$encoded = $image->encode(new JpegEncoder(quality: 85));

// Simpan ke file lokal
$encoded->save(storage_path('app/output.jpg'));

// Ke string binary (untuk Storage::put)
$binary = (string) $encoded;

// Ke base64
$base64 = $encoded->toBase64();

// Ke data URI
$dataUri = $encoded->toDataUri();

// Ke stream resource
$stream = $encoded->toStream();

// Informasi
$encoded->mediaType();  // 'image/jpeg'
$encoded->size();       // bytes
```

### 4.5 Simpan Langsung ke File

```php
// Shortcut: encode + save dalam satu langkah
$image->save(storage_path('app/result.jpg'), quality: 85);

// Format ditentukan dari extension file path
$image->save('output.webp', quality: 80);
```

---

## 5. Integrasi dengan Laravel Storage & Backblaze B2

### 5.1 Upload ke B2

```php
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;

// Baca image
$image = Image::decode($file->getRealPath());

// Encode
$encoded = $image->encode(new JpegEncoder(quality: 85));

// Upload ke B2
Storage::disk('b2')->put('gallery/photo.jpg', (string) $encoded, 'public');
```

### 5.2 Download dari B2, Proses, Upload Kembali

```php
// Download binary dari B2
$content = Storage::disk('b2')->get('gallery/original/photo.jpg');

// Decode dari binary
$image = Image::decode($content);

// Proses
$image->scale(width: 800);
$image->brightness(10);
$image->contrast(5);

// Encode dan upload hasil edit
$encoded = $image->encode(new JpegEncoder(quality: 85));
Storage::disk('b2')->put('gallery/edited/photo_edited.jpg', (string) $encoded, 'public');

// Hapus file lama jika perlu
Storage::disk('b2')->delete('gallery/edited/old_photo_edited.jpg');
```

### 5.3 Generate Thumbnail saat Upload

```php
$image = Image::decode($file->getRealPath());

// Simpan original
Storage::disk('b2')->put(
    'gallery/original/'.$filename,
    file_get_contents($file->getRealPath()),
    'public'
);

// Buat thumbnail 300x300 (crop ke tengah)
$thumb = clone $image;
$thumb->cover(300, 300);
$encodedThumb = $thumb->encode(new JpegEncoder(quality: 75));
Storage::disk('b2')->put(
    'gallery/thumbnails/'.$filename,
    (string) $encodedThumb,
    'public'
);
```

### 5.4 Response Macro (Serve Image Langsung)

```php
use Illuminate\Support\Facades\Response;

// Di route atau controller
Route::get('image/{path}', function (string $path) {
    $content = Storage::disk('b2')->get($path);
    $image = Image::decode($content);
    $image->scale(width: 400);

    return Response::image($image, 'jpeg', quality: 80);
});
```

---

## 6. Implementasi di Project: Gallery Photo

### 6.1 Struktur File

```
app/Models/GalleryPhoto.php           # Eloquent model
database/migrations/..._create_gallery_photos_table.php
resources/views/pages/gallery/⚡index.blade.php  # Livewire SFC page
routes/web.php                        # Route::livewire('gallery', ...)
resources/views/layouts/app/sidebar.blade.php     # Menu sidebar
config/intervention-image.php         # Konfigurasi driver & opsi
```

### 6.2 Database Schema

```
gallery_photos
├── id (bigint PK)
├── user_id (FK → users, cascade delete)
├── title (string)
├── description (text, nullable)
├── original_path (string)            → gallery/original/{timestamp}_{uniqid}.{ext}
├── edited_path (string, nullable)    → gallery/edited/{timestamp}_{uniqid}_edited.jpg
├── disk (string, default 'b2')
├── original_width (unsigned int)
├── original_height (unsigned int)
├── file_size (unsigned bigint, bytes)
├── mime_type (string)
└── timestamps
```

### 6.3 Model: GalleryPhoto

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryPhoto extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'original_path', 'edited_path',
        'disk', 'original_width', 'original_height', 'file_size', 'mime_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // URL aktif (edited jika ada, otherwise original)
    public function getActiveUrlAttribute(): string
    {
        $path = $this->edited_path ?? $this->original_path;
        return Storage::disk($this->disk)->url($path);
    }

    // Human-readable file size
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
```

### 6.4 Livewire Page: Upload Flow

```php
use Intervention\Image\Laravel\Facades\Image;
use Livewire\WithFileUploads;

// Di dalam class component:
use WithFileUploads;

public $photo = null;  // Livewire file upload property

public function savePhoto(): void
{
    $this->validate();

    $file = $this->photo;

    // Baca metadata dari image
    $image  = Image::decode($file->getRealPath());
    $width  = $image->width();
    $height = $image->height();

    // Upload original ke B2
    $filename = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->guessExtension();
    $b2Path   = 'gallery/original/' . $filename;
    Storage::disk('b2')->put($b2Path, file_get_contents($file->getRealPath()), 'public');

    // Simpan record
    GalleryPhoto::create([
        'user_id'         => auth()->id(),
        'title'           => $this->title,
        'original_path'   => $b2Path,
        'disk'            => 'b2',
        'original_width'  => $width,
        'original_height' => $height,
        'file_size'       => $file->getSize(),
        'mime_type'       => $file->getMimeType(),
    ]);
}
```

### 6.5 Livewire Page: Edit/Processing Flow

```php
use Intervention\Image\Direction;
use Intervention\Image\Encoders\JpegEncoder;

public function applyEdit(): void
{
    $photo = GalleryPhoto::findOrFail($this->editingId);

    // Download original dari B2
    $content = Storage::disk('b2')->get($photo->original_path);
    $image = Image::decode($content);

    // Terapkan transformasi
    $image->scale(
        width: (int) $this->editSettings['width'],
        height: (int) $this->editSettings['height'],
    );

    if ($this->editSettings['brightness'] !== 0) {
        $image->brightness((int) $this->editSettings['brightness']);
    }

    if ($this->editSettings['contrast'] !== 0) {
        $image->contrast((int) $this->editSettings['contrast']);
    }

    if ((int) $this->editSettings['blur'] > 0) {
        $image->blur((int) $this->editSettings['blur']);
    }

    if ($this->editSettings['grayscale']) {
        $image->grayscale();
    }

    if ($this->editSettings['flip'] === 'h') {
        $image->flip(Direction::HORIZONTAL);
    } elseif ($this->editSettings['flip'] === 'v') {
        $image->flip(Direction::VERTICAL);
    }

    if ((int) $this->editSettings['rotate'] > 0) {
        $image->rotate((int) $this->editSettings['rotate']);
    }

    // Encode ke JPEG
    $encoded = $image->encode(new JpegEncoder(
        quality: (int) $this->editSettings['quality']
    ));

    // Upload hasil edit ke B2
    $editedPath = 'gallery/edited/' . now()->format('Ymd_His') . '_edited.jpg';
    Storage::disk('b2')->put($editedPath, (string) $encoded, 'public');

    // Hapus file edit lama dari B2
    if ($photo->edited_path) {
        Storage::disk('b2')->delete($photo->edited_path);
    }

    $photo->update(['edited_path' => $editedPath]);
}
```

---

## 7. Format yang Didukung

### 7.1 Format Enum

| Format | Encode | Decode | Extension |
|--------|--------|--------|-----------|
| JPEG | Ya | Ya | `.jpg`, `.jpeg`, `.pjpg`, `.pjpeg` |
| PNG | Ya | Ya | `.png` |
| GIF | Ya | Ya | `.gif` |
| WebP | Ya | Ya | `.webp` |
| AVIF | Ya | Ya | `.avif` |
| BMP | Ya | Ya | `.bmp` |
| TIFF | Ya | Ya | `.tif`, `.tiff` |
| HEIC | Ya* | Ya* | `.heic`, `.heif` |
| JPEG 2000 | Ya* | Ya* | `.jp2`, `.j2k` |
| JXL | Ya* | Ya* | `.jxl` |
| ICO | Ya | Ya | `.ico` |

*Tergantung driver dan extension PHP yang tersedia.

### 7.2 MediaType

Intervention Image mengenali 31 MIME types termasuk:
- `image/jpeg`, `image/pjpeg`
- `image/png`, `image/x-png`
- `image/gif`
- `image/webp`, `image/x-webp`
- `image/avif`, `image/x-avif`
- `image/bmp` (+ 8 varian)
- `image/tiff`
- `image/heic`, `image/heif`, `image/x-heic`
- `image/jp2` (+ 3 varian)
- `image/jxl`, `image/x-jxl`
- `image/x-icon`, `image/vnd.microsoft.icon`

---

## 8. Gotchas & Best Practices

### 8.1 Nama Method yang Sering Salah

| Salah | Benar | Catatan |
|-------|-------|---------|
| `Image::read(...)` | `Image::decode(...)` | `read()` tidak ada di facade v4 |
| `Image::make(...)` | `Image::decode(...)` | `make()` adalah API v2/v3 |
| `$image->greyscale()` | `$image->grayscale()` | American English spelling |
| `$image->flop()` | `$image->flip(Direction::VERTICAL)` | `flop()` tidak ada di v4 |
| `$image->fit(200, 200)` | `$image->cover(200, 200)` | `fit()` adalah API v2/v3 |

### 8.2 Livewire Naming Conflict

Jangan gunakan nama method berikut di komponen Livewire anonymous (SFC):

| Nama Method | Alasan |
|-------------|--------|
| `view()` | Bentrok dengan `Component::view()` bawaan Livewire |
| `render()` | Bentrok dengan lifecycle Livewire |
| `upload()` | Bentrok dengan internal `UploadManager` Livewire |
| `mount()` | Hanya boleh didefinisikan sekali |

**Solusi**: Gunakan nama deskriptif seperti `savePhoto()`, `showPhoto()`, `processImage()`.

### 8.3 Memory Management

```php
// Untuk image besar, perhatikan memory limit
ini_set('memory_limit', '256M');

// Clone image sebelum multiple transformasi
$thumb = clone $image;
$thumb->cover(300, 300);

// Original tetap utuh
$image->scale(width: 1200);
```

### 8.4 Validasi Upload di Livewire

```php
#[Validate(['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'])]
public $photo = null;

// max:10240 = 10 MB (dalam kilobytes)
```

### 8.5 Path Convention di B2

```
gallery/
├── original/    → File asli yang diunggah
│   └── 20260724_120530_669abc123def.jpg
├── edited/      → Hasil edit/proses
│   └── 20260724_121045_669abc456ghi_edited.jpg
└── thumbnails/  → Thumbnail (opsional)
    └── 20260724_120530_669abc123def.jpg
```

---

## 9. Driver Comparison

| Fitur | GD | Imagick | Vips |
|-------|:--:|:-------:|:----:|
| Format dasar (JPEG/PNG/GIF/WebP) | Ya | Ya | Ya |
| AVIF | Ya* | Ya | Ya |
| HEIC/HEIF | - | Ya | Ya |
| TIFF | - | Ya | Ya |
| Kualitas resize | Baik | Sangat baik | Sangat baik |
| Kecepatan | Cepat | Sedang | Sangat cepat |
| Memory usage | Rendah | Tinggi | Rendah |
| Install effort | Bawaan PHP | Perlu ext | Perlu ext |

*GD mendukung AVIF mulai PHP 8.1 dengan `--enable-gd --with-avif`.

**Ganti driver** (di `.env`):

```env
# Pilih salah satu (pastikan extension terinstall)
IMAGE_DRIVER=Intervention\Image\Drivers\Gd\Driver
IMAGE_DRIVER=Intervention\Image\Drivers\Imagick\Driver
IMAGE_DRIVER=Intervention\Image\Drivers\Vips\Driver
```

---

## 10. Quick Reference

```php
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Direction;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\PngEncoder;

// Decode
$image = Image::decode($source);

// Info
$image->width(); $image->height();

// Resize
$image->scale(800, 600);
$image->cover(400, 400);
$image->contain(800, 600, 'ffffff');

// Crop
$image->crop(300, 300, 50, 50);

// Effects
$image->brightness(20);
$image->contrast(15);
$image->blur(5);
$image->sharpen(10);
$image->grayscale();
$image->pixelate(8);

// Transform
$image->rotate(90);
$image->flip(Direction::HORIZONTAL);
$image->flip(Direction::VERTICAL);

// Encode
$encoded = $image->encode(new JpegEncoder(quality: 85));
$encoded = $image->encode(new WebpEncoder(quality: 80));

// Output
(string) $encoded;         // binary
$encoded->toBase64();      // base64
$encoded->save('out.jpg'); // save to file
Storage::disk('b2')->put('path.jpg', (string) $encoded, 'public');
```
