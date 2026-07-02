<?php

use Flux\Flux;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Kirim Email')] #[Layout('layouts.app')] class extends Component {
    #[Validate(['required', 'email:rfc', 'max:255'])]
    public string $to = '';

    #[Validate(['required', 'string', 'max:255'])]
    public string $subject = '';

    #[Validate(['required', 'string', 'max:5000'])]
    public string $message = '';

    public bool $isSending = false;

    public ?string $sendError = null;

    /**
     * @var array{mailer:string,scheme:mixed,host:string,port:mixed,username:mixed,password:mixed,ehlo_domain:mixed,from_address:string,from_name:string}
     */
    public array $mailConfig = [];

    public function mount(): void
    {
        $this->mailConfig = [
            'mailer' => (string) config('mail.default'),
            'scheme' => config('mail.mailers.smtp.scheme'),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'ehlo_domain' => config('mail.mailers.smtp.local_domain'),
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'to' => 'Email Tujuan',
            'subject' => 'Subjek',
            'message' => 'Pesan',
        ];
    }

    public function send(): void
    {
        $this->validate();
        $this->sendError = null;

        if (! $this->isMailConfigurationComplete()) {
            $this->sendError = 'Konfigurasi SMTP belum lengkap. Periksa file .env.';
            Flux::toast(variant: 'error', text: $this->sendError);

            return;
        }

        $this->isSending = true;

        try {
            Mail::mailer((string) config('mail.default'))->raw($this->message, function (Message $message): void {
                $message->to($this->to)
                    ->subject($this->subject);
            });

            Flux::toast(variant: 'success', text: 'Email berhasil dikirim ke ' . $this->to);
            $this->reset(['to', 'subject', 'message', 'sendError']);
        } catch (\Throwable $throwable) {
            Log::error('SMTP email send failed', [
                'to' => $this->to,
                'subject' => $this->subject,
                'error' => $throwable->getMessage(),
            ]);

            $this->sendError = $throwable->getMessage();
            Flux::toast(variant: 'error', text: 'Gagal mengirim email. ' . $this->sendError);
        } finally {
            $this->isSending = false;
        }
    }

    private function isMailConfigurationComplete(): bool
    {
        return $this->mailConfig['mailer'] === 'smtp'
            && $this->mailConfig['host'] !== ''
            && $this->mailConfig['port'] !== null
            && $this->mailConfig['username'] !== null
            && $this->mailConfig['username'] !== ''
            && $this->mailConfig['password'] !== null
            && $this->mailConfig['password'] !== ''
            && $this->mailConfig['from_address'] !== '';
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Kirim Email') }}</flux:heading>
        <flux:subheading>{{ __('Kirim email SMTP Brevo langsung dari dashboard.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
        <div class="space-y-5">
            @if ($sendError)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    <div class="font-medium">{{ __('Gagal mengirim email') }}</div>
                    <div class="mt-1 break-words">{{ $sendError }}</div>
                </div>
            @endif

            <form wire:submit="send" class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('Email Tujuan') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="to" type="email" placeholder="penerima@contohdomain.com" data-test="input-email-to" />
                    <flux:error name="to" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Subjek') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:input wire:model="subject" type="text" placeholder="Subjek email" data-test="input-email-subject" />
                    <flux:error name="subject" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Pesan') }} <flux:badge size="sm" color="red">Wajib</flux:badge></flux:label>
                    <flux:textarea wire:model="message" rows="6" placeholder="Tulis isi email..." data-test="input-email-message" />
                    <flux:error name="message" />
                </flux:field>

                <div class="flex justify-end gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" :disabled="$isSending" wire:loading.attr="disabled" wire:target="send" data-test="btn-kirim-email">
                        <span wire:loading.remove wire:target="send">{{ __('Kirim Email') }}</span>
                        <span wire:loading wire:target="send">{{ __('Mengirim...') }}</span>
                    </flux:button>
                </div>
            </form>
        </div>

        <aside>
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Debug Konfigurasi SMTP') }}</div>
                        <div class="text-zinc-500 dark:text-zinc-400">
                            {{ __('Data ini diambil dari file .env melalui config/mail.php.') }}
                        </div>
                    </div>
                    <flux:badge size="sm" :color="$this->isMailConfigurationComplete() ? 'green' : 'red'">
                        {{ $this->isMailConfigurationComplete() ? __('Lengkap') : __('Belum Lengkap') }}
                    </flux:badge>
                </div>

                <dl class="space-y-2">
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">MAILER</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['mailer'] !== '' ? $mailConfig['mailer'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">HOST</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['host'] !== '' ? $mailConfig['host'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">PORT</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['port'] !== null && $mailConfig['port'] !== '' ? $mailConfig['port'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">USERNAME</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['username'] !== null && $mailConfig['username'] !== '' ? $mailConfig['username'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">PASSWORD</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['password'] !== null && $mailConfig['password'] !== '' ? '••••••••' : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">SCHEME</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['scheme'] !== null && $mailConfig['scheme'] !== '' ? $mailConfig['scheme'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">EHLO DOMAIN</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['ehlo_domain'] !== null && $mailConfig['ehlo_domain'] !== '' ? $mailConfig['ehlo_domain'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">FROM ADDRESS</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['from_address'] !== '' ? $mailConfig['from_address'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3 border-t border-zinc-200 pt-2 dark:border-zinc-800">
                        <dt class="text-zinc-500 dark:text-zinc-400">FROM NAME</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">{{ $mailConfig['from_name'] !== '' ? $mailConfig['from_name'] : '-' }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</div>
