# Panduan: Fitur Cetak Faktur (Invoice) PDF

Panduan pembuatan fitur cetak faktur dalam format PDF yang diekspor dari form isian: **nama**, **dynamic items** (deskripsi + quantity + harga satuan → auto-sum total), **terbilang**, **memo/catatan**. Plus: **logo opsional** (pojok kiri atas), **opsi ukuran kertas** (A4, 1/2 A4, 1/3 A4 landscape), dan **riwayat faktur tersimpan di tabel database + file PDF di Backblaze B2**.

**Dynamic items** adalah baris item yang bisa ditambah/dihapus secara dinamis (Alpine.js). Setiap item: deskripsi, quantity, harga satuan → subtotal otomatis. Total seluruh item dihitung otomatis menjadi **nominal** faktur. Items disimpan sebagai JSON di kolom `items`.

Stack: Laravel 13 + Livewire 4 + Flux UI + Tailwind 4 + DOMPDF + Backblaze B2 (S3-compatible). Mengikuti pola halaman `qr-code/generate` yang sudah ada (Livewire page class inline di Blade, route `Route::livewire`). Untuk storage B2 mengikuti `panduan-backblaze-b2-laravel-livewire.md`.

---

## 1. Dependensi

```bash
composer require barryvdh/laravel-dompdf --no-interaction
```

Paket `league/flysystem-aws-s3-v3` untuk B2 sudah ada di `composer.json` repo ini. Konfigurasi disk `b2` di `config/filesystems.php` dan env `B2_*` di `.env` sesuai `panduan-backblaze-b2-laravel-livewire.md` bagian 2.

---

## 2. Konvensi Struktur

| Hal | Path / Lokasi |
|---|---|
| Route | `routes/web.php` |
| View + Livewire class (inline) | `resources/views/pages/faktur/⚡generate.blade.php` |
| Service (logic PDF + B2) | `app/Services/FakturPdfService.php` |
| Blade template PDF | `resources/views/pdf/faktur.blade.php` |
| Model + migration (riwayat) | `app/Models/Faktur.php` + `database/migrations/xxxx_create_fakturs_table.php` |
| Factory + seeder | `database/factories/FakturFactory.php`, `database/seeders/FakturSeeder.php` |

Prefix `⚡` pada nama file view agar Livewire auto-discovery mengenali sebagai page class inline (sama seperti `⚡generate.blade.php` di `qr-code`).

---

## 3. Model & Migration (Riwayat Faktur)

```bash
php artisan make:model Faktur -mfsc --no-interaction
```

`database/migrations/xxxx_create_fakturs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fakturs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nomor_faktur')->unique();
            $table->string('nama');
            $table->decimal('nominal', 14, 2);
            $table->json('items')->nullable();                    // JSON: [{description, qty, price, subtotal}]
            $table->string('terbilang');
            $table->text('memo')->nullable();
            $table->string('paper_size')->default('a4');      // a4 | half_a4 | third_a4
            $table->string('logo_path')->nullable();          // path logo di B2 (opsional)
            $table->string('pdf_path');                      // path PDF di B2
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fakturs');
    }
};
```

`app/Models/Faktur.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Faktur extends Model
{
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

    /**
     * URL download PDF (signed, private bucket) atau direct (public bucket).
     */
    public function getPdfUrlAttribute(): string
    {
        return Storage::disk('b2')->temporaryUrl($this->pdf_path, now()->addHours(3));
    }

    /**
     * URL logo bila ada.
     */
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

    /**
     * Hapus semua file terkait di B2.
     */
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
```

`database/factories/FakturFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Faktur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faktur>
 */
class FakturFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nomor_faktur' => 'INV-'.$this->faker->unique()->numerify('######'),
            'nama' => $this->faker->name(),
            'nominal' => $this->faker->numberBetween(50000, 5000000),
            'items' => [
                ['description' => 'Jasa konsultasi', 'qty' => 1, 'price' => 150000, 'subtotal' => 150000],
                ['description' => 'Biaya administrasi', 'qty' => 1, 'price' => 50000, 'subtotal' => 50000],
            ],
            'terbilang' => 'Seratus ribu rupiah',
            'memo' => $this->faker->optional()->sentence(),
            'paper_size' => $this->faker->randomElement(['a4', 'half_a4', 'third_a4']),
            'logo_path' => null,
            'pdf_path' => 'faktur/dummy.pdf',
        ];
    }
}
```

Jalankan migrasi:

```bash
php artisan migrate --no-interaction
```

---

## 4. Paper Size & Logo Config

DOMPDF mengatur ukuran via `setPaper()`. Tambahkan mapping di service. Nilai ukuran:

| Kode | Label | DOMPDF paper | Orientasi |
|---|---|---|---|
| `a4` | A4 | `a4` (210 × 297 mm) | portrait |
| `half_a4` | 1/2 A4 | `a4` dipotong vertikal → pakai custom [148, 210] mm | portrait |
| `third_a4` | 1/3 A4 | custom landscape [99, 210] mm | landscape |

> 1/2 A4 = A5 ≈ 148×210 mm. 1/3 A4 landscape ≈ 99×210 mm (tinggi 1/3 A4 = 99mm, lebar penuh A4 = 210mm).

---

## 5. Service: `FakturPdfService`

`app/Services/FakturPdfService.php`:

```php
<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FakturPdfService
{
    public const DISK = 'b2';

    public const DIR_PDF = 'faktur/documents';

    public const DIR_LOGO = 'faktur/logos';

    /**
     * Mapping paper_size -> [paper, orientation, [w, h] custom mm].
     *
     * @return array{0: string, 1: string, 2: ?array{0: float, 1: float}}
     */
    public function paperConfig(string $size): array
    {
        return match ($size) {
            'half_a4' => [[0 => 148.0, 1 => 210.0], 'portrait', null],
            'third_a4' => [[0 => 210.0, 1 => 99.0], 'landscape', null],
            default => ['a4', 'portrait', null], // a4
        };
    }

    /**
     * Upload logo (opsional) ke B2. Return path atau null.
     */
    public function storeLogo(?UploadedFile $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $filename = time().'_logo_'.Str::slug(pathinfo($logo->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$logo->getClientOriginalExtension();

        return $logo->storeAs(self::DIR_LOGO, $filename, self::DISK);
    }

    /**
     * Format nominal ke rupiah, mis. 150000 -> "Rp 150.000".
     */
    public function formatRupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * Generate PDF faktur, simpan ke B2, kembalikan path + data URI preview.
     *
     * @param  array{name: string, nominal: float, items: array, terbilang: string, memo: ?string, paper_size: string, logo_path: ?string, nomor_faktur: string}  $data
     * @return array{pdf_path: string, preview: string}
     */
    public function generate(array $data): array
    {
        [$paper, $orientation] = $this->paperConfig($data['paper_size']);

        /** @var PDF $pdf */
        $pdf = app('pdf')->loadView('pdf.faktur', [
            'nama' => $data['name'],
            'nominal' => $this->formatRupiah($data['nominal']),
            'items' => $data['items'],
            'terbilang' => $data['terbilang'],
            'memo' => $data['memo'] ?? null,
            'logoUrl' => $data['logo_path'] ? Storage::disk(self::DISK)->url($data['logo_path']) : null,
            'tanggal' => now()->translatedFormat('d F Y'),
            'nomorFaktur' => $data['nomor_faktur'],
        ]);

        $pdf->setPaper($paper, $orientation);

        $filename = $data['nomor_faktur'].'_'.Str::slug($data['name']).'.pdf';
        $path = self::DIR_PDF.'/'.$filename;

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk(self::DISK);
        $storage->put($path, $pdf->output());

        return [
            'pdf_path' => $path,
            'preview' => 'data:application/pdf;base64,'.base64_encode($pdf->output()),
        ];
    }
}
```

> Logo di-embed ke PDF via URL B2. Pastikan bucket B2 **public** agar DOMPDF bisa fetch logo, ATAU pre-encode logo jadi data URI base64 di service lalu passing `$logoDataUri` ke blade (lebih andal untuk private bucket). Contoh encode di service:
>
> ```php
> 'logoDataUri' => $data['logo_path']
>     ? 'data:'.Storage::disk(self::DISK)->mimeType($data['logo_path']).';base64,'.base64_encode(Storage::disk(self::DISK)->get($data['logo_path']))
>     : null,
> ```
> Lalu di blade PDF pakai `<img src="{{ $logoDataUri }}">` sebagai ganti `$logoUrl`. **Direkomendasikan** pakai data URI agar DOMPDF tidak butuh akses jaringan ke B2 saat render.

---

## 6. Blade Template PDF (dengan Logo + Ukuran Adaptif)

`resources/views/pdf/faktur.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur {{ $nomorFaktur }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { margin: 30px; color: #1f2937; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 12px; }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .logo { height: 60px; width: auto; max-width: 180px; }
        .title { font-size: 22px; font-weight: bold; }
        .nomor { font-size: 12px; color: #6b7280; }
        .row { margin-top: 18px; }
        .label { font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .value { font-size: 15px; font-weight: 600; }
        .nominal-big { font-size: 24px; font-weight: bold; margin-top: 4px; }
        .terbilang { font-style: italic; color: #4b5563; margin-top: 4px; }
        .memo { margin-top: 20px; padding: 10px 14px; background: #f9fafb; border-left: 4px solid #111827; white-space: pre-wrap; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; }
        .signature { text-align: center; }
        /* Tabel item */
        .item-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .item-table th { background: #f3f4f6; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; }
        .item-table td { padding: 7px 10px; font-size: 13px; border-bottom: 1px solid #e5e7eb; }
        .item-table .right { text-align: right; }
        .item-table .center { text-align: center; }
        .total-row td { font-weight: bold; font-size: 15px; border-top: 2px solid #111827; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if (! empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="Logo" class="logo" />
            @elseif (! empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="Logo" class="logo" />
            @endif
            <div>
                <div class="title">FAKTUR</div>
                <div class="nomor">{{ $nomorFaktur }}</div>
            </div>
        </div>
        <div style="text-align: right;">
            <div class="value">{{ $tanggal }}</div>
        </div>
    </div>

    <div class="row">
        <div class="label">Kepada Yth.</div>
        <div class="value">{{ $nama }}</div>
    </div>

    @if (! empty($items))
        <table class="item-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Deskripsi</th>
                    <th class="center">Qty</th>
                    <th class="right">Harga Satuan</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $no++ }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td class="center">{{ (int) $item['qty'] }}</td>
                        <td class="right">{{ number_format((float) $item['price'], 0, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $item['subtotal'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="right">TOTAL</td>
                    <td class="right">{{ $nominal }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="row">
        <div class="label">Jumlah Tagihan</div>
        <div class="nominal-big">{{ $nominal }}</div>
        <div class="terbilang">Terbilang: {{ $terbilang }}</div>
    </div>

    @if (! empty($memo))
        <div class="memo">{{ $memo }}</div>
    @endif

    <div class="footer">
        <div>Pembayaran dapat ditransfer ke rekening yang tertera pada catatan.</div>
        <div class="signature">
            Hormat kami,<br><br><br>
            ____________________
        </div>
    </div>
</body>
</html>
```

Catatan template:
- Hindari Tailwind di PDF — dompdf tak paham utility modern, pakai inline `<style>`.
- Font `DejaVu Sans` sudah bundle dompdf.
- Logo di pojok kiri atas via `.header-left` flex. Tinggi `60px`, lebar auto. Hilangkan blok `<img>` bila tanpa logo.
- Untuk 1/3 A4 landscape, margin `30px` cukup; layout flex header tetap rapi.

---

## 7. Route

`routes/web.php` — tambah di dalam group `['auth', 'verified', 'login-otp']`:

```php
Route::livewire('faktur/generate', 'pages::faktur.generate')->name('faktur.generate');
```

Download PDF via signed URL langsung dari accessor model `Faktur::pdf_url` (tidak butuh route terpisah), atau bila ingin endpoint route-based:

```php
Route::get('faktur/download/{faktur}', function (\App\Models\Faktur $faktur) {
    abort_unless($faktur->user_id === auth()->id(), 403);

    return Storage::disk('b2')->download($faktur->pdf_path, basename($faktur->pdf_path));
})->name('faktur.download');
```

---

## 8. Livewire Page (inline di Blade)

`resources/views/pages/faktur/⚡generate.blade.php`:

```blade
<?php

use App\Models\Faktur;
use App\Services\FakturPdfService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Cetak Faktur')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    #[Validate(['required', 'string', 'max:255'])]
    public string $nama = '';

    /** @var array<int, array{description: string, qty: int, price: float, subtotal: float}> */
    public array $items = [];

    #[Validate(['required', 'string', 'max:500'])]
    public string $terbilang = '';

    #[Validate(['nullable', 'string', 'max:2000'])]
    public ?string $memo = null;

    #[Validate(['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,svg,webp'])]
    public $logo = null;

    #[Validate(['required', 'string', 'in:a4,half_a4,third_a4'])]
    public string $paperSize = 'a4';

    public ?string $previewDataUri = null;

    public ?string $generateError = null;

    public function validationAttributes(): array
    {
        return [
            'nama' => 'Nama',
            'items' => 'Item',
            'terbilang' => 'Terbilang',
            'memo' => 'Catatan',
            'logo' => 'Logo',
            'paperSize' => 'Ukuran Kertas',
        ];
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item wajib diisi.',
            'items.min' => 'Minimal satu item wajib diisi.',
            'items.*.description.required' => 'Deskripsi item wajib diisi.',
            'items.*.qty.required' => 'Qty item wajib diisi.',
            'items.*.price.required' => 'Harga item wajib diisi.',
        ];
    }

    public function mount(): void
    {
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'qty' => 1, 'price' => 0, 'subtotal' => 0];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Dipanggil oleh Alpine.js setiap kali qty/price berubah.
     */
    public function updateItem(int $index, string $field, mixed $value): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index][$field] = $field === 'description' ? (string) $value : (float) $value;

        $qty = (int) ($this->items[$index]['qty'] ?? 0);
        $price = (float) ($this->items[$index]['price'] ?? 0);
        $this->items[$index]['subtotal'] = $qty * $price;
    }

    /**
     * Hitung total seluruh item. Dipanggil di generate().
     */
    public function getTotalProperty(): float
    {
        return array_sum(array_column($this->items, 'subtotal'));
    }

    public function generate(FakturPdfService $service): void
    {
        $this->validate();
        $this->generateError = null;

        $total = $this->total;

        if ($total <= 0) {
            $this->generateError = 'Total nominal harus lebih dari 0.';
            Flux::toast(variant: 'error', text: $this->generateError);

            return;
        }

        try {
            $nomorFaktur = 'INV-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(4));
            $logoPath = $service->storeLogo($this->logo);

            $result = $service->generate([
                'name' => $this->nama,
                'nominal' => $total,
                'items' => array_map(fn (array $item) => [
                    'description' => $item['description'],
                    'qty' => (int) $item['qty'],
                    'price' => (float) $item['price'],
                    'subtotal' => (float) $item['subtotal'],
                ], $this->items),
                'terbilang' => $this->terbilang,
                'memo' => $this->memo,
                'paper_size' => $this->paperSize,
                'logo_path' => $logoPath,
                'nomor_faktur' => $nomorFaktur,
            ]);

            Faktur::create([
                'user_id' => auth()->id(),
                'nomor_faktur' => $nomorFaktur,
                'nama' => $this->nama,
                'nominal' => $total,
                'items' => $this->items,
                'terbilang' => $this->terbilang,
                'memo' => $this->memo,
                'paper_size' => $this->paperSize,
                'logo_path' => $logoPath,
                'pdf_path' => $result['pdf_path'],
            ]);

            $this->previewDataUri = $result['preview'];

            $this->reset(['nama', 'items', 'terbilang', 'memo', 'logo', 'paperSize']);
            $this->paperSize = 'a4';
            $this->addItem();

            Flux::toast(variant: 'success', text: 'Faktur PDF dibuat & disimpan ke B2.');
        } catch (\Throwable $e) {
            $this->generateError = $e->getMessage();
            Flux::toast(variant: 'error', text: 'Gagal membuat faktur. '.$this->generateError);
        }
    }

    public function delete(FakturPdfService $service, Faktur $faktur): void
    {
        abort_unless($faktur->user_id === auth()->id(), 403);

        $faktur->deleteAllFiles();
        $faktur->delete();

        Flux::toast(variant: 'success', text: 'Faktur & file B2 dihapus.');
    }

    public function getFaktursProperty()
    {
        return Faktur::where('user_id', auth()->id())
            ->latest()
            ->limit(50)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Cetak Faktur') }}</flux:heading>
        <flux:subheading>{{ __('Isi data, pilih ukuran kertas, lalu ekspor faktur sebagai PDF (tersimpan di B2).') }}</flux:subheading>
    </div>

    @if ($generateError)
        <div class="max-w-2xl rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            <div class="font-medium">{{ __('Gagal membuat faktur') }}</div>
            <div class="mt-1 break-words">{{ $generateError }}</div>
        </div>
    @endif

    <form wire:submit="generate" class="max-w-2xl space-y-5">
        <flux:field>
            <flux:label>{{ __('Nama') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
            <flux:input wire:model="nama" placeholder="Nama penerima / pelanggan" data-test="input-nama" />
            <flux:error name="nama" />
        </flux:field>

        {{-- ===== Dynamic Items ===== --}}
        <fieldset class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
            x-data="{
                items: @entangle('items'),
                updateItem(index, field, value) {
                    $wire.updateItem(index, field, value);
                }
            }"
        >
            <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Item Tagihan') }} <flux:badge size="sm" color="red">Wajib</flux:badge>
            </legend>

            <flux:error name="items" />
            <flux:error name="items.*.description" />
            <flux:error name="items.*.qty" />
            <flux:error name="items.*.price" />

            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 w-[5%]">#</th>
                            <th class="px-3 py-2">Deskripsi</th>
                            <th class="px-3 py-2 w-[10%] text-center">Qty</th>
                            <th class="px-3 py-2 w-[22%] text-right">Harga Satuan</th>
                            <th class="px-3 py-2 w-[22%] text-right">Subtotal</th>
                            <th class="px-3 py-2 w-[5%]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="px-3 py-2 text-center text-zinc-400" x-text="index + 1"></td>
                                <td class="px-3 py-2">
                                    <flux:input
                                        type="text"
                                        x-model="item.description"
                                        placeholder="Nama item / jasa"
                                        x-on:input="updateItem(index, 'description', $el.value)"
                                        size="sm"
                                        data-test="input-item-desc"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <flux:input
                                        type="number"
                                        min="1"
                                        step="1"
                                        x-model="item.qty"
                                        x-on:input="updateItem(index, 'qty', $el.value)"
                                        size="sm"
                                        class="text-center"
                                        data-test="input-item-qty"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <flux:input
                                        type="number"
                                        min="0"
                                        step="1"
                                        x-model="item.price"
                                        x-on:input="updateItem(index, 'price', $el.value)"
                                        size="sm"
                                        class="text-right"
                                        data-test="input-item-price"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right font-mono" x-text="'Rp ' + Number(item.subtotal || 0).toLocaleString('id-ID')"></td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button"
                                        x-show="items.length > 1"
                                        x-on:click="$wire.removeItem(index)"
                                        class="text-red-500 hover:text-red-700 text-lg leading-none"
                                        data-test="btn-remove-item"
                                    >&times;</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <button type="button" wire:click="addItem"
                class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400"
                data-test="btn-add-item"
            >
                + {{ __('Tambah Item') }}
            </button>

            <div class="text-right text-lg font-bold">
                {{ __('Total:') }}
                <span class="font-mono" x-text="'Rp ' + (items || []).reduce((s, i) => s + Number(i.subtotal || 0), 0).toLocaleString('id-ID')"></span>
            </div>
        </fieldset>

        <flux:field>
            <flux:label>{{ __('Terbilang') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
            <flux:input wire:model="terbilang" placeholder="Seratus lima puluh ribu rupiah" data-test="input-terbilang" />
            <flux:error name="terbilang" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Catatan / Memo') }}</flux:label>
            <flux:textarea wire:model="memo" rows="4" placeholder="Catatan tambahan, no. rekening, jatuh tempo..." data-test="input-memo" />
            <flux:error name="memo" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Logo (opsional)') }}</flux:label>
            <flux:input type="file" wire:model="logo" accept="image/*" data-test="input-logo" />
            <flux:description>{{ __('Muncul di pojok kiri atas faktur. JPG/PNG/SVG/WebP, maks 2MB.') }}</flux:description>
            <flux:error name="logo" />
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="mt-2 h-16 w-auto rounded border" />
            @endif
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Ukuran Kertas') }}</flux:label>
            <flux:select wire:model="paperSize" data-test="select-paper-size">
                <option value="a4">A4 (210 × 297 mm, portrait)</option>
                <option value="half_a4">1/2 A4 (148 × 210 mm, portrait)</option>
                <option value="third_a4">1/3 A4 landscape (210 × 99 mm)</option>
            </flux:select>
            <flux:description>{{ __('Ukuran kertas hasil PDF.') }}</flux:description>
            <flux:error name="paperSize" />
        </flux:field>

        <div class="flex justify-end border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="generate" data-test="btn-generate-faktur">
                <span wire:loading.remove wire:target="generate">{{ __('Cetak Faktur') }}</span>
                <span wire:loading wire:target="generate">{{ __('Memproses...') }}</span>
            </flux:button>
        </div>
    </form>

    @if ($previewDataUri)
        <div class="max-w-4xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
            <flux:heading size="lg">{{ __('Preview Faktur Terakhir') }}</flux:heading>
            <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <object data="{{ $previewDataUri }}#toolbar=0" type="application/pdf" class="h-[600px] w-full" data-test="faktur-preview"></object>
            </div>
        </div>
    @endif

    {{-- ===== Tabel Riwayat Faktur ===== --}}
    <div class="max-w-5xl">
        <flux:heading size="lg">{{ __('Riwayat Faktur') }}</flux:heading>
        <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Semua faktur tersimpan di Backblaze B2. Klik nomor untuk download.') }}
        </flux:text>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                        <th class="px-4 py-3">Kertas</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->fakturs as $faktur)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                            <td class="px-4 py-3 font-mono">
                                <a href="{{ $faktur->pdf_url }}" target="_blank" class="text-blue-600 hover:underline" data-test="link-download-faktur">
                                    {{ $faktur->nomor_faktur }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $faktur->nama }}</td>
                            <td class="px-4 py-3 text-right">{{ $service ??= app(\App\Services\FakturPdfService::class)->formatRupiah((float) $faktur->nominal) }}</td>
                            <td class="px-4 py-3 uppercase">{{ $faktur->paper_size }}</td>
                            <td class="px-4 py-3">{{ $faktur->created_at->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <flux:button icon="arrow-down-tray" size="xs" variant="ghost" :href="$faktur->pdf_url" target="_blank" />
                                <flux:button icon="trash" size="xs" variant="ghost" wire:click="delete({{ $faktur->id }})" wire:confirm="Hapus faktur ini beserta file B2?" data-test="btn-delete-faktur" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-400">Belum ada faktur.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
```

Catatan page:
- `WithFileUploads` untuk upload logo.
- Dynamic items via `public array $items` + Alpine.js `x-data` dengan `@entangle('items')`. Setiap input qty/price mentrigger `$wire.updateItem()` yang hitung ulang subtotal.
- `getTotalProperty()` — computed property via `$this->total` — menjumlahkan seluruh subtotal items.
- `addItem()` / `removeItem()` — tambah/hapus baris item; `mount()` inisialisasi 1 baris kosong.
- Tombol hapus item (`x-show="items.length > 1"`) disembunyikan saat cuma 1 item — minimal 1 baris.
- Subtotal & total real-time via Alpine `x-text` dengan `toLocaleString('id-ID')` rupiah-style, tanpa roundtrip Livewire server.
- Custom `rules()` + `messages()` untuk validasi nested array items.
- `generate()` hitung `$total` dari `$this->total`, validasi > 0, passing `items` ke service + simpan ke DB sebagai JSON.
- `getFaktursProperty()` + `$this->fakturs` = computed property (auto-cache per request).
- Download via `$faktur->pdf_url` (signed URL B2, valid 3 jam).
- Hapus: `delete()` cek ownership, hapus file B2 + record DB.
- Format rupiah di tabel riwayat pakai accessor `Faktur::nominal_rupiah`.

---

## 9. Navigasi (Sidebar)

`resources/views/layouts/app/sidebar.blade.php` dan `header.blade.php` — setelah item QR Code:

```blade
<flux:sidebar.item icon="document-text" :href="route('faktur.generate')" :current="request()->routeIs('faktur.*')" wire:navigate>
    {{ __('Cetak Faktur') }}
</flux:sidebar.item>
```

---

## 10. Format Pint

```bash
vendor/bin/pint --dirty --format agent
```

---

## 11. Testing

```bash
php artisan make:test --phpunit FakturGenerateTest --no-interaction
```

`tests/Feature/FakturGenerateTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Faktur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FakturGenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('b2');
    }

    public function test_halaman_faktur_tampil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('faktur.generate'))
            ->assertOk()
            ->assertSee('Cetak Faktur');
    }

    public function test_generate_faktur_default_a4_tanpa_logo(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::faktur.generate')
            ->set('nama', 'Budi Santoso')
            ->set('items', [
                ['description' => 'Jasa konsultasi', 'qty' => 1, 'price' => 100000, 'subtotal' => 100000],
                ['description' => 'Biaya administrasi', 'qty' => 2, 'price' => 25000, 'subtotal' => 50000],
            ])
            ->set('terbilang', 'Seratus lima puluh ribu rupiah')
            ->set('memo', 'Lunas sebelum tanggal 10.')
            ->set('paperSize', 'a4')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertNotSet('previewDataUri', null);

        $this->assertDatabaseHas(Faktur::class, [
            'user_id' => $user->id,
            'nama' => 'Budi Santoso',
            'paper_size' => 'a4',
            'logo_path' => null,
            'nominal' => 150000.00,
        ]);

        $faktur = Faktur::first();
        $this->assertIsArray($faktur->items);
        $this->assertCount(2, $faktur->items);
        Storage::disk('b2')->assertExists($faktur->pdf_path);
    }

    public function test_generate_faktur_dengan_logo_dan_ukuran_third_a4(): void
    {
        Storage::fake('b2');
        $user = User::factory()->create();
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        Livewire::actingAs($user)
            ->test('pages::faktur.generate')
            ->set('nama', 'PT Contoh')
            ->set('items', [
                ['description' => 'Pengembangan software', 'qty' => 1, 'price' => 2500000, 'subtotal' => 2500000],
            ])
            ->set('terbilang', 'Dua juta lima ratus ribu rupiah')
            ->set('logo', $logo)
            ->set('paperSize', 'third_a4')
            ->call('generate')
            ->assertHasNoErrors();

        $faktur = Faktur::first();
        $this->assertNotNull($faktur->logo_path);
        Storage::disk('b2')->assertExists($faktur->logo_path);
        Storage::disk('b2')->assertExists($faktur->pdf_path);
    }

    public function test_validasi_wajib(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::faktur.generate')
            ->set('nama', '')
            ->set('items', [])
            ->set('terbilang', '')
            ->call('generate')
            ->assertHasErrors(['nama', 'items', 'terbilang']);
    }

    public function test_validasi_item_kosong(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::faktur.generate')
            ->set('nama', 'Test')
            ->set('items', [
                ['description' => '', 'qty' => 0, 'price' => 0, 'subtotal' => 0],
            ])
            ->set('terbilang', 'Test')
            ->call('generate')
            ->assertHasErrors(['items.0.description']);
    }

    public function test_paper_size_invalid_ditolak(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::faktur.generate')
            ->set('nama', 'X')
            ->set('items', [
                ['description' => 'Item x', 'qty' => 1, 'price' => 1000, 'subtotal' => 1000],
            ])
            ->set('terbilang', 'Seribu')
            ->set('paperSize', 'b5')
            ->call('generate')
            ->assertHasErrors(['paperSize']);
    }

    public function test_delete_faktur_menghapus_file_b2(): void
    {
        $user = User::factory()->create();
        $faktur = Faktur::factory()->create([
            'user_id' => $user->id,
            'pdf_path' => 'faktur/documents/test.pdf',
        ]);
        Storage::disk('b2')->put($faktur->pdf_path, 'dummy');

        Livewire::actingAs($user)
            ->test('pages::faktur.generate')
            ->call('delete', $faktur->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing(Faktur::class, ['id' => $faktur->id]);
        Storage::disk('b2')->assertMissing($faktur->pdf_path);
    }
}
```

Jalankan:

```bash
php artisan test --compact tests/Feature/FakturGenerateTest.php
```

> `Storage::fake('b2')` men-DI-override disk `b2` ke local temp — aman, tak butuh kredensial asli. Pastikan disk `b2` terdefinisi di `config/filesystems.php`.

---

## 12. Catatan

- **Dynamic items**: form pakai Alpine.js + `@entangle('items')` — setiap perubahan qty/price langsung update subtotal & total di client tanpa roundtrip server. Validasi items via `rules()` dengan nested array rules `items.*.description`, `items.*.qty`, `items.*.price`. Minimal 1 item wajib (`min:1`).
- **Nominal auto-sum**: dihitung dari total subtotal seluruh items via `getTotalProperty()` (`$this->total`), disimpan ke `fakturs.nominal` (decimal). Tidak ada input nominal manual — total selalu = sum(items[].subtotal).
- **Logo**: opsional. Di-embed ke PDF via **data URI base64** (rekomendasi) agar DOMPDF tak butuh fetch jaringan ke B2 saat render. Validasi: image, max 2MB, mimes jpg/jpeg/png/svg/webp.
- **Ukuran kertas**: `a4` (portrait), `half_a4` = 148×210mm portrait (≈A5), `third_a4` = 210×99mm landscape. Mapping di `FakturPdfService::paperConfig()`. Tambah/ubah opsi di sana + di `<select>` page.
- **Storage B2**: file PDF di `faktur/documents/`, logo di `faktur/logos/`. Bucket B2 sebaiknya **private** + akses via `temporaryUrl` (signed). Bila bucket public, `url()` langsung. Sesuai panduan B2 bagian 6.1/6.2.
- **Download**: via `$faktur->pdf_url` (signed URL B2, valid 3 jam) — aman untuk private bucket. Atau route `faktur.download/{faktur}` dengan cek ownership + `Storage::disk('b2')->download()`.
- **Ownership**: `delete()` & route download cek `user_id === auth()->id()`. Riwayat di tabel hanya milik user login (`where('user_id', auth()->id())`).
- **Terbilang manual**: field diisi user. Auto-generate dari nominal di luar scope (bisa tambah helper `terbilang()`).
- **Cleanup**: file PDF + logo dihapus dari B2 saat record dihapus via `Faktur::deleteAllFiles()`.
- **Scheduled cleanup (opsional)**: bila ingin hapus faktur lama otomatis, buat command `php artisan make:command PurgeOldFakturs` + schedule — hapus record + file B2 berdasarkan umur.

---

Selesai. Urutan implementasi: install DOMPDF → migration/model/factory → service → blade PDF → Livewire page → route → nav → pint → test.

