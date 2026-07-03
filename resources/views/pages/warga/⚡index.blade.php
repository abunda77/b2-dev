<?php

use App\Models\Warga;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Data Warga')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;
    use WithPagination;

    // Search & filter
    public string $search = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    // Form fields
    public ?int $editingId = null;

    #[Validate]
    public string $nik = '';

    #[Validate]
    public string $nama = '';

    #[Validate]
    public string $alamat = '';

    #[Validate]
    public $pasFoto = null;

    #[Validate]
    public $dokumen = null;

    // Existing file paths (saat edit)
    public ?string $existingPasFoto = null;
    public ?string $existingDokumen = null;

    // ID yang akan dihapus
    public ?int $wargaToDeleteId = null;
    public ?string $wargaToDeleteNama = null;

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nik' => ['required', 'digits:16', 'unique:wargas,nik' . ($this->editingId ? ',' . $this->editingId : '')],
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string', 'max:1000'],
            'pasFoto' => $this->editingId
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']
                : ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'dokumen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * Validation attribute names.
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'nik' => 'NIK',
            'nama' => 'Nama',
            'alamat' => 'Alamat',
            'pasFoto' => 'Pas Foto',
            'dokumen' => 'Dokumen',
        ];
    }

    /**
     * Paginated list of wargas based on search query.
     */
    #[Computed]
    public function wargas(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Warga::query()
            ->when($this->search, fn ($q) => $q->where('nama', 'like', '%' . $this->search . '%')
                ->orWhere('nik', 'like', '%' . $this->search . '%'))
            ->latest()
            ->paginate(10);
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Open modal for creating a new warga.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    /**
     * Open modal for editing an existing warga.
     */
    public function edit(int $id): void
    {
        $warga = Warga::findOrFail($id);

        $this->editingId = $warga->id;
        $this->nik = $warga->nik;
        $this->nama = $warga->nama;
        $this->alamat = $warga->alamat;
        $this->existingPasFoto = $warga->pas_foto;
        $this->existingDokumen = $warga->dokumen;
        $this->pasFoto = null;
        $this->dokumen = null;

        $this->showFormModal = true;
    }

    /**
     * Save (create or update) a warga record.
     * Uses DB transaction for atomicity. Deletes old files only after DB save succeeds.
     */
    public function save(): void
    {
        $this->validate();

        $disk = 'b2';
        $oldPasFoto = null;
        $oldDokumen = null;

        DB::transaction(function () use ($disk, &$oldPasFoto, &$oldDokumen) {
            if ($this->editingId) {
                /** @var Warga $warga */
                $warga = Warga::findOrFail($this->editingId);
                $oldPasFoto = $warga->pas_foto;
                $oldDokumen = $warga->dokumen;

                // Upload pas_foto baru jika ada
                if ($this->pasFoto) {
                    try {
                        $warga->pas_foto = $this->pasFoto->storePubliclyAs(
                            'warga/pas_foto',
                            $this->generateFileName($this->pasFoto),
                            $disk
                        );
                    } catch (\Exception $e) {
                        Log::error('Gagal upload pas_foto', ['error' => $e->getMessage()]);
                        Flux::toast(variant: 'error', text: 'Gagal mengupload pas foto.');

                        throw $e;
                    }
                }

                // Upload dokumen baru jika ada
                if ($this->dokumen) {
                    try {
                        $warga->dokumen = $this->dokumen->storePubliclyAs(
                            'warga/dokumen',
                            $this->generateFileName($this->dokumen),
                            $disk
                        );
                    } catch (\Exception $e) {
                        Log::error('Gagal upload dokumen', ['error' => $e->getMessage()]);
                        Flux::toast(variant: 'error', text: 'Gagal mengupload dokumen.');

                        throw $e;
                    }
                }

                $warga->nik = $this->nik;
                $warga->nama = $this->nama;
                $warga->alamat = $this->alamat;
                $warga->save();

                Flux::toast(variant: 'success', text: "Data warga {$warga->nama} berhasil diperbarui.");
            } else {
                // Upload pas_foto
                try {
                    $pasFotoPath = $this->pasFoto->storePubliclyAs(
                        'warga/pas_foto',
                        $this->generateFileName($this->pasFoto),
                        $disk
                    );
                } catch (\Exception $e) {
                    Log::error('Gagal upload pas_foto', ['error' => $e->getMessage()]);
                    Flux::toast(variant: 'error', text: 'Gagal mengupload pas foto.');

                    throw $e;
                }

                // Upload dokumen jika ada
                $dokumenPath = null;
                if ($this->dokumen) {
                    try {
                        $dokumenPath = $this->dokumen->storePubliclyAs(
                            'warga/dokumen',
                            $this->generateFileName($this->dokumen),
                            $disk
                        );
                    } catch (\Exception $e) {
                        Log::error('Gagal upload dokumen', ['error' => $e->getMessage()]);
                        Flux::toast(variant: 'error', text: 'Gagal mengupload dokumen.');

                        throw $e;
                    }
                }

                $warga = Warga::create([
                    'nik' => $this->nik,
                    'nama' => $this->nama,
                    'alamat' => $this->alamat,
                    'pas_foto' => $pasFotoPath,
                    'dokumen' => $dokumenPath,
                ]);

                Flux::toast(variant: 'success', text: "Data warga {$warga->nama} berhasil ditambahkan.");
            }
        });

        // Hapus file lama setelah transaksi sukses (atomic)
        if ($oldPasFoto && str_starts_with($oldPasFoto, 'warga/pas_foto/')) {
            try {
                Storage::disk($disk)->delete($oldPasFoto);
            } catch (\Exception $e) {
                Log::error('Gagal hapus pas_foto lama', ['path' => $oldPasFoto, 'error' => $e->getMessage()]);
            }
        }
        if ($oldDokumen && str_starts_with($oldDokumen, 'warga/dokumen/')) {
            try {
                Storage::disk($disk)->delete($oldDokumen);
            } catch (\Exception $e) {
                Log::error('Gagal hapus dokumen lama', ['path' => $oldDokumen, 'error' => $e->getMessage()]);
            }
        }

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->wargas);
    }

    /**
     * Open delete confirmation modal.
     */
    public function confirmDelete(int $id): void
    {
        $warga = Warga::findOrFail($id);
        $this->wargaToDeleteId = $warga->id;
        $this->wargaToDeleteNama = $warga->nama;
        $this->showDeleteModal = true;
    }

    /**
     * Delete a warga record and its associated files from B2.
     * Validates path prefix before deletion. Logs storage errors.
     */
    public function delete(): void
    {
        if (! $this->wargaToDeleteId) {
            return;
        }

        $warga = Warga::findOrFail($this->wargaToDeleteId);
        $disk = 'b2';
        $nama = $warga->nama;
        $pasFoto = $warga->pas_foto;
        $dokumen = $warga->dokumen;

        $warga->delete();

        // Hapus file setelah DB delete sukses, dengan validasi path
        if ($pasFoto && str_starts_with($pasFoto, 'warga/pas_foto/')) {
            try {
                Storage::disk($disk)->delete($pasFoto);
            } catch (\Exception $e) {
                Log::error('Gagal hapus pas_foto', ['path' => $pasFoto, 'error' => $e->getMessage()]);
            }
        }

        if ($dokumen && str_starts_with($dokumen, 'warga/dokumen/')) {
            try {
                Storage::disk($disk)->delete($dokumen);
            } catch (\Exception $e) {
                Log::error('Gagal hapus dokumen', ['path' => $dokumen, 'error' => $e->getMessage()]);
            }
        }

        $this->showDeleteModal = false;
        $this->wargaToDeleteId = null;
        $this->wargaToDeleteNama = null;

        Flux::toast(variant: 'success', text: "Data warga {$nama} berhasil dihapus.");
        unset($this->wargas);
    }

    /**
     * Reset all form fields.
     */
    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nik = '';
        $this->nama = '';
        $this->alamat = '';
        $this->pasFoto = null;
        $this->dokumen = null;
        $this->existingPasFoto = null;
        $this->existingDokumen = null;
        $this->resetValidation();
    }

    /**
     * Generate a unique filename for an uploaded file.
     */
    private function generateFileName(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string
    {
        return uniqid('', true) . '_' . time() . '.' . $file->getClientOriginalExtension();
    }
}; ?>

<div class="space-y-6">
            {{-- Page Header --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="xl" level="1">{{ __('Data Warga') }}</flux:heading>
                    <flux:subheading>{{ __('Kelola data warga beserta dokumen pendukung.') }}</flux:subheading>
                </div>
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:click="create"
                    data-test="btn-tambah-warga"
                >
                    {{ __('Tambah Warga') }}
                </flux:button>
            </div>

            {{-- Search --}}
            <div class="max-w-sm">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Cari nama atau NIK...') }}"
                    clearable
                />
            </div>

            {{-- Table --}}
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Pas Foto') }}</flux:table.column>
                    <flux:table.column sortable>{{ __('NIK') }}</flux:table.column>
                    <flux:table.column sortable>{{ __('Nama') }}</flux:table.column>
                    <flux:table.column>{{ __('Alamat') }}</flux:table.column>
                    <flux:table.column>{{ __('Dokumen') }}</flux:table.column>
                    <flux:table.column>{{ __('Aksi') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->wargas as $warga)
                        <flux:table.row wire:key="warga-{{ $warga->id }}">
                            {{-- Pas Foto --}}
                            <flux:table.cell>
                                @if ($warga->pas_foto)
                                    @php($pasFotoUrl = $warga->pas_foto_url)
                                    <a href="{{ $pasFotoUrl }}" target="_blank" class="block">
                                        <img
                                            src="{{ $pasFotoUrl }}"
                                            alt="Pas foto {{ $warga->nama }}"
                                            class="h-12 w-10 rounded object-cover ring-1 ring-zinc-200 dark:ring-zinc-700"
                                        />
                                    </a>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Tidak ada') }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            {{-- NIK --}}
                            <flux:table.cell class="font-mono text-sm">
                                {{ $warga->nik }}
                            </flux:table.cell>

                            {{-- Nama --}}
                            <flux:table.cell class="font-medium">
                                {{ $warga->nama }}
                            </flux:table.cell>

                            {{-- Alamat --}}
                            <flux:table.cell class="max-w-xs">
                                <flux:tooltip :content="$warga->alamat">
                                    <span class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $warga->alamat }}
                                    </span>
                                </flux:tooltip>
                            </flux:table.cell>

                            {{-- Dokumen --}}
                            <flux:table.cell>
                                @if ($warga->dokumen)
                                    <flux:button
                                        size="sm"
                                        icon="arrow-down-tray"
                                        variant="ghost"
                                        :href="$warga->dokumen_url"
                                        target="_blank"
                                    >
                                        {{ __('Lihat') }}
                                    </flux:button>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Tidak ada') }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        size="sm"
                                        icon="pencil"
                                        variant="ghost"
                                        wire:click="edit({{ $warga->id }})"
                                        data-test="btn-edit-{{ $warga->id }}"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="ghost"
                                        class="text-red-500 hover:text-red-600 dark:text-red-400"
                                        wire:click="confirmDelete({{ $warga->id }})"
                                        data-test="btn-hapus-{{ $warga->id }}"
                                    >
                                        {{ __('Hapus') }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-zinc-400">
                                    <flux:icon name="users" class="size-10 opacity-40" />
                                    <p class="text-sm">
                                        {{ $search ? __('Tidak ada warga yang cocok dengan pencarian.') : __('Belum ada data warga.') }}
                                    </p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            {{-- Pagination --}}
            @if ($this->wargas->hasPages())
                <div class="mt-4">
                    {{ $this->wargas->links() }}
                </div>
            @endif

        {{-- ============================================= --}}
        {{-- Modal: Form Tambah / Edit                     --}}
        {{-- ============================================= --}}
        <flux:modal wire:model="showFormModal" class="w-full max-w-lg">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit Data Warga') : __('Tambah Warga Baru') }}
            </flux:heading>
            <flux:subheading>
                {{ $editingId ? __('Perbarui informasi warga dan unggah file jika perlu.') : __('Isi data warga dan unggah pas foto.') }}
            </flux:subheading>

            <form wire:submit="save" class="mt-6 space-y-5" enctype="multipart/form-data">

                {{-- NIK --}}
                <flux:field>
                    <flux:label>{{ __('NIK') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:input
                        wire:model="nik"
                        type="text"
                        inputmode="numeric"
                        maxlength="16"
                        placeholder="16 digit NIK"
                        data-test="input-nik"
                    />
                    <flux:error name="nik" />
                </flux:field>

                {{-- Nama --}}
                <flux:field>
                    <flux:label>{{ __('Nama Lengkap') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:input
                        wire:model="nama"
                        type="text"
                        placeholder="Nama lengkap warga"
                        data-test="input-nama"
                    />
                    <flux:error name="nama" />
                </flux:field>

                {{-- Alamat --}}
                <flux:field>
                    <flux:label>{{ __('Alamat') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:textarea
                        wire:model="alamat"
                        rows="3"
                        placeholder="Alamat lengkap tempat tinggal"
                        data-test="input-alamat"
                    />
                    <flux:error name="alamat" />
                </flux:field>

                {{-- Pas Foto --}}
                <flux:field>
                    <flux:label>
                        {{ __('Pas Foto') }}
                        @if (!$editingId)
                            <flux:badge size="sm" color="red">Wajib</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Opsional — biarkan kosong jika tidak ingin mengganti') }}</flux:badge>
                        @endif
                    </flux:label>

                    {{-- Preview existing --}}
                    @if ($editingId && $existingPasFoto && !$pasFoto)
                        <div class="mb-2 flex items-center gap-3">
                            <img
                                src="{{ \App\Models\Warga::b2Url($existingPasFoto) }}"
                                alt="Pas foto saat ini"
                                class="h-16 w-14 rounded-md object-cover ring-1 ring-zinc-300 dark:ring-zinc-600"
                            />
                            <flux:text size="sm" class="text-zinc-500">{{ __('Foto saat ini') }}</flux:text>
                        </div>
                    @endif

                    {{-- Preview new upload --}}
                    @if ($pasFoto)
                        <div class="mb-2">
                            <img
                                src="{{ $pasFoto->temporaryUrl() }}"
                                alt="Preview pas foto"
                                class="h-20 w-16 rounded-md object-cover ring-2 ring-blue-400"
                            />
                        </div>
                    @endif

                    <flux:input
                        type="file"
                        wire:model="pasFoto"
                        accept="image/jpg,image/jpeg,image/png,image/webp"
                        data-test="input-pas-foto"
                    />
                    <flux:description>{{ __('Format: JPG, JPEG, PNG, WebP. Maks 2 MB.') }}</flux:description>
                    <flux:error name="pasFoto" />
                </flux:field>

                {{-- Dokumen --}}
                <flux:field>
                    <flux:label>
                        {{ __('Dokumen Pendukung') }}
                        <flux:badge size="sm" color="zinc">{{ __('Opsional') }}</flux:badge>
                    </flux:label>

                    {{-- Existing dokumen info --}}
                    @if ($editingId && $existingDokumen && !$dokumen)
                        <div class="mb-2 flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <flux:icon name="document" class="size-5 text-zinc-400" />
                            <flux:text size="sm" class="text-zinc-500">{{ __('Sudah ada dokumen tersimpan') }}</flux:text>
                        </div>
                    @endif

                    {{-- Preview new dokumen --}}
                    @if ($dokumen)
                        <div class="mb-2 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-800 dark:bg-blue-950">
                            <flux:icon name="document-check" class="size-5 text-blue-500" />
                            <flux:text size="sm" class="text-blue-600 dark:text-blue-400">
                                {{ $dokumen->getClientOriginalName() }}
                            </flux:text>
                        </div>
                    @endif

                    <flux:input
                        type="file"
                        wire:model="dokumen"
                        accept="image/jpg,image/jpeg,image/png,image/webp,application/pdf"
                        data-test="input-dokumen"
                    />
                    <flux:description>{{ __('Format: JPG, JPEG, PNG, WebP, PDF. Maks 5 MB.') }}</flux:description>
                    <flux:error name="dokumen" />
                </flux:field>

                {{-- Actions --}}
                <div class="flex justify-end gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="$set('showFormModal', false)"
                    >
                        {{ __('Batal') }}
                    </flux:button>
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        data-test="btn-simpan"
                    >
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? __('Perbarui') : __('Simpan') }}
                        </span>
                        <span wire:loading wire:target="save">
                            {{ __('Menyimpan...') }}
                        </span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- ============================================= --}}
        {{-- Modal: Konfirmasi Hapus                       --}}
        {{-- ============================================= --}}
        <flux:modal wire:model="showDeleteModal" class="max-w-sm">
            <div class="flex flex-col items-center gap-4 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-7 text-red-500" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Hapus Data Warga') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Apakah Anda yakin ingin menghapus data') }}
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $wargaToDeleteNama }}</span>?
                        {{ __('File pas foto dan dokumen di storage juga akan dihapus. Tindakan ini tidak dapat dibatalkan.') }}
                    </flux:text>
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-3">
                <flux:button
                    variant="ghost"
                    wire:click="$set('showDeleteModal', false)"
                >
                    {{ __('Batal') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="delete"
                    wire:loading.attr="disabled"
                    wire:target="delete"
                    data-test="btn-konfirmasi-hapus"
                >
                    {{ __('Ya, Hapus') }}
                </flux:button>
            </div>
        </flux:modal>
</div>