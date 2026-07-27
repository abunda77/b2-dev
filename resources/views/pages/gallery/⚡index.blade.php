<?php

use App\Models\GalleryPhoto;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Direction;
use Intervention\Image\Encoders\JpegEncoder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Gallery')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;
    use WithPagination;

    // ------------------------------------------------------------------
    // Upload form
    // ------------------------------------------------------------------

    #[Validate(['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'])]
    public $photo = null;

    #[Validate(['required', 'string', 'max:255'])]
    public string $title = '';

    #[Validate(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    public bool $showUploadModal = false;

    public bool $uploading = false;

    // ------------------------------------------------------------------
    // Edit / processing modal
    // ------------------------------------------------------------------

    public bool $showEditModal = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $editSettings = [
        'width'      => 800,
        'height'     => 600,
        'quality'    => 85,
        'brightness' => 0,      // -100 … +100
        'contrast'   => 0,      // -100 … +100
        'blur'       => 0,      // 0 … 20
        'flip'       => 'none', // none | h | v
        'rotate'     => 0,      // 0 | 90 | 180 | 270
        'grayscale'  => false,
    ];

    // ------------------------------------------------------------------
    // Delete modal
    // ------------------------------------------------------------------

    public bool $showDeleteModal = false;

    public ?int $photoToDeleteId = null;

    public ?string $photoToDeleteTitle = null;

    // ------------------------------------------------------------------
    // View modal
    // ------------------------------------------------------------------

    public bool $showViewModal = false;

    public ?GalleryPhoto $viewingPhoto = null;

    // ------------------------------------------------------------------
    // Search
    // ------------------------------------------------------------------

    public string $search = '';

    // ------------------------------------------------------------------
    // Computed
    // ------------------------------------------------------------------

    #[Computed]
    public function photoList(): LengthAwarePaginator
    {
        return GalleryPhoto::query()
            ->whereBelongsTo($this->user())
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->orderByDesc('id')
            ->paginate(12);
    }

    // ------------------------------------------------------------------
    // Events
    // ------------------------------------------------------------------

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ------------------------------------------------------------------
    // Upload
    // ------------------------------------------------------------------

    public function openUpload(): void
    {
        $this->reset(['photo', 'title', 'description', 'uploading']);
        $this->resetValidation();
        $this->showUploadModal = true;
    }

    public function savePhoto(): void
    {
        $this->validate();
        $this->uploading = true;

        /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file */
        $file = $this->photo;

        // Read original image info
        $image  = Image::decode($file->getRealPath());
        $width  = $image->width();
        $height = $image->height();
        $mime   = $file->getMimeType() ?? 'image/jpeg';
        $size   = $file->getSize();

        // Build destination path in B2
        $ext      = $file->guessExtension() ?? 'jpg';
        $filename = now()->format('Ymd_His').'_'.uniqid().'.'.$ext;
        $b2Path   = 'gallery/original/'.$filename;

        // Upload original to B2
        Storage::disk('b2')->put($b2Path, file_get_contents($file->getRealPath()), 'public');

        GalleryPhoto::create([
            'user_id'         => $this->user()->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'original_path'   => $b2Path,
            'disk'            => 'b2',
            'original_width'  => $width,
            'original_height' => $height,
            'file_size'       => $size,
            'mime_type'       => $mime,
        ]);

        $this->uploading = false;
        $this->showUploadModal = false;
        $this->reset(['photo', 'title', 'description']);
        $this->resetPage();
        unset($this->photoList);

        Flux::toast(variant: 'success', text: 'Foto berhasil diunggah ke Backblaze B2.');
    }

    // ------------------------------------------------------------------
    // View detail
    // ------------------------------------------------------------------

    public function showPhoto(int $id): void
    {
        $this->viewingPhoto = GalleryPhoto::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->showViewModal = true;
    }

    // ------------------------------------------------------------------
    // Edit / Processing
    // ------------------------------------------------------------------

    public function openEdit(int $id): void
    {
        $photo = GalleryPhoto::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->editingId    = $photo->id;
        $this->editSettings = [
            'width'      => $photo->original_width ?? 800,
            'height'     => $photo->original_height ?? 600,
            'quality'    => 85,
            'brightness' => 0,
            'contrast'   => 0,
            'blur'       => 0,
            'flip'       => 'none',
            'rotate'     => 0,
            'grayscale'  => false,
        ];

        $this->showEditModal = true;
    }

    public function applyEdit(): void
    {
        $this->validate([
            'editSettings.width'      => ['required', 'integer', 'min:50', 'max:4096'],
            'editSettings.height'     => ['required', 'integer', 'min:50', 'max:4096'],
            'editSettings.quality'    => ['required', 'integer', 'min:10', 'max:100'],
            'editSettings.brightness' => ['required', 'integer', 'min:-100', 'max:100'],
            'editSettings.contrast'   => ['required', 'integer', 'min:-100', 'max:100'],
            'editSettings.blur'       => ['required', 'integer', 'min:0', 'max:20'],
            'editSettings.flip'       => ['required', 'in:none,h,v'],
            'editSettings.rotate'     => ['required', 'in:0,90,180,270'],
            'editSettings.grayscale'  => ['boolean'],
        ]);

        $photo = GalleryPhoto::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($this->editingId);

        // Download original from B2 into temp memory
        $originalContent = Storage::disk('b2')->get($photo->original_path);

        // Process with Intervention Image
        $image = Image::decode($originalContent);

        // Resize
        $image->scale(
            width: (int) $this->editSettings['width'],
            height: (int) $this->editSettings['height'],
        );

        // Brightness  (-100 … +100 → Intervention uses -100…100 natively)
        if ($this->editSettings['brightness'] !== 0) {
            $image->brightness((int) $this->editSettings['brightness']);
        }

        // Contrast
        if ($this->editSettings['contrast'] !== 0) {
            $image->contrast((int) $this->editSettings['contrast']);
        }

        // Blur
        if ((int) $this->editSettings['blur'] > 0) {
            $image->blur((int) $this->editSettings['blur']);
        }

        // Grayscale
        if ($this->editSettings['grayscale']) {
            $image->grayscale();
        }

        // Flip
        if ($this->editSettings['flip'] === 'h') {
            $image->flip(Direction::HORIZONTAL);
        } elseif ($this->editSettings['flip'] === 'v') {
            $image->flip(Direction::VERTICAL);
        }

        // Rotate
        if ((int) $this->editSettings['rotate'] > 0) {
            $image->rotate((int) $this->editSettings['rotate']);
        }

        // Encode to JPEG
        $encoded = $image->encode(new JpegEncoder(quality: (int) $this->editSettings['quality']));

        // Upload edited to B2
        $editedFilename = now()->format('Ymd_His').'_'.uniqid().'_edited.jpg';
        $editedPath     = 'gallery/edited/'.$editedFilename;

        Storage::disk('b2')->put($editedPath, (string) $encoded, 'public');

        // Delete old edited file if exists
        if ($photo->edited_path) {
            Storage::disk('b2')->delete($photo->edited_path);
        }

        $photo->update(['edited_path' => $editedPath]);

        $this->showEditModal = false;
        $this->editingId     = null;
        unset($this->photoList);

        Flux::toast(variant: 'success', text: 'Foto berhasil diedit dan disimpan ke Backblaze B2.');
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function confirmDelete(int $id): void
    {
        $photo = GalleryPhoto::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->photoToDeleteId    = $photo->id;
        $this->photoToDeleteTitle = $photo->title;
        $this->showDeleteModal    = true;
    }

    public function delete(): void
    {
        if (! $this->photoToDeleteId) {
            return;
        }

        $photo = GalleryPhoto::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($this->photoToDeleteId);

        // Delete files from B2
        Storage::disk('b2')->delete($photo->original_path);

        if ($photo->edited_path) {
            Storage::disk('b2')->delete($photo->edited_path);
        }

        $photo->delete();

        $this->showDeleteModal    = false;
        $this->photoToDeleteId    = null;
        $this->photoToDeleteTitle = null;
        $this->resetPage();
        unset($this->photoList);

        Flux::toast(variant: 'success', text: 'Foto berhasil dihapus dari galeri dan Backblaze B2.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function user(): User
    {
        return auth()->user();
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Gallery') }}</flux:heading>
            <flux:subheading>{{ __('Unggah & edit foto. Disimpan di Backblaze B2.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openUpload">
            {{ __('Upload Foto') }}
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="max-w-sm">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Cari judul foto...') }}"
            clearable
        />
    </div>

    {{-- Photo Grid --}}
    @if ($this->photoList->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-300 py-20 text-zinc-400 dark:border-zinc-700">
            <flux:icon name="photo" class="mb-3 size-14 opacity-30" />
            <p class="text-sm">
                {{ $search ? __('Tidak ada foto yang cocok.') : __('Belum ada foto. Klik "Upload Foto" untuk mulai.') }}
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($this->photoList as $item)
                <div wire:key="photo-{{ $item->id }}" class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    {{-- Thumbnail --}}
                    <div class="aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        <img
                            src="{{ $item->active_url }}"
                            alt="{{ $item->title }}"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                    </div>

                    {{-- Info --}}
                    <div class="p-3">
                        <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item->title }}</p>
                        <div class="mt-1 flex items-center gap-2 text-xs text-zinc-400">
                            <span>{{ $item->original_width }}×{{ $item->original_height }}px</span>
                            <span>·</span>
                            <span>{{ $item->file_size_human }}</span>
                        </div>
                        @if ($item->edited_path)
                            <flux:badge size="sm" color="green" class="mt-1">Edited</flux:badge>
                        @endif
                    </div>

                    {{-- Action overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                        <flux:button size="sm" variant="filled" icon="eye"    wire:click="showPhoto({{ $item->id }})">View</flux:button>
                        <flux:button size="sm" variant="filled" icon="pencil" wire:click="openEdit({{ $item->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="filled" icon="trash"  class="!bg-red-600 hover:!bg-red-700" wire:click="confirmDelete({{ $item->id }})">Del</flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($this->photoList->hasPages())
            <div>{{ $this->photoList->links() }}</div>
        @endif
    @endif

    {{-- ================================================================ --}}
    {{-- UPLOAD MODAL --}}
    {{-- ================================================================ --}}
    <flux:modal wire:model="showUploadModal" class="w-full max-w-lg">
        <flux:heading size="lg">{{ __('Upload Foto') }}</flux:heading>
        <flux:subheading>{{ __('File diunggah langsung ke Backblaze B2.') }}</flux:subheading>

        <form wire:submit="savePhoto" class="mt-6 space-y-5">
            <flux:field>
                <flux:label>{{ __('Judul') }}</flux:label>
                <flux:input wire:model="title" type="text" placeholder="Nama foto" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Deskripsi') }}</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Opsional" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('File Foto') }}</flux:label>
                <input
                    type="file"
                    wire:model="photo"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-300"
                />
                <flux:error name="photo" />
                <p class="mt-1 text-xs text-zinc-400">JPG, PNG, GIF, WebP · Maks 10 MB</p>

                @if ($photo)
                    <div class="mt-3">
                        <img src="{{ $photo->temporaryUrl() }}" alt="preview" class="h-40 w-full rounded-lg object-cover" />
                    </div>
                @endif

                <div wire:loading wire:target="photo" class="mt-2 text-xs text-blue-500">
                    Memproses file...
                </div>
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showUploadModal', false)">
                    {{ __('Batal') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePhoto,photo">
                    <span wire:loading.remove wire:target="savePhoto">{{ __('Upload') }}</span>
                    <span wire:loading wire:target="savePhoto">{{ __('Mengunggah...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ================================================================ --}}
    {{-- VIEW MODAL --}}
    {{-- ================================================================ --}}
    <flux:modal wire:model="showViewModal" class="w-full max-w-2xl">
        @if ($viewingPhoto)
            <flux:heading size="lg">{{ $viewingPhoto->title }}</flux:heading>
            @if ($viewingPhoto->description)
                <flux:subheading>{{ $viewingPhoto->description }}</flux:subheading>
            @endif

            <div class="mt-4 space-y-4">
                <img
                    src="{{ $viewingPhoto->active_url }}"
                    alt="{{ $viewingPhoto->title }}"
                    class="w-full rounded-xl object-contain"
                    style="max-height: 60vh;"
                />

                <div class="grid grid-cols-2 gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <p class="text-xs text-zinc-400">Dimensi</p>
                        <p class="font-medium">{{ $viewingPhoto->original_width }} × {{ $viewingPhoto->original_height }} px</p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400">Ukuran File</p>
                        <p class="font-medium">{{ $viewingPhoto->file_size_human }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400">Tipe</p>
                        <p class="font-medium">{{ $viewingPhoto->mime_type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400">Diunggah</p>
                        <p class="font-medium">{{ $viewingPhoto->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-zinc-400">Status</p>
                        <p class="font-medium">
                            @if ($viewingPhoto->edited_path)
                                <flux:badge color="green">Sudah diedit · disimpan di B2</flux:badge>
                            @else
                                <flux:badge color="zinc">Original · disimpan di B2</flux:badge>
                            @endif
                        </p>
                    </div>
                </div>

                @if ($viewingPhoto->edited_url)
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-zinc-500">Original:</p>
                        <img src="{{ $viewingPhoto->original_url }}" alt="original" class="w-full rounded-lg object-cover opacity-70" style="max-height: 20vh;" />
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button type="button" variant="primary" wire:click="$set('showViewModal', false)">
                    {{ __('Tutup') }}
                </flux:button>
            </div>
        @endif
    </flux:modal>

    {{-- ================================================================ --}}
    {{-- EDIT / PROCESSING MODAL --}}
    {{-- ================================================================ --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-xl">
        <flux:heading size="lg">{{ __('Edit & Proses Foto') }}</flux:heading>
        <flux:subheading>{{ __('Hasil edit disimpan ke Backblaze B2.') }}</flux:subheading>

        <div class="mt-6 space-y-5">
            {{-- Resize --}}
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="mb-3 text-sm font-semibold text-zinc-700 dark:text-zinc-300">Ukuran (Resize)</p>
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Lebar (px)</flux:label>
                        <flux:input wire:model="editSettings.width" type="number" min="50" max="4096" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Tinggi (px)</flux:label>
                        <flux:input wire:model="editSettings.height" type="number" min="50" max="4096" />
                    </flux:field>
                </div>
            </div>

            {{-- Quality --}}
            <flux:field>
                <flux:label>Kualitas JPEG: {{ $editSettings['quality'] }}%</flux:label>
                <input type="range" wire:model.live="editSettings.quality" min="10" max="100" step="5"
                    class="w-full accent-pink-500" />
            </flux:field>

            {{-- Brightness & Contrast --}}
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="mb-3 text-sm font-semibold text-zinc-700 dark:text-zinc-300">Cahaya & Kontras</p>
                <flux:field>
                    <flux:label>Brightness: {{ $editSettings['brightness'] }}</flux:label>
                    <input type="range" wire:model.live="editSettings.brightness" min="-100" max="100" step="5"
                        class="w-full accent-yellow-400" />
                </flux:field>
                <flux:field class="mt-3">
                    <flux:label>Contrast: {{ $editSettings['contrast'] }}</flux:label>
                    <input type="range" wire:model.live="editSettings.contrast" min="-100" max="100" step="5"
                        class="w-full accent-blue-400" />
                </flux:field>
            </div>

            {{-- Blur --}}
            <flux:field>
                <flux:label>Blur: {{ $editSettings['blur'] }}</flux:label>
                <input type="range" wire:model.live="editSettings.blur" min="0" max="20"
                    class="w-full accent-purple-400" />
            </flux:field>

            {{-- Rotate & Flip --}}
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Rotasi</flux:label>
                    <flux:select wire:model="editSettings.rotate">
                        <option value="0">Tidak diputar</option>
                        <option value="90">90°</option>
                        <option value="180">180°</option>
                        <option value="270">270°</option>
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Flip</flux:label>
                    <flux:select wire:model="editSettings.flip">
                        <option value="none">Tidak</option>
                        <option value="h">Horizontal</option>
                        <option value="v">Vertikal</option>
                    </flux:select>
                </flux:field>
            </div>

            {{-- Grayscale --}}
            <flux:field variant="inline">
                <flux:checkbox wire:model="editSettings.grayscale" />
                <flux:label>Grayscale (hitam putih)</flux:label>
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showEditModal', false)">
                    {{ __('Batal') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="sparkles"
                    wire:click="applyEdit"
                    wire:loading.attr="disabled"
                    wire:target="applyEdit"
                >
                    <span wire:loading.remove wire:target="applyEdit">{{ __('Terapkan & Simpan ke B2') }}</span>
                    <span wire:loading wire:target="applyEdit">{{ __('Memproses...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ================================================================ --}}
    {{-- DELETE MODAL --}}
    {{-- ================================================================ --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <flux:heading size="lg">{{ __('Hapus Foto') }}</flux:heading>
        <flux:subheading>
            {{ __('Foto ":title" akan dihapus dari galeri dan Backblaze B2 secara permanen.', ['title' => $photoToDeleteTitle]) }}
        </flux:subheading>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Batal') }}
            </flux:button>
            <flux:button type="button" variant="danger" wire:click="delete">
                {{ __('Hapus Permanen') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
