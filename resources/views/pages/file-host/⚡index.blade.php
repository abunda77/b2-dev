<?php

use App\Models\FileHost;
use App\Services\B2UploadService;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('File Host')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    #[Validate]
    public string $nama = '';

    public ?int $fileToDeleteId = null;
    public ?string $fileToDeleteNama = null;

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'nama' => 'Nama',
        ];
    }

    #[Computed]
    public function fileHosts(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return FileHost::query()
            ->when($this->search, fn ($q) => $q->where('nama', 'like', '%'.$this->search.'%')
                ->orWhere('original_name', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(10);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
        $this->dispatch('modal-opened');
    }

    public function edit(int $id): void
    {
        $fileHost = FileHost::findOrFail($id);
        $this->editingId = $fileHost->id;
        $this->nama = $fileHost->nama;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $fileHost = FileHost::findOrFail($this->editingId);
        $fileHost->nama = $this->nama;
        $fileHost->save();

        Flux::toast(variant: 'success', text: "File {$fileHost->nama} berhasil diperbarui.");

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->fileHosts);
    }

    /**
     * Initiate single-part upload. Returns presigned PUT URL and storage key.
     */
    public function initiateSingleUpload(string $originalName, int $fileSize, string $contentType): array
    {
        $service = app(B2UploadService::class);
        $key = $service->generateKey($originalName);
        $url = $service->presignedPutUrl($key, $contentType);

        return [
            'url' => $url,
            'key' => $key,
        ];
    }

    /**
     * Initiate multipart upload. Returns UploadId, key, and presigned URLs for each part.
     */
    public function initiateMultipartUpload(string $originalName, int $fileSize, string $contentType): array
    {
        $service = app(B2UploadService::class);
        $key = $service->generateKey($originalName);

        return $service->initiateMultipartUpload($key, $contentType, $fileSize);
    }

    /**
     * Confirm single-part upload and save record.
     */
    public function confirmUpload(string $key, string $nama, string $originalName, string $contentType, int $fileSize): void
    {
        FileHost::create([
            'nama' => $nama,
            'original_name' => $originalName,
            'mime_type' => $contentType,
            'size' => $fileSize,
            'path' => $key,
            'disk' => 'b2',
        ]);

        Flux::toast(variant: 'success', text: "File {$nama} berhasil diupload.");

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->fileHosts);
    }

    /**
     * Complete multipart upload and save record.
     */
    public function confirmMultipartUpload(string $key, string $uploadId, array $parts, string $nama, string $originalName, string $contentType, int $fileSize): void
    {
        $service = app(B2UploadService::class);

        try {
            $service->completeMultipartUpload($key, $uploadId, $parts);
        } catch (\Exception $e) {
            Log::error('Multipart upload completion failed', [
                'key' => $key,
                'uploadId' => $uploadId,
                'error' => $e->getMessage(),
            ]);
            Flux::toast(variant: 'error', text: 'Gagal menyelesaikan upload. Silakan coba lagi.');

            throw $e;
        }

        FileHost::create([
            'nama' => $nama,
            'original_name' => $originalName,
            'mime_type' => $contentType,
            'size' => $fileSize,
            'path' => $key,
            'disk' => 'b2',
        ]);

        Flux::toast(variant: 'success', text: "File {$nama} berhasil diupload.");

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->fileHosts);
    }

    public function confirmDelete(int $id): void
    {
        $fileHost = FileHost::findOrFail($id);
        $this->fileToDeleteId = $fileHost->id;
        $this->fileToDeleteNama = $fileHost->nama;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->fileToDeleteId) {
            return;
        }

        $fileHost = FileHost::findOrFail($this->fileToDeleteId);
        $nama = $fileHost->nama;
        $path = $fileHost->path;
        $disk = $fileHost->disk;

        $fileHost->delete();

        if ($path && str_starts_with($path, 'file-host/')) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Exception $e) {
                Log::error('Gagal hapus file dari storage', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        $this->showDeleteModal = false;
        $this->fileToDeleteId = null;
        $this->fileToDeleteNama = null;

        Flux::toast(variant: 'success', text: "File {$nama} berhasil dihapus.");
        unset($this->fileHosts);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nama = '';
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('File Host') }}</flux:heading>
            <flux:subheading>{{ __('Upload dan kelola file besar langsung ke Backblaze B2.') }}</flux:subheading>
        </div>
        <flux:button
            variant="primary"
            icon="arrow-up-tray"
            wire:click="create"
            data-test="btn-upload-file"
        >
            {{ __('Upload File') }}
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="max-w-sm">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Cari nama atau file...') }}"
            clearable
        />
    </div>

    {{-- Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>{{ __('Nama File') }}</flux:table.column>
            <flux:table.column>{{ __('Tipe') }}</flux:table.column>
            <flux:table.column sortable>{{ __('Ukuran') }}</flux:table.column>
            <flux:table.column>{{ __('Aksi') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->fileHosts as $fileHost)
                <flux:table.row wire:key="file-{{ $fileHost->id }}">
                    {{-- Nama --}}
                    <flux:table.cell class="font-medium">
                        {{ $fileHost->nama }}
                    </flux:table.cell>

                    {{-- Nama File --}}
                    <flux:table.cell class="max-w-xs">
                        <flux:tooltip :content="$fileHost->original_name">
                            <span class="line-clamp-1 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $fileHost->original_name }}
                            </span>
                        </flux:tooltip>
                    </flux:table.cell>

                    {{-- Tipe --}}
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $fileHost->mime_type }}</flux:badge>
                    </flux:table.cell>

                    {{-- Ukuran --}}
                    <flux:table.cell class="font-mono text-sm">
                        {{ $fileHost->size_for_humans }}
                    </flux:table.cell>

                    {{-- Aksi --}}
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button
                                size="sm"
                                icon="arrow-down-tray"
                                variant="ghost"
                                :href="$fileHost->url"
                                target="_blank"
                                data-test="btn-download-{{ $fileHost->id }}"
                            >
                                {{ __('Download') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                icon="pencil"
                                variant="ghost"
                                wire:click="edit({{ $fileHost->id }})"
                                data-test="btn-edit-{{ $fileHost->id }}"
                            >
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                icon="trash"
                                variant="ghost"
                                class="text-red-500 hover:text-red-600 dark:text-red-400"
                                wire:click="confirmDelete({{ $fileHost->id }})"
                                data-test="btn-hapus-{{ $fileHost->id }}"
                            >
                                {{ __('Hapus') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400">
                            <flux:icon name="document" class="size-10 opacity-40" />
                            <p class="text-sm">
                                {{ $search ? __('Tidak ada file yang cocok dengan pencarian.') : __('Belum ada file yang diupload.') }}
                            </p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($this->fileHosts->hasPages())
        <div class="mt-4">
            {{ $this->fileHosts->links() }}
        </div>
    @endif

    {{-- ============================================= --}}
    {{-- Modal: Upload / Edit                          --}}
    {{-- ============================================= --}}
    <flux:modal wire:model="showFormModal" class="w-full max-w-lg">
        <flux:heading size="lg">
            {{ $editingId ? __('Edit File') : __('Upload File Baru') }}
        </flux:heading>
        <flux:subheading>
            {{ $editingId ? __('Perbarui nama label file.') : __('Pilih file dan beri label untuk diupload ke Backblaze B2.') }}
        </flux:subheading>

        <div class="mt-6 space-y-5">
            {{-- Nama --}}
            <flux:field>
                <flux:label>{{ __('Nama Label') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                <flux:input
                    wire:model="nama"
                    type="text"
                    placeholder="Nama atau deskripsi file"
                    id="file-host-nama"
                    data-test="input-nama"
                />
                <flux:error name="nama" />
            </flux:field>

            @if (!$editingId)
                {{-- File Input (hanya saat create) --}}
                <flux:field>
                    <flux:label>{{ __('Pilih File') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <input
                        type="file"
                        id="file-host-input"
                        class="block w-full text-sm text-zinc-500 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-300 dark:hover:file:bg-zinc-700"
                        data-test="input-file"
                    />
                    <flux:description>{{ __('Mendukung semua tipe file. Tidak ada batasan ukuran.') }}</flux:description>
                </flux:field>

                {{-- File Info Preview --}}
                <div id="file-info" class="hidden rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <flux:icon name="document" class="size-8 text-blue-500" />
                        <div class="min-w-0 flex-1">
                            <p id="file-info-name" class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100"></p>
                            <p id="file-info-size" class="text-xs text-zinc-500 dark:text-zinc-400"></p>
                        </div>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div id="progress-container" class="hidden space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Mengupload...') }}</span>
                        <span id="progress-text" class="font-mono font-medium text-blue-600 dark:text-blue-400">0%</span>
                    </div>
                    <div class="h-2.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                        <div
                            id="progress-bar"
                            class="h-2.5 rounded-full bg-blue-500 transition-all duration-300 ease-out"
                            style="width: 0%"
                        ></div>
                    </div>
                </div>

                {{-- Error Message --}}
                <div id="upload-error" class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400"></div>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <flux:button
                type="button"
                variant="ghost"
                wire:click="$set('showFormModal', false)"
                id="btn-batal"
            >
                {{ __('Batal') }}
            </flux:button>
            @if ($editingId)
                <flux:button
                    type="button"
                    variant="primary"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    data-test="btn-simpan"
                >
                    <span wire:loading.remove wire:target="save">{{ __('Simpan') }}</span>
                    <span wire:loading wire:target="save">{{ __('Menyimpan...') }}</span>
                </flux:button>
            @else
                <flux:button
                    type="button"
                    variant="primary"
                    id="upload-btn"
                    disabled
                    data-test="btn-upload"
                >
                    {{ __('Upload') }}
                </flux:button>
            @endif
        </div>
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
                <flux:heading size="lg">{{ __('Hapus File') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ __('Apakah Anda yakin ingin menghapus') }}
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $fileToDeleteNama }}</span>?
                    {{ __('File di storage B2 juga akan dihapus. Tindakan ini tidak dapat dibatalkan.') }}
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

<script>
(function () {
    let selectedFile = null;
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

    function getEl(id) { return document.getElementById(id); }

    function getWire() {
        const components = Livewire.getByName('pages::file-host.index');
        return components.length > 0 ? components[0] : null;
    }

    function formatSize(bytes) {
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return bytes + ' B';
    }

    function resetUploadUI() {
        selectedFile = null;
        const fileInput = getEl('file-host-input');
        const fileInfo = getEl('file-info');
        const progressContainer = getEl('progress-container');
        const progressBar = getEl('progress-bar');
        const progressText = getEl('progress-text');
        const uploadError = getEl('upload-error');
        const uploadBtn = getEl('upload-btn');

        if (fileInput) fileInput.value = '';
        if (fileInfo) fileInfo.classList.add('hidden');
        if (progressContainer) progressContainer.classList.add('hidden');
        if (progressBar) progressBar.style.width = '0%';
        if (progressText) progressText.textContent = '0%';
        if (uploadError) uploadError.classList.add('hidden');
        if (uploadBtn) uploadBtn.disabled = true;
    }

    function showError(message) {
        const uploadError = getEl('upload-error');
        if (uploadError) {
            uploadError.textContent = message;
            uploadError.classList.remove('hidden');
        }
    }

    function updateProgress(percent) {
        const rounded = Math.round(percent);
        const progressBar = getEl('progress-bar');
        const progressText = getEl('progress-text');
        if (progressBar) {
            progressBar.style.width = rounded + '%';
            progressBar.classList.remove('animate-pulse');
        }
        if (progressText) {
            progressText.textContent = rounded + '%';
        }
    }

    function showIndeterminateProgress() {
        const progressBar = getEl('progress-bar');
        const progressText = getEl('progress-text');
        if (progressBar) {
            progressBar.style.width = '100%';
            progressBar.classList.add('animate-pulse');
        }
        if (progressText) {
            progressText.textContent = '...';
        }
    }

    function uploadChunk(url, chunk, partIndex, totalParts) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');

            const chunkSize = chunk.size;

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const chunkProgress = e.loaded / e.total;
                    const overall = ((partIndex + chunkProgress) / totalParts) * 100;
                    updateProgress(overall);
                } else if (e.loaded > 0) {
                    const chunkProgress = e.loaded / chunkSize;
                    const overall = ((partIndex + chunkProgress) / totalParts) * 100;
                    updateProgress(overall);
                } else {
                    showIndeterminateProgress();
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    const etag = xhr.getResponseHeader('ETag');
                    if (etag) {
                        resolve(etag.replace(/^"|"$/g, ''));
                    } else {
                        reject(new Error('ETag tidak ditemukan di response part ' + (partIndex + 1)));
                    }
                } else {
                    reject(new Error('HTTP ' + xhr.status + ' pada part ' + (partIndex + 1)));
                }
            };

            xhr.onerror = () => reject(new Error('Network error pada part ' + (partIndex + 1)));
            xhr.ontimeout = () => reject(new Error('Timeout pada part ' + (partIndex + 1)));
            xhr.timeout = 300000;
            xhr.send(chunk);
        });
    }

    function uploadSingleFile(url, file) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url);
            xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

            const fileSize = file.size;

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    updateProgress((e.loaded / e.total) * 100);
                } else if (e.loaded > 0) {
                    updateProgress((e.loaded / fileSize) * 100);
                } else {
                    showIndeterminateProgress();
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    resolve();
                } else {
                    reject(new Error('HTTP ' + xhr.status));
                }
            };

            xhr.onerror = () => reject(new Error('Network error'));
            xhr.ontimeout = () => reject(new Error('Timeout'));
            xhr.timeout = 600000;
            xhr.send(file);
        });
    }

    async function handleUpload() {
        const uploadBtn = getEl('upload-btn');
        const progressContainer = getEl('progress-container');
        const uploadError = getEl('upload-error');
        const namaInput = getEl('file-host-nama');

        if (!selectedFile || !uploadBtn) return;

        const nama = (namaInput?.value || '').trim();
        if (!nama) {
            showError('Nama label wajib diisi.');
            namaInput?.focus();
            return;
        }

        uploadBtn.disabled = true;
        if (progressContainer) progressContainer.classList.remove('hidden');
        if (uploadError) uploadError.classList.add('hidden');
        updateProgress(0);

        const totalChunks = Math.ceil(selectedFile.size / CHUNK_SIZE);
        const wire = getWire();

        if (!wire) {
            showError('Komponen Livewire tidak ditemukan.');
            uploadBtn.disabled = false;
            return;
        }

        try {
            if (totalChunks > 1) {
                const initResult = await wire.initiateMultipartUpload(
                    selectedFile.name,
                    selectedFile.size,
                    selectedFile.type || 'application/octet-stream'
                );

                const parts = [];
                for (let i = 0; i < totalChunks; i++) {
                    const start = i * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, selectedFile.size);
                    const chunk = selectedFile.slice(start, end);

                    const etag = await uploadChunk(
                        initResult.presignedUrls[i],
                        chunk,
                        i,
                        totalChunks
                    );
                    parts.push({ PartNumber: i + 1, ETag: etag });
                }

                await wire.confirmMultipartUpload(
                    initResult.key,
                    initResult.uploadId,
                    parts,
                    nama,
                    selectedFile.name,
                    selectedFile.type || 'application/octet-stream',
                    selectedFile.size
                );
            } else {
                const initResult = await wire.initiateSingleUpload(
                    selectedFile.name,
                    selectedFile.size,
                    selectedFile.type || 'application/octet-stream'
                );

                await uploadSingleFile(initResult.url, selectedFile);

                await wire.confirmUpload(
                    initResult.key,
                    nama,
                    selectedFile.name,
                    selectedFile.type || 'application/octet-stream',
                    selectedFile.size
                );
            }
        } catch (err) {
            showError('Upload gagal: ' + (err.message || 'Terjadi kesalahan.'));
            if (uploadBtn) uploadBtn.disabled = false;
        } finally {
            updateProgress(100);
            setTimeout(function () {
                const pc = getEl('progress-container');
                if (pc) pc.classList.add('hidden');
                updateProgress(0);
            }, 500);
        }
    }

    // Event delegation — survives Livewire re-renders
    document.addEventListener('change', function (e) {
        if (e.target.id !== 'file-host-input') return;

        selectedFile = e.target.files[0];
        const uploadError = getEl('upload-error');
        const fileInfo = getEl('file-info');
        const fileInfoName = getEl('file-info-name');
        const fileInfoSize = getEl('file-info-size');
        const uploadBtn = getEl('upload-btn');

        if (uploadError) uploadError.classList.add('hidden');

        if (selectedFile) {
            if (fileInfoName) fileInfoName.textContent = selectedFile.name;
            if (fileInfoSize) fileInfoSize.textContent = formatSize(selectedFile.size);
            if (fileInfo) fileInfo.classList.remove('hidden');
            if (uploadBtn) uploadBtn.disabled = false;
        } else {
            if (fileInfo) fileInfo.classList.add('hidden');
            if (uploadBtn) uploadBtn.disabled = true;
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('#upload-btn')) {
            e.preventDefault();
            handleUpload();
        }
    });

    // Reset UI when create modal opens
    Livewire.on('modal-opened', resetUploadUI);
})();
</script>