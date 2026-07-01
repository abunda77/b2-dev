<?php

use App\Services\QrCodeTemporaryFileService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Generate QR Code')] #[Layout('layouts.app')] class extends Component {
    #[Validate(['required', 'string', 'max:5000'])]
    public string $content = '';

    public ?string $pngFilename = null;

    public ?string $jpgFilename = null;

    public ?string $previewDataUri = null;

    public ?string $generateError = null;

    public function validationAttributes(): array
    {
        return [
            'content' => 'Teks QR Code',
        ];
    }

    public function generate(QrCodeTemporaryFileService $temporaryFileService): void
    {
        $this->validate();
        $this->generateError = null;

        $this->clearTemporaryFiles($temporaryFileService, false);

        try {
            $result = $temporaryFileService->generate($this->content);

            $this->pngFilename = $result['png_filename'];
            $this->jpgFilename = $result['jpg_filename'];
            $this->previewDataUri = $result['preview_data_uri'];

            Flux::toast(variant: 'success', text: 'QR code berhasil dibuat. File PNG dan JPG siap diunduh.');
        } catch (\Throwable $throwable) {
            $this->generateError = $throwable->getMessage();
            Flux::toast(variant: 'error', text: 'Gagal membuat QR code. '.$this->generateError);
        }
    }

    public function clearTemporaryFiles(QrCodeTemporaryFileService $temporaryFileService, bool $showToast = true): void
    {
        $temporaryFileService->deleteMany([
            $this->pngFilename,
            $this->jpgFilename,
        ]);

        $hadFiles = $this->pngFilename !== null || $this->jpgFilename !== null;

        $this->pngFilename = null;
        $this->jpgFilename = null;
        $this->previewDataUri = null;
        $this->generateError = null;

        if ($showToast && $hadFiles) {
            Flux::toast(variant: 'success', text: 'File QR code temporary dihapus.');
        }
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Generate QR Code') }}</flux:heading>
        <flux:subheading>{{ __('Buat QR code dari input teks dan unduh hasilnya dalam format PNG atau JPG.') }}</flux:subheading>
    </div>

    @if ($generateError)
        <div class="max-w-2xl rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            <div class="font-medium">{{ __('Gagal membuat QR code') }}</div>
            <div class="mt-1 break-words">{{ $generateError }}</div>
        </div>
    @endif

    <form wire:submit="generate" class="max-w-2xl space-y-5">
        <flux:field>
            <flux:label>{{ __('Teks QR Code') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
            <flux:textarea wire:model="content" rows="6" placeholder="Tulis teks, URL, atau data lain untuk dibuat jadi QR code..." data-test="input-qr-content" />
            <flux:description>{{ __('Maksimal 5000 karakter. File hasil disimpan sementara di storage local dan dapat dibersihkan otomatis atau manual.') }}</flux:description>
            <flux:error name="content" />
        </flux:field>

        <div class="flex justify-end gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            @if ($pngFilename || $jpgFilename)
                <flux:button type="button" variant="ghost" wire:click="clearTemporaryFiles" data-test="btn-hapus-temp-qr">
                    {{ __('Hapus Temporary') }}
                </flux:button>
            @endif

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="generate" data-test="btn-generate-qr">
                <span wire:loading.remove wire:target="generate">{{ __('Generate QR Code') }}</span>
                <span wire:loading wire:target="generate">{{ __('Memproses...') }}</span>
            </flux:button>
        </div>
    </form>

    @if ($previewDataUri && $pngFilename && $jpgFilename)
        <div class="max-w-4xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
            <div class="grid gap-6 lg:grid-cols-[280px_1fr] lg:items-start">
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <img src="{{ $previewDataUri }}" alt="QR Code Preview" class="mx-auto h-auto w-full max-w-[240px] rounded-xl bg-white p-3" data-test="qr-preview" />
                </div>

                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Hasil QR Code') }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Preview memakai PNG. Unduhan PNG dan JPG tersedia dari file temporary storage.') }}
                        </flux:text>
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="grid grid-cols-[90px_1fr] gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">PNG</dt>
                            <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $pngFilename }}</dd>
                        </div>
                        <div class="grid grid-cols-[90px_1fr] gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">JPG</dt>
                            <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $jpgFilename }}</dd>
                        </div>
                    </dl>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('qr-code.download', ['filename' => $pngFilename]) }}" class="inline-flex" data-test="download-qr-png">
                            <flux:button variant="primary" icon="arrow-down-tray">{{ __('Download PNG') }}</flux:button>
                        </a>
                        <a href="{{ route('qr-code.download', ['filename' => $jpgFilename]) }}" class="inline-flex" data-test="download-qr-jpg">
                            <flux:button variant="filled" icon="arrow-down-tray">{{ __('Download JPG') }}</flux:button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
