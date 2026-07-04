<?php

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use App\Services\AiChat\ProviderRegistry;
use Flux\Flux;
use Illuminate\Support\Collection;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Chat AI')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public string $selectedProvider = '';

    public string $selectedModel = '';

    #[Validate(['required', 'string', 'max:5000'])]
    public string $message = '';

    #[Validate(['nullable', 'string', 'max:5000'])]
    public ?string $systemPrompt = '';

    #[Validate(['nullable', 'array', 'max:5'])]
    public array $attachments = [];

    public bool $isGenerating = false;

    public ?string $activeConversationId = null;

    public array $chatMessages = [];

    private ProviderRegistry $registry;

    public function boot(ProviderRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function mount(): void
    {
        $this->selectedProvider = $this->registry->defaultProvider();
        $this->selectedModel = $this->registry->defaultModelFor($this->selectedProvider);

        $lastConversationId = $this->user()->conversations()->latest('updated_at')->value('id');

        if ($lastConversationId) {
            $this->selectConversation($lastConversationId);
        }
    }

    public function validationAttributes(): array
    {
        return [
            'message' => 'Pesan',
            'systemPrompt' => 'Role system',
            'attachments' => 'Lampiran',
        ];
    }

    #[Computed]
    public function availableProviders(): Collection
    {
        return $this->registry->availableProviders();
    }

    #[Computed]
    public function availableModels(): array
    {
        return $this->registry->modelsFor($this->selectedProvider);
    }

    public function selectedModelSupportsImages(): bool
    {
        return $this->registry->supportsImages($this->selectedProvider, $this->selectedModel);
    }

    public function hasImageAttachments(): bool
    {
        return collect($this->attachments)->contains(fn ($file) => $this->isImageAttachment($file));
    }

    #[Computed]
    public function conversations(): Collection
    {
        return $this->user()->conversations()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at?->diffForHumans(),
            ]);
    }

    public function updatedSelectedProvider(): void
    {
        $this->selectedModel = $this->registry->defaultModelFor($this->selectedProvider);
    }

    public function newConversation(): void
    {
        $this->activeConversationId = null;
        $this->chatMessages = [];
        $this->message = '';
        $this->systemPrompt = '';
        $this->resetAttachments();
    }

    public function selectConversation(string $conversationId): void
    {
        $this->activeConversationId = $conversationId;

        $this->chatMessages = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('id')
            ->get()
            ->map(fn (ConversationMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->format('H:i'),
                'has_attachments' => filled($m->attachments) && json_decode((string) $m->attachments, true) !== [],
            ])
            ->values()
            ->all();
    }

    public function send(): void
    {
        $this->validate();

        if ($this->isGenerating) {
            return;
        }

        if (! $this->registry->isAvailable($this->selectedProvider)) {
            Flux::toast(variant: 'error', text: 'Provider tidak tersedia atau API key belum dikonfigurasi.');

            return;
        }

        $this->isGenerating = true;

        $userText = $this->message;
        $attachmentNames = collect($this->attachments)
            ->map(fn ($file) => $file->getClientOriginalName())
            ->all();

        $this->chatMessages[] = [
            'id' => 'temp-user-' . time(),
            'role' => 'user',
            'content' => $userText,
            'created_at' => now()->format('H:i'),
            'has_attachments' => count($attachmentNames) > 0,
            'attachment_names' => $attachmentNames,
        ];

        $aiAttachments = $this->buildAiAttachments();

        $this->message = '';
        $this->resetAttachments();

        try {
            $agent = (new ChatAgent)->withSystemPrompt($this->systemPrompt);

            if ($this->activeConversationId) {
                $agent->continue($this->activeConversationId, $this->user());
            } else {
                $agent->forUser($this->user());
            }

            $response = $agent->prompt(
                $userText,
                $aiAttachments,
                $this->selectedProvider,
                $this->selectedModel,
            );

            $this->activeConversationId = $response->conversationId;

            $this->chatMessages[] = [
                'id' => 'temp-assistant-' . time(),
                'role' => 'assistant',
                'content' => (string) $response,
                'created_at' => now()->format('H:i'),
                'has_attachments' => false,
            ];

            unset($this->conversations);
        } catch (\Throwable $e) {
            $this->chatMessages[] = [
                'id' => 'temp-error-' . time(),
                'role' => 'error',
                'content' => 'Gagal mendapatkan respons: ' . $e->getMessage(),
                'created_at' => now()->format('H:i'),
                'has_attachments' => false,
            ];

            Flux::toast(variant: 'error', text: 'Gagal mengirim pesan ke AI.');
        }

        $this->isGenerating = false;
    }

    public function deleteConversation(string $conversationId): void
    {
        $conversation = Conversation::find($conversationId);

        if ($conversation && $conversation->user_id === $this->user()->id) {
            $conversation->messages()->delete();
            $conversation->delete();

            if ($this->activeConversationId === $conversationId) {
                $this->newConversation();
            }

            unset($this->conversations);

            Flux::toast(variant: 'success', text: 'Percakapan dihapus.');
        }
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    private function buildAiAttachments(): array
    {
        $supportsImages = $this->registry->supportsImages($this->selectedProvider, $this->selectedModel);

        return collect($this->attachments)
            ->map(function ($file) use ($supportsImages) {
                $isImage = $this->isImageAttachment($file);

                if ($isImage && ! $supportsImages) {
                    return null;
                }

                return $isImage
                    ? Image::fromUpload($file)
                    : Document::fromUpload($file);
            })
            ->filter()
            ->values()
            ->all();
    }

    public function isImageAttachment($file): bool
    {
        return str_starts_with((string) $file->getClientMimeType(), 'image/')
            || in_array(strtolower((string) $file->getClientOriginalExtension()), config('ai-chat.attachments.allowed_image_mimes', []), true);
    }

    private function resetAttachments(): void
    {
        $this->attachments = [];
        $this->resetValidation('attachments');
    }

    private function user(): User
    {
        return auth()->user();
    }
}; ?>

<div class="flex h-full gap-0">
    {{-- Conversation Sidebar --}}
    <div class="hidden md:flex w-64 flex-col border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm" weight="semi">{{ __('Percakapan') }}</flux:heading>
            <flux:button size="sm" icon="plus" variant="ghost" wire:click="newConversation" data-test="btn-new-conversation" />
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            @forelse ($this->conversations as $conv)
                <div wire:key="conv-{{ $conv['id'] }}" class="group flex items-center gap-1">
                    <button
                        wire:click="selectConversation('{{ $conv['id'] }}')"
                        class="flex-1 rounded-lg px-3 py-2 text-start text-sm transition-colors {{ $activeConversationId === $conv['id'] ? 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-300' : 'hover:bg-zinc-100 text-zinc-700 dark:hover:bg-zinc-800 dark:text-zinc-400' }}"
                        data-test="conv-item"
                    >
                        <div class="truncate font-medium">{{ Str::limit($conv['title'], 24) }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-500">{{ $conv['updated_at'] }}</div>
                    </button>
                    <flux:button
                        size="sm"
                        icon="trash"
                        variant="ghost"
                        wire:click="deleteConversation('{{ $conv['id'] }}')"
                        class="invisible group-hover:visible text-zinc-400 hover:text-red-500"
                        data-test="btn-delete-conv"
                    />
                </div>
            @empty
                <div class="py-8 text-center text-sm text-zinc-400">
                    <flux:icon name="chat-bubble-left" class="mb-2 size-6 opacity-40" />
                    {{ __('Belum ada percakapan') }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- Chat Main Area --}}
    <div class="flex flex-1 flex-col min-w-0">
        {{-- Header with provider/model selectors --}}
        <div class="flex items-center gap-3 p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="lg" weight="semi">{{ __('Chat AI') }}</flux:heading>

            <flux:spacer />

            <div class="flex items-center gap-2">
                <flux:select
                    wire:model.live="selectedProvider"
                    size="sm"
                    placeholder="Provider"
                    data-test="select-provider"
                    class="w-32"
                >
                    @foreach ($this->availableProviders as $provider)
                        <option value="{{ $provider['name'] }}">{{ $provider['label'] }}</option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model.live="selectedModel"
                    size="sm"
                    placeholder="Model"
                    data-test="select-model"
                    class="w-40"
                >
                    @foreach ($this->availableModels as $model)
                        <option value="{{ $model }}">{{ $model }}</option>
                    @endforeach
                </flux:select>
            </div>

            <flux:button
                size="sm"
                icon="chat-bubble-left"
                variant="ghost"
                wire:click="newConversation"
                class="hidden md:hidden"
            >
                {{ __('Baru') }}
            </flux:button>
        </div>

        {{-- Messages Area --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-messages" data-test="chat-messages">
            @forelse ($chatMessages as $msg)
                @if ($msg['role'] === 'error')
                    <div class="mx-auto max-w-lg rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                        {{ $msg['content'] }}
                    </div>
                @elseif ($msg['role'] === 'user')
                    <div class="flex justify-end" wire:key="msg-{{ $msg['id'] }}">
                        <div class="max-w-[80%] rounded-2xl bg-blue-600 px-4 py-2.5 text-white dark:bg-blue-700">
                            <div class="whitespace-pre-wrap break-words text-sm">{{ $msg['content'] }}</div>
                            @if ($msg['has_attachments'])
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($msg['attachment_names'] ?? [] as $fileName)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-blue-500/40 px-2 py-0.5 text-xs">
                                            <flux:icon name="paper-clip" class="size-3" />
                                            {{ Str::limit($fileName, 20) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="mt-1 text-right text-xs text-blue-200">{{ $msg['created_at'] }}</div>
                        </div>
                    </div>
                @elseif ($msg['role'] === 'assistant')
                    <div class="flex justify-start" wire:key="msg-{{ $msg['id'] }}">
                        <div class="max-w-[80%] rounded-2xl bg-zinc-100 px-4 py-2.5 dark:bg-zinc-800">
                            <div class="prose prose-sm prose-zinc max-w-none dark:prose-invert whitespace-pre-wrap break-words text-sm">{{ $msg['content'] }}</div>
                            @if ($msg['has_attachments'])
                                <div class="mt-1.5 text-xs text-zinc-500">
                                    <flux:icon name="paper-clip" class="size-3 inline" />
                                    {{ __('Lampiran disertakan') }}
                                </div>
                            @endif
                            <div class="mt-1 text-xs text-zinc-400">{{ $msg['created_at'] }}</div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex flex-col items-center justify-center py-16 text-center text-zinc-400">
                    <flux:icon name="sparkles" class="size-10 opacity-40" />
                    <flux:heading size="lg" class="mt-4 text-zinc-400">{{ __('Mulai percakapan baru') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-400">{{ __('Pilih provider dan model, lalu ketik pesan Anda.') }}</flux:text>
                </div>
            @endforelse
        </div>

        {{-- Input Area --}}
        <div class="border-t border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            {{-- Attachments preview --}}
            @if (count($attachments) > 0)
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach ($attachments as $index => $file)
                        <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($this->isImageAttachment($file))
                                <img src="{{ $file->temporaryUrl() }}" alt="{{ $file->getClientOriginalName() }}" class="size-8 rounded object-cover" />
                            @else
                                <flux:icon name="document" class="size-4 text-zinc-400" />
                            @endif
                            <span class="max-w-[120px] truncate">{{ $file->getClientOriginalName() }}</span>
                            <flux:button size="xs" icon="x-mark" variant="ghost" wire:click="removeAttachment({{ $index }})" class="text-zinc-400 hover:text-red-500" />
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Image unsupported warning --}}
            @if ($this->hasImageAttachments() && !$this->selectedModelSupportsImages())
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                    <div class="flex items-start gap-2">
                        <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                        <span>{{ __('Model') }} <strong>{{ $selectedModel }}</strong> {{ __('tidak mendukung gambar. Gambar tidak akan dikirim.') }}</span>
                    </div>
                </div>
            @endif

            <form wire:submit="send" class="space-y-3">
                <flux:field>
                    <flux:label>{{ __('Role system (opsional)') }}</flux:label>
                    <flux:textarea
                        wire:model="systemPrompt"
                        rows="2"
                        placeholder="{{ __('Tambahkan instruksi sistem untuk chat ini...') }}"
                        class="min-h-[44px] resize-none"
                        wire:loading.attr="disabled"
                        wire:target="send"
                        data-test="input-system-prompt"
                    />
                    <flux:error name="systemPrompt" />
                </flux:field>

                <div class="flex items-end gap-3">
                    <flux:field class="flex-1">
                        <flux:textarea
                            wire:model="message"
                            rows="2"
                            placeholder="{{ __('Ketik pesan Anda...') }}"
                            class="min-h-[44px] resize-none"
                            wire:loading.attr="disabled"
                            wire:target="send"
                            data-test="input-message"
                        />
                        <flux:error name="message" />
                    </flux:field>

                    <div class="flex items-center gap-1 pb-5">
                        <flux:input
                            type="file"
                            wire:model="attachments"
                            multiple
                            accept="image/jpg,image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,text/markdown,text/csv,application/json,application/xml,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="hidden"
                            data-test="input-attachments"
                            id="chat-file-input"
                        />
                        <flux:button
                            size="sm"
                            icon="paper-clip"
                            variant="ghost"
                            onclick="document.getElementById('chat-file-input').click()"
                            data-test="btn-attach"
                        />
                        <flux:button
                            size="sm"
                            icon="arrow-up"
                            variant="primary"
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="send"
                            data-test="btn-send"
                        >
                            <span wire:loading.remove wire:target="send">{{ __('Kirim') }}</span>
                            <span wire:loading wire:target="send" class="flex items-center gap-1">
                                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                {{ __('Memproses...') }}
                            </span>
                        </flux:button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
