# Panduan Fitur QR Code

## Ringkasan
Fitur QR Code pada aplikasi ini dipakai untuk:

- menerima input teks dari user
- generate QR code format `PNG` dan `JPG`
- menampilkan preview langsung di halaman
- menyediakan download file temporary
- membersihkan file temporary manual atau otomatis

Implementasi utama ada di:

- `app/Services/QrCodeTemporaryFileService.php`
- `resources/views/pages/qr-code/⚡generate.blade.php`
- `routes/web.php`
- `tests/Feature/QrCodeGeneratorTest.php`

---

## Alur Kerja

### 1. User buka halaman generate
Route Livewire untuk halaman ini ada di `routes/web.php:19`:

```php
Route::livewire('qr-code/generate', 'pages::qr-code.generate')->name('qr-code.generate');
```

Halaman hanya bisa diakses user yang lolos middleware:

- `auth`
- `verified`
- `login-otp`

### 2. User isi teks QR
Field input ada di `resources/views/pages/qr-code/⚡generate.blade.php:83-89`.

Validasi property Livewire ada di `resources/views/pages/qr-code/⚡generate.blade.php:11-12`:

```php
#[Validate(['required', 'string', 'max:5000'])]
public string $content = '';
```

Artinya:

- wajib diisi
- harus string
- maksimal `5000` karakter

### 3. Livewire panggil method `generate()`
Method utama ada di `resources/views/pages/qr-code/⚡generate.blade.php:29-48`.

Urutan proses:

1. validasi input
2. reset error lama
3. hapus file temporary lama milik state komponen
4. panggil service `QrCodeTemporaryFileService::generate()`
5. simpan nama file PNG, JPG, dan preview data URI ke state komponen
6. tampilkan toast sukses atau error

### 4. Service generate file QR
Logika backend ada di `app/Services/QrCodeTemporaryFileService.php:23-42`.

Proses dalam service:

1. jalankan cleanup file expired
2. buat token UUID
3. pastikan direktori storage tersedia
4. generate file `png` dan `jpg`
5. simpan ke disk `local`
6. kembalikan metadata hasil generate

Return value service berbentuk:

```php
[
    'png_filename' => 'uuid.png',
    'jpg_filename' => 'uuid.jpg',
    'preview_data_uri' => 'data:image/png;base64,...',
]
```

### 5. Preview dan download tampil
Jika `previewDataUri`, `pngFilename`, dan `jpgFilename` terisi, blok hasil tampil di `resources/views/pages/qr-code/⚡generate.blade.php:105-142`.

Fitur hasil:

- preview gambar QR
- tampil nama file PNG
- tampil nama file JPG
- tombol download PNG
- tombol download JPG

---

## Penjelasan Service `QrCodeTemporaryFileService`

File: `app/Services/QrCodeTemporaryFileService.php`

### Konstanta penting

```php
public const Disk = 'local';
public const Directory = 'qr-codes-tmp';
public const ExpiryHours = 24;
```

Makna:

- semua file QR disimpan di disk `local`
- folder penyimpanan: `qr-codes-tmp`
- file dianggap expired setelah `24` jam

### Method `generate(string $content): array`
Lokasi: `app/Services/QrCodeTemporaryFileService.php:23-42`

Tugas method:

- cleanup file lama
- generate nama file unik berbasis UUID
- render QR ke format PNG dan JPG
- simpan file ke storage
- buat preview base64 dari file PNG

### Method `delete(?string $filename): void`
Lokasi: `app/Services/QrCodeTemporaryFileService.php:44-55`

Tugas method:

- abaikan nama file kosong
- validasi nama file agar aman
- hapus file dari storage bila valid

### Method `deleteMany(array $filenames): void`
Lokasi: `app/Services/QrCodeTemporaryFileService.php:57-65`

Dipakai untuk hapus beberapa file sekaligus, misal PNG dan JPG lama dari state halaman.

### Method `cleanupExpiredFiles(): int`
Lokasi: `app/Services/QrCodeTemporaryFileService.php:67-90`

Tugas method:

- cek direktori storage
- hitung batas waktu expired
- iterasi semua file dalam direktori
- hapus file yang lebih tua dari batas waktu
- return jumlah file terhapus

### Method `path(string $filename): string`
Lokasi: `app/Services/QrCodeTemporaryFileService.php:107-114`

Method ini:

- validasi nama file
- gabungkan direktori dan nama file
- lempar `RuntimeException` bila nama file tidak valid

### Method `mimeType(string $filename): string`
Lokasi: `app/Services/QrCodeTemporaryFileService.php:116-119`

Dipakai route download untuk menentukan header respons:

- `.png` → `image/png`
- selain itu → `image/jpeg`

### Validasi keamanan nama file
Lokasi: `app/Services/QrCodeTemporaryFileService.php:121-124`

Regex dipakai:

```php
/\A[0-9a-f-]+\.(png|jpg)\z/i
```

Efek validasi:

- hanya file UUID-like diterima
- hanya ekstensi `png` atau `jpg`
- cegah path traversal seperti `../../file`

### Render QR image
Lokasi: `app/Services/QrCodeTemporaryFileService.php:126-132`

Service memakai:

- `BaconQrCode\Writer`
- `BaconQrCode\Renderer\GDLibRenderer`

Konfigurasi render saat ini:

- size: `800`
- margin: `4`
- format: `png` atau `jpg`
- kualitas PNG: `9`
- kualitas JPG: `90`

---

## Penjelasan Halaman Livewire `⚡generate.blade.php`

File ini adalah Livewire Volt single-file component.

### State komponen
Lokasi: `resources/views/pages/qr-code/⚡generate.blade.php:11-20`

Property utama:

- `$content` → input teks QR
- `$pngFilename` → nama file PNG hasil generate
- `$jpgFilename` → nama file JPG hasil generate
- `$previewDataUri` → preview QR berbasis base64
- `$generateError` → pesan error bila generate gagal

### Method `validationAttributes()`
Lokasi: `resources/views/pages/qr-code/⚡generate.blade.php:22-27`

Dipakai untuk mengganti label error validasi `content` menjadi `Teks QR Code`.

### Method `generate()`
Lokasi: `resources/views/pages/qr-code/⚡generate.blade.php:29-48`

Fungsi utama halaman:

- validasi input
- cleanup file temporary lama
- generate file baru via service
- set state hasil
- tampilkan toast sukses atau error

### Method `clearTemporaryFiles()`
Lokasi: `resources/views/pages/qr-code/⚡generate.blade.php:50-67`

Fungsi method:

- hapus file PNG/JPG lama via service
- reset state preview dan error
- tampilkan toast sukses bila file memang ada

### Struktur UI
Lokasi penting:

- heading halaman: `resources/views/pages/qr-code/⚡generate.blade.php:72-73`
- alert error: `resources/views/pages/qr-code/⚡generate.blade.php:76-81`
- form input: `resources/views/pages/qr-code/⚡generate.blade.php:83-103`
- panel hasil preview: `resources/views/pages/qr-code/⚡generate.blade.php:105-142`

Elemen penting UI:

- `flux:textarea` untuk input teks
- `flux:button` untuk generate
- tombol `Hapus Temporary`
- link download PNG/JPG
- preview image dari data URI

---

## Route Download File QR

Route download ada di `routes/web.php:21-32`:

```php
Route::get('qr-code/download/{filename}', function (string $filename, QrCodeTemporaryFileService $temporaryFileService) {
    $path = $temporaryFileService->path($filename);
    $storage = Storage::disk($temporaryFileService->disk());

    abort_unless($storage->exists($path), 404);

    return response()->streamDownload(function () use ($storage, $path): void {
        echo $storage->get($path);
    }, $filename, [
        'Content-Type' => $temporaryFileService->mimeType($filename),
    ]);
})->name('qr-code.download');
```

Catatan:

- route pakai validasi filename dari service
- file wajib ada, jika tidak akan `404`
- response dikirim sebagai download stream
- header MIME disesuaikan dengan ekstensi file

---

## Lokasi Penyimpanan File

Berdasarkan `app/Services/QrCodeTemporaryFileService.php:14-18`, file QR disimpan di:

- disk: `local`
- direktori: `qr-codes-tmp`

Secara praktis, file berada di area storage Laravel disk local.

Contoh path relatif storage:

```text
qr-codes-tmp/<uuid>.png
qr-codes-tmp/<uuid>.jpg
```

---

## Pembersihan File Temporary

### Pembersihan otomatis saat generate
Setiap kali `generate()` dipanggil, service menjalankan:

```php
$this->cleanupExpiredFiles();
```

Lokasi: `app/Services/QrCodeTemporaryFileService.php:25`

Efek:

- file QR lama dibersihkan otomatis saat ada generate baru

### Pembersihan manual dari halaman
Tombol `Hapus Temporary` ada di `resources/views/pages/qr-code/⚡generate.blade.php:92-95`.

Tombol ini memanggil method Livewire:

```blade
wire:click="clearTemporaryFiles"
```

### Pembersihan via command Artisan
Command terkait ada di `app/Console/Commands/CleanupLivewireTemporaryUploads.php:12-72`.

Command ini bukan hanya bersihkan temporary upload Livewire, tapi juga memanggil:

```php
$qrDeletedCount = $this->qrCodeTemporaryFileService->cleanupExpiredFiles();
```

Output command juga melaporkan jumlah file QR yang dihapus.

---

## Pengujian Fitur

Test utama ada di `tests/Feature/QrCodeGeneratorTest.php`.

Cakupan test saat ini:

1. halaman tampil untuk user terautentikasi — `tests/Feature/QrCodeGeneratorTest.php:17-27`
2. halaman blokir guest — `tests/Feature/QrCodeGeneratorTest.php:29-34`
3. validasi input wajib — `tests/Feature/QrCodeGeneratorTest.php:36-48`
4. generate simpan file PNG dan JPG — `tests/Feature/QrCodeGeneratorTest.php:50-74`
5. file hasil bisa diunduh — `tests/Feature/QrCodeGeneratorTest.php:76-94`
6. file temporary bisa dihapus manual — `tests/Feature/QrCodeGeneratorTest.php:96-118`
7. file expired dibersihkan service — `tests/Feature/QrCodeGeneratorTest.php:120-138`

Test command cleanup QR juga ada di:

- `tests/Feature/Console/CleanupLivewireTemporaryUploadsCommandTest.php`

---

## Ringkasan Teknis

### Komponen utama

- `app/Services/QrCodeTemporaryFileService.php` → logika generate, delete, cleanup, mime, validasi path
- `resources/views/pages/qr-code/⚡generate.blade.php` → UI + Livewire action
- `routes/web.php` → route halaman dan route download
- `tests/Feature/QrCodeGeneratorTest.php` → verifikasi fitur utama

### Kelebihan implementasi saat ini

- nama file unik dengan UUID
- dukung `PNG` dan `JPG`
- preview langsung tanpa file publik
- file temporary bisa dibersihkan
- validasi nama file bantu keamanan download
- ada test fitur inti

### Hal yang perlu diperhatikan

- penyimpanan masih di disk `local`
- preview memakai base64 PNG, jadi ukuran response Livewire ikut naik bila gambar besar
- file download bergantung pada file temporary masih ada dan belum expired

---

## Referensi Cepat

- Service generate QR: `app/Services/QrCodeTemporaryFileService.php:23`
- Cleanup expired QR: `app/Services/QrCodeTemporaryFileService.php:67`
- Validasi path filename: `app/Services/QrCodeTemporaryFileService.php:107`
- Halaman generate QR: `resources/views/pages/qr-code/⚡generate.blade.php:10`
- Action generate halaman: `resources/views/pages/qr-code/⚡generate.blade.php:29`
- Action hapus temporary: `resources/views/pages/qr-code/⚡generate.blade.php:50`
- Route halaman: `routes/web.php:19`
- Route download: `routes/web.php:21`
- Test fitur QR: `tests/Feature/QrCodeGeneratorTest.php:13`
