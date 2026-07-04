<?php

use App\Models\Note;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notes')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public bool $showViewModal = false;

    public ?int $editingId = null;

    #[Validate(['required', 'string', 'max:255'])]
    public string $title = '';

    #[Validate(['required', 'string'])]
    public string $notes = '';

    #[Validate(['required', 'date'])]
    public string $noteDate = '';

    public ?int $noteToDeleteId = null;

    public ?string $noteToDeleteTitle = null;

    public ?string $viewTitle = null;

    public ?string $viewNotes = null;

    public ?string $viewDate = null;

    #[Computed]
    public function notesList(): LengthAwarePaginator
    {
        return Note::query()
            ->whereBelongsTo($this->user())
            ->when($this->search, function ($query): void {
                $query->where(function ($builder): void {
                    $builder
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('notes', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function mount(): void
    {
        $this->noteDate = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $note = Note::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->editingId = $note->id;
        $this->title = $note->title;
        $this->notes = $note->notes;
        $this->noteDate = $note->note_date?->toDateString() ?? now()->toDateString();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingId) {
            $note = Note::query()
                ->whereBelongsTo($this->user())
                ->findOrFail($this->editingId);

            $note->update([
                'title' => $validated['title'],
                'notes' => $validated['notes'],
                'note_date' => $validated['noteDate'],
            ]);

            Flux::toast(variant: 'success', text: 'Catatan berhasil diperbarui.');
        } else {
            $this->user()->notes()->create([
                'title' => $validated['title'],
                'notes' => $validated['notes'],
                'note_date' => $validated['noteDate'],
            ]);

            Flux::toast(variant: 'success', text: 'Catatan berhasil ditambahkan.');
        }

        $this->showFormModal = false;
        $this->resetForm();
        $this->resetPage();
        unset($this->notesList);
    }

    public function show(int $id): void
    {
        $note = Note::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->viewTitle = $note->title;
        $this->viewNotes = $note->notes;
        $this->viewDate = $note->note_date?->format('d/m/Y');
        $this->showViewModal = true;
    }

    public function confirmDelete(int $id): void
    {
        $note = Note::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($id);

        $this->noteToDeleteId = $note->id;
        $this->noteToDeleteTitle = $note->title;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->noteToDeleteId) {
            return;
        }

        $note = Note::query()
            ->whereBelongsTo($this->user())
            ->findOrFail($this->noteToDeleteId);

        $note->delete();

        $this->showDeleteModal = false;
        $this->noteToDeleteId = null;
        $this->noteToDeleteTitle = null;
        $this->resetPage();
        unset($this->notesList);

        Flux::toast(variant: 'success', text: 'Catatan berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->notes = '';
        $this->noteDate = now()->toDateString();
        $this->resetValidation();
    }

    private function user(): User
    {
        return auth()->user();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Notes') }}</flux:heading>
            <flux:subheading>{{ __('Kelola catatan harian dan jadwal penting.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create" data-test="btn-tambah-note">
            {{ __('Tambah Note') }}
        </flux:button>
    </div>

    <div class="max-w-sm">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Cari judul atau isi catatan...') }}"
            clearable
        />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Notes') }}</flux:table.column>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Aksi') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->notesList as $note)
                <flux:table.row wire:key="note-{{ $note->id }}">
                    <flux:table.cell class="font-medium">{{ $note->title }}</flux:table.cell>
                    <flux:table.cell class="max-w-xl">
                        <div class="flex items-start gap-2">
                            <flux:tooltip :content="$note->notes">
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ \Illuminate\Support\Str::limit($note->notes, 50) }}
                                </span>
                            </flux:tooltip>
                            <button
                                type="button"
                                x-data="{ copied: false, async copy() { try { await navigator.clipboard.writeText(@js($note->notes)); this.copied = true; setTimeout(() => this.copied = false, 1500); } catch (error) { console.warn('Could not copy to clipboard', error); } } }"
                                @click="copy()"
                                class="mt-0.5 inline-flex shrink-0 items-center text-zinc-400 transition hover:text-zinc-600 dark:hover:text-zinc-200"
                                data-test="btn-copy-note-{{ $note->id }}"
                                title="{{ __('Copy notes') }}"
                            >
                                <flux:icon.clipboard-document class="size-4" x-show="!copied" />
                                <flux:icon.check class="size-4 text-emerald-500" x-show="copied" x-cloak />
                            </button>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $note->note_date?->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" icon="eye" variant="ghost" wire:click="show({{ $note->id }})" data-test="btn-view-note-{{ $note->id }}">
                                {{ __('View') }}
                            </flux:button>
                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="edit({{ $note->id }})" data-test="btn-edit-note-{{ $note->id }}">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" icon="trash" variant="ghost" class="text-red-500 hover:text-red-600 dark:text-red-400" wire:click="confirmDelete({{ $note->id }})" data-test="btn-hapus-note-{{ $note->id }}">
                                {{ __('Hapus') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400">
                            <flux:icon name="document-text" class="size-10 opacity-40" />
                            <p class="text-sm">
                                {{ $search ? __('Tidak ada catatan yang cocok dengan pencarian.') : __('Belum ada catatan.') }}
                            </p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->notesList->hasPages())
        <div>
            {{ $this->notesList->links() }}
        </div>
    @endif

    <flux:modal wire:model="showFormModal" class="w-full max-w-lg">
        <flux:heading size="lg">{{ $editingId ? __('Edit Note') : __('Tambah Note') }}</flux:heading>
        <flux:subheading>{{ __('Simpan judul, isi catatan, dan tanggal.') }}</flux:subheading>

        <form wire:submit="save" class="mt-6 space-y-5">
            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="title" type="text" placeholder="Judul catatan" data-test="input-note-title" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="5" placeholder="Isi catatan" data-test="input-note-notes" />
                <flux:error name="notes" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Date') }}</flux:label>
                <flux:input wire:model="noteDate" type="date" data-test="input-note-date" />
                <flux:error name="noteDate" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">
                    {{ __('Batal') }}
                </flux:button>
                <flux:button type="submit" variant="primary" data-test="btn-simpan-note">
                    {{ $editingId ? __('Perbarui') : __('Simpan') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showViewModal" class="w-full max-w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $viewTitle }}</flux:heading>
                <flux:subheading>{{ $viewDate }}</flux:subheading>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ $viewNotes }}</p>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button
                    type="button"
                    variant="ghost"
                    x-data="{ copied: false, async copy() { try { await navigator.clipboard.writeText(@js($viewNotes)); this.copied = true; setTimeout(() => this.copied = false, 1500); } catch (error) { console.warn('Could not copy to clipboard', error); } } }"
                    @click="copy()"
                    data-test="btn-copy-view-note"
                >
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </flux:button>
                <flux:button type="button" variant="primary" wire:click="$set('showViewModal', false)">
                    {{ __('Tutup') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <flux:heading size="lg">{{ __('Hapus Note') }}</flux:heading>
        <flux:subheading>
            {{ __('Catatan ":title" akan dihapus permanen.', ['title' => $noteToDeleteTitle]) }}
        </flux:subheading>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Batal') }}
            </flux:button>
            <flux:button type="button" variant="danger" wire:click="delete" data-test="btn-konfirmasi-hapus-note">
                {{ __('Hapus') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
