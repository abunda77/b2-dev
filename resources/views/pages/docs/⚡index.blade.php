<?php

use App\Models\Document;
use App\Models\User;
use App\Services\MarkdownRendererService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Docs')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public ?int $selectedDocumentId = null;

    public string $search = '';

    public bool $showUploadModal = false;

    public bool $showDeleteModal = false;

    public ?int $documentToDeleteId = null;

    public ?string $documentToDeleteTitle = null;

    #[Validate(['required', 'file', 'mimes:md,txt,markdown', 'max:2048'])]
    public $uploadFile = null;

    #[Validate(['nullable', 'string', 'max:255'])]
    public string $uploadTitle = '';

    /**
     * Get all documents for the current user, including project files.
     *
     * @return Collection<int, Document>
     */
    #[Computed]
    public function documentsList(): Collection
    {
        return Document::query()
            ->whereBelongsTo($this->user())
            ->when($this->search, function ($query): void {
                $query->where(function ($builder): void {
                    $builder
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('filename', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Get the currently selected document's rendered HTML.
     */
    #[Computed]
    public function renderedContent(): string
    {
        if (! $this->selectedDocumentId) {
            return '';
        }

        $document = Document::query()
            ->whereBelongsTo($this->user())
            ->find($this->selectedDocumentId);

        if (! $document) {
            return '<p class="text-zinc-400">Dokumen tidak ditemukan.</p>';
        }

        $content = $document->getContent();

        if ($content === null) {
            return '<p class="text-zinc-400">File tidak ditemukan di disk.</p>';
        }

        $renderer = new MarkdownRendererService;

        return $renderer->render($content);
    }

    /**
     * Get the selected document model.
     */
    #[Computed]
    public function selectedDocument(): ?Document
    {
        if (! $this->selectedDocumentId) {
            return null;
        }

        return Document::query()
            ->whereBelongsTo($this->user())
            ->find($this->selectedDocumentId);
    }

    public function mount(): void
    {
        $this->syncProjectFiles();

        // Auto-select first document
        $first = Document::query()
            ->whereBelongsTo($this->user())
            ->orderByDesc('updated_at')
            ->first();

        if ($first) {
            $this->selectedDocumentId = $first->id;
        }
    }

    public function selectDocument(int $id): void
    {
        $this->selectedDocumentId = $id;
        unset($this->renderedContent, $this->selectedDocument);
    }

    public function openUploadModal(): void
    {
        $this->uploadFile = null;
        $this->uploadTitle = '';
        $this->resetValidation();
        $this->showUploadModal = true;
    }

    public function upload(): void
    {
        $this->validate([
            'uploadFile' => ['required', 'file', 'max:2048'],
        ]);

        $originalName = $this->uploadFile->getClientOriginalName();

        // Ensure .md extension
        if (! str_ends_with(strtolower($originalName), '.md') && ! str_ends_with(strtolower($originalName), '.markdown')) {
            $this->addError('uploadFile', 'File harus berformat Markdown (.md atau .markdown).');

            return;
        }

        $filename = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $path = $this->uploadFile->storeAs('documents', $filename, 'local');

        $title = $this->uploadTitle ?: pathinfo($originalName, PATHINFO_FILENAME);

        // Try to extract title from markdown content
        if (! $this->uploadTitle) {
            $content = file_get_contents($this->uploadFile->getRealPath());
            $renderer = new MarkdownRendererService;
            $extractedTitle = $renderer->extractTitle($content);
            if ($extractedTitle) {
                $title = $extractedTitle;
            }
        }

        $document = $this->user()->documents()->create([
            'title' => $title,
            'filename' => $originalName,
            'disk_path' => $path,
            'source' => 'upload',
            'file_size' => $this->uploadFile->getSize(),
        ]);

        $this->showUploadModal = false;
        $this->uploadFile = null;
        $this->uploadTitle = '';
        $this->selectedDocumentId = $document->id;
        unset($this->documentsList, $this->renderedContent, $this->selectedDocument);

        Flux::toast(variant: 'success', text: 'Dokumen berhasil diupload.');
    }

    public function confirmDelete(int $id): void
    {
        $document = Document::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->documentToDeleteId = $document->id;
        $this->documentToDeleteTitle = $document->title;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->documentToDeleteId) {
            return;
        }

        $document = Document::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($this->documentToDeleteId);

        $document->deleteFile();
        $document->delete();

        $this->showDeleteModal = false;
        $this->documentToDeleteId = null;
        $this->documentToDeleteTitle = null;

        // Deselect if we deleted the selected document
        if ($this->selectedDocumentId === $document->id) {
            $this->selectedDocumentId = null;
            unset($this->renderedContent, $this->selectedDocument);
        }

        unset($this->documentsList);

        Flux::toast(variant: 'success', text: 'Dokumen berhasil dihapus.');
    }

    /**
     * Scan project root and docs/ folder for .md files and sync them to the database.
     */
    public function syncProjectFiles(): void
    {
        $user = $this->user();
        $scannedPaths = [];

        // Scan project root for .md files
        $rootMdFiles = glob(base_path('*.md')) ?: [];
        foreach ($rootMdFiles as $fullPath) {
            $filename = basename($fullPath);
            $relativePath = $filename;
            $scannedPaths[] = $relativePath;

            Document::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'disk_path' => $relativePath,
                    'source' => 'project_root',
                ],
                [
                    'title' => pathinfo($filename, PATHINFO_FILENAME),
                    'filename' => $filename,
                    'file_size' => filesize($fullPath),
                ]
            );
        }

        // Scan docs/ folder
        $docsPath = base_path('docs');
        if (is_dir($docsPath)) {
            $docsMdFiles = glob($docsPath.'/*.md') ?: [];
            foreach ($docsMdFiles as $fullPath) {
                $filename = basename($fullPath);
                $relativePath = 'docs/'.$filename;
                $scannedPaths[] = $relativePath;

                Document::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'disk_path' => $relativePath,
                        'source' => 'docs_folder',
                    ],
                    [
                        'title' => pathinfo($filename, PATHINFO_FILENAME),
                        'filename' => $filename,
                        'file_size' => filesize($fullPath),
                    ]
                );
            }
        }

        // Remove stale project files (deleted from disk)
        Document::query()
            ->where('user_id', $user->id)
            ->whereIn('source', ['project_root', 'docs_folder'])
            ->whereNotIn('disk_path', $scannedPaths)
            ->delete();
    }

    public function refreshProjectFiles(): void
    {
        $this->syncProjectFiles();
        unset($this->documentsList);

        Flux::toast(variant: 'success', text: 'File project berhasil disinkronkan.');
    }

    public function downloadDocument(int $id): mixed
    {
        $document = Document::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $content = $document->getContent();

        if ($content === null) {
            Flux::toast(variant: 'danger', text: 'File tidak ditemukan.');

            return null;
        }

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $document->filename, [
            'Content-Type' => 'text/markdown',
        ]);
    }

    private function user(): User
    {
        return auth()->user();
    }
}; ?>

<div class="flex h-[calc(100vh-4rem)] flex-col">
    {{-- Header --}}
    <div class="flex flex-col gap-4 border-b border-zinc-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 sm:px-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Docs') }}</flux:heading>
            <flux:subheading>{{ __('Lihat dan kelola dokumen markdown.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button size="sm" icon="arrow-path" variant="ghost" wire:click="refreshProjectFiles" data-test="btn-sync-docs">
                {{ __('Sync') }}
            </flux:button>
            <flux:button variant="primary" icon="arrow-up-tray" wire:click="openUploadModal" data-test="btn-upload-doc">
                {{ __('Upload') }}
            </flux:button>
        </div>
    </div>

    {{-- Main content: sidebar + preview --}}
    <div class="flex min-h-0 flex-1">
        {{-- Sidebar: file list --}}
        <div class="flex w-72 flex-col border-e border-zinc-200 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-900/50">
            {{-- Search --}}
            <div class="border-b border-zinc-200 p-3 dark:border-zinc-700">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Cari dokumen...') }}"
                    clearable
                    size="sm"
                />
            </div>

            {{-- File list --}}
            <div class="flex-1 overflow-y-auto p-2 space-y-0.5">
                @forelse ($this->documentsList as $doc)
                    <button
                        wire:key="doc-{{ $doc->id }}"
                        wire:click="selectDocument({{ $doc->id }})"
                        class="group flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-start transition-colors
                            {{ $selectedDocumentId === $doc->id
                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        data-test="doc-item-{{ $doc->id }}"
                    >
                        <div class="mt-0.5 shrink-0">
                            @if ($doc->source === 'upload')
                                <flux:icon name="document-arrow-up" class="size-4 text-emerald-500" />
                            @elseif ($doc->source === 'docs_folder')
                                <flux:icon name="folder-open" class="size-4 text-amber-500" />
                            @else
                                <flux:icon name="document-text" class="size-4 text-blue-500" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $doc->title }}</p>
                            <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $doc->filename }}
                                <span class="mx-1">&middot;</span>
                                {{ $doc->formatted_file_size }}
                            </p>
                        </div>

                        {{-- Delete button (only for uploaded files) --}}
                        @if ($doc->source === 'upload')
                            <button
                                wire:click.stop="confirmDelete({{ $doc->id }})"
                                class="mt-0.5 shrink-0 text-zinc-300 opacity-0 transition hover:text-red-500 group-hover:opacity-100 dark:text-zinc-600 dark:hover:text-red-400"
                                title="{{ __('Hapus') }}"
                                data-test="btn-delete-doc-{{ $doc->id }}"
                            >
                                <flux:icon name="trash" class="size-3.5" />
                            </button>
                        @endif
                    </button>
                @empty
                    <div class="flex flex-col items-center gap-2 px-4 py-12 text-zinc-400">
                        <flux:icon name="document-text" class="size-10 opacity-40" />
                        <p class="text-center text-sm">
                            {{ $search ? __('Tidak ada dokumen yang cocok.') : __('Belum ada dokumen.') }}
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- Sidebar footer: file count --}}
            <div class="border-t border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <p class="text-xs text-zinc-400">
                    {{ trans_choice(':count dokumen|:count dokumen', $this->documentsList->count(), ['count' => $this->documentsList->count()]) }}
                </p>
            </div>
        </div>

        {{-- Preview area --}}
        <div class="flex flex-1 flex-col min-w-0 bg-white dark:bg-zinc-800">
            @if ($this->selectedDocument)
                {{-- Document header --}}
                <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-3 dark:border-zinc-700">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $this->selectedDocument->title }}
                        </h2>
                        <p class="text-xs text-zinc-400">
                            {{ $this->selectedDocument->filename }}
                            <span class="mx-1">&middot;</span>
                            {{ $this->selectedDocument->formatted_file_size }}
                            <span class="mx-1">&middot;</span>
                            @if ($this->selectedDocument->source === 'upload')
                                <flux:badge size="sm" variant="pill" color="emerald">Upload</flux:badge>
                            @elseif ($this->selectedDocument->source === 'docs_folder')
                                <flux:badge size="sm" variant="pill" color="amber">docs/</flux:badge>
                            @else
                                <flux:badge size="sm" variant="pill" color="blue">Project</flux:badge>
                            @endif
                        </p>
                    </div>
                    <flux:button size="sm" icon="arrow-down-tray" variant="ghost" wire:click="downloadDocument({{ $this->selectedDocument->id }})" data-test="btn-download-doc">
                        {{ __('Download') }}
                    </flux:button>
                </div>

                {{-- Markdown content --}}
                <div class="flex-1 overflow-y-auto p-6 md:p-8 lg:p-10">
                    <article class="prose prose-zinc dark:prose-invert max-w-none
                        prose-headings:scroll-mt-4
                        prose-h1:text-2xl prose-h1:font-bold prose-h1:border-b prose-h1:border-zinc-200 prose-h1:pb-2 dark:prose-h1:border-zinc-700
                        prose-h2:text-xl prose-h2:font-semibold prose-h2:border-b prose-h2:border-zinc-200 prose-h2:pb-1 dark:prose-h2:border-zinc-700
                        prose-code:before:content-[''] prose-code:after:content-['']
                        prose-code:rounded prose-code:bg-zinc-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-sm prose-code:font-normal dark:prose-code:bg-zinc-900
                        prose-pre:bg-zinc-900 prose-pre:text-zinc-100 dark:prose-pre:bg-zinc-950
                        prose-a:text-blue-600 prose-a:no-underline hover:prose-a:underline dark:prose-a:text-blue-400
                        prose-table:text-sm
                        prose-img:rounded-lg prose-img:shadow-sm
                        prose-blockquote:border-blue-300 dark:prose-blockquote:border-blue-700
                        prose-hr:border-zinc-200 dark:prose-hr:border-zinc-700
                    ">
                        {!! $this->renderedContent !!}
                    </article>
                </div>
            @else
                {{-- Empty state --}}
                <div class="flex flex-1 flex-col items-center justify-center gap-4 text-zinc-400">
                    <flux:icon name="document-text" class="size-16 opacity-30" />
                    <div class="text-center">
                        <p class="text-lg font-medium">{{ __('Pilih dokumen') }}</p>
                        <p class="text-sm">{{ __('Pilih dokumen dari sidebar untuk melihat isinya.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Upload Modal --}}
    <flux:modal wire:model="showUploadModal" class="w-full max-w-lg">
        <flux:heading size="lg">{{ __('Upload Dokumen') }}</flux:heading>
        <flux:subheading>{{ __('Upload file Markdown (.md) dari komputer Anda.') }}</flux:subheading>

        <form wire:submit="upload" class="mt-6 space-y-5">
            <flux:field>
                <flux:label>{{ __('File Markdown') }}</flux:label>
                <flux:input type="file" wire:model="uploadFile" accept=".md,.markdown,.txt" data-test="input-upload-file" />
                <flux:description>{{ __('Maksimal 2MB. Format: .md, .markdown, .txt') }}</flux:description>
                <flux:error name="uploadFile" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Judul (opsional)') }}</flux:label>
                <flux:input wire:model="uploadTitle" type="text" placeholder="{{ __('Otomatis dari nama file/heading') }}" data-test="input-upload-title" />
                <flux:description>{{ __('Jika kosong, judul diambil dari heading pertama atau nama file.') }}</flux:description>
                <flux:error name="uploadTitle" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showUploadModal', false)">
                    {{ __('Batal') }}
                </flux:button>
                <flux:button type="submit" variant="primary" data-test="btn-submit-upload">
                    {{ __('Upload') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <flux:heading size="lg">{{ __('Hapus Dokumen') }}</flux:heading>
        <flux:subheading>
            {{ __('Dokumen ":title" akan dihapus permanen.', ['title' => $documentToDeleteTitle]) }}
        </flux:subheading>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Batal') }}
            </flux:button>
            <flux:button type="button" variant="danger" wire:click="delete" data-test="btn-konfirmasi-hapus-doc">
                {{ __('Hapus') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
