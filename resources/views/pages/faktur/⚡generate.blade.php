<?php

use App\Models\Faktur;
use App\Services\FakturPdfService;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Helpers\Terbilang;
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
        $this->updateTerbilang();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->updateTerbilang();
    }

    public function updateItem(int $index, string $field, mixed $value): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index][$field] = $field === 'description' ? (string) $value : (float) $value;

        $qty = (int) ($this->items[$index]['qty'] ?? 0);
        $price = (float) ($this->items[$index]['price'] ?? 0);
        $this->items[$index]['subtotal'] = $qty * $price;

        $this->updateTerbilang();
    }

    public function updateTerbilang(): void
    {
        $total = $this->total;
        if ($total > 0) {
            $words = Terbilang::make($total);
            $this->terbilang = ucfirst($words) . ' rupiah';
        } else {
            $this->terbilang = '';
        }
    }

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
            $nomorFaktur = 'INV-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
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

            // Preview via signed URL (file already on B2) — lebih andal dari data URI base64.
            $this->previewDataUri = Storage::disk('b2')->temporaryUrl($result['pdf_path'], now()->addHours(3));

            $this->reset(['nama', 'items', 'terbilang', 'memo', 'logo', 'paperSize']);
            $this->paperSize = 'a4';
            $this->addItem();

            Flux::toast(variant: 'success', text: 'Faktur PDF dibuat & disimpan ke B2.');
        } catch (\Throwable $e) {
            $this->generateError = $e->getMessage();
            Flux::toast(variant: 'error', text: 'Gagal membuat faktur. ' . $this->generateError);
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
        {{-- Kolom Kiri: Form --}}
        <div class="space-y-5">
            @if ($generateError)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    <div class="font-medium">{{ __('Gagal membuat faktur') }}</div>
                    <div class="mt-1 break-words">{{ $generateError }}</div>
                </div>
            @endif

            <form wire:submit="generate" class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('Nama') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="nama" placeholder="Nama penerima / pelanggan" data-test="input-nama" />
                    <flux:error name="nama" />
                </flux:field>

                {{-- Dynamic Items --}}
                <fieldset class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
                    x-data="{
                        items: @entangle('items'),
                        pushField(index, field, value) {
                            $wire.call('updateItem', index, field, value);
                        },
                        subtotal(item) {
                            return Number(item.qty || 0) * Number(item.price || 0);
                        },
                        get grandTotal() {
                            return (this.items || []).reduce((s, i) => s + this.subtotal(i), 0);
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
                                    <th class="w-[5%] px-3 py-2">#</th>
                                    <th class="px-3 py-2">Deskripsi</th>
                                    <th class="w-[20%] px-3 py-2 text-center">Qty</th>
                                    <th class="w-[22%] px-3 py-2 text-right">Harga Satuan</th>
                                    <th class="w-[22%] px-3 py-2 text-right">Subtotal</th>
                                    <th class="w-[5%] px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr>
                                        <td class="px-3 py-2 text-center text-zinc-400" x-text="index + 1"></td>
                                        <td class="px-3 py-2">
                                            <input
                                                type="text"
                                                x-bind:value="item.description"
                                                x-on:input="item.description = $event.target.value; pushField(index, 'description', $event.target.value)"
                                                placeholder="Nama item / jasa"
                                                class="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                                data-test="input-item-desc"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                x-bind:value="item.qty"
                                                x-on:input="item.qty = Number($event.target.value); item.subtotal = subtotal(item)"
                                                x-on:change="pushField(index, 'qty', item.qty)"
                                                class="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-center text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                                data-test="input-item-qty"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                type="number"
                                                min="0"
                                                step="1"
                                                x-bind:value="item.price"
                                                x-on:input="item.price = Number($event.target.value); item.subtotal = subtotal(item)"
                                                x-on:change="pushField(index, 'price', item.price)"
                                                class="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-right text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                                data-test="input-item-price"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="'Rp ' + Number(item.subtotal || 0).toLocaleString('id-ID')"></td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button"
                                                x-show="items.length > 1"
                                                x-on:click="$wire.removeItem(index)"
                                                class="text-lg leading-none text-red-500 hover:text-red-700"
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
                        <span class="font-mono" x-text="'Rp ' + grandTotal.toLocaleString('id-ID')"></span>
                    </div>
                </fieldset>

                <flux:field>
                    <flux:label>{{ __('Terbilang') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <div class="flex items-stretch gap-2">
                        <flux:input wire:model="terbilang" placeholder="Seratus lima puluh ribu rupiah" data-test="input-terbilang" class="flex-1" />
                        <flux:button type="button" wire:click="updateTerbilang" size="sm" variant="ghost" icon="sparkles" title="{{ __('Generate otomatis dari total') }}" />
                    </div>
                    <flux:description>{{ __('Terisi otomatis saat harga/qty diubah. Klik ✨ untuk generate ulang dari total.') }}</flux:description>
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
        </div>

        {{-- Kolom Kanan: Preview & Riwayat --}}
        <aside class="space-y-6">
            @if ($previewDataUri)
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                    <flux:heading size="lg">{{ __('Preview Faktur Terakhir') }}</flux:heading>
                    <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <iframe src="{{ $previewDataUri }}#toolbar=0" class="h-[600px] w-full" data-test="faktur-preview"></iframe>
                    </div>
                </div>
            @endif

            {{-- Riwayat Faktur --}}
            <div>
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
                                    <td class="px-4 py-3 text-right">{{ $faktur->nominal_rupiah }}</td>
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
        </aside>
    </div>
</div>
