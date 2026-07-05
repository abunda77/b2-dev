<x-layouts::app :title="__('Dashboard')">
    <div class="flex w-full flex-1 flex-col gap-6 px-2 py-4 lg:px-4">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Selamat datang, pilih menu di bawah untuk memulai.') }}</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <a href="{{ route('chat.index') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/30">
                    <flux:icon.sparkles variant="solid" class="size-6 text-purple-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Chat AI') }}</flux:heading>
            </a>

            <a href="{{ route('warga.index') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                    <flux:icon.users variant="solid" class="size-6 text-emerald-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Warga') }}</flux:heading>
            </a>

            <a href="{{ route('notes.index') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-cyan-50 dark:bg-cyan-900/30">
                    <flux:icon.document-text variant="solid" class="size-6 text-cyan-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Notes') }}</flux:heading>
            </a>

            <a href="{{ route('whatsapp.send-message') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30">
                    <flux:icon.chat-bubble-left variant="solid" class="size-6 text-green-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('WhatsApp') }}</flux:heading>
            </a>

            <a href="{{ route('qr-code.generate') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30">
                    <flux:icon.qr-code variant="solid" class="size-6 text-amber-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Generate QR Code') }}</flux:heading>
            </a>

            <a href="{{ route('email.send-message') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-900/30">
                    <flux:icon.envelope variant="solid" class="size-6 text-rose-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Email') }}</flux:heading>
            </a>

            <a href="{{ route('faktur.generate') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30">
                    <flux:icon.document-text variant="solid" class="size-6 text-amber-600" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Cetak Faktur') }}</flux:heading>
            </a>

            <a href="{{ route('docs.index') }}" wire:navigate
                class="group flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex size-12 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                    <flux:icon.book-open variant="solid" class="size-6 text-indigo-500" />
                </div>
                <flux:heading size="sm" class="text-center">{{ __('Docs') }}</flux:heading>
            </a>
        </div>
    </div>
</x-layouts::app>
