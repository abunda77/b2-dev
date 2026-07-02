<?php

use Flux\Flux;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Kirim Pesan WhatsApp')] #[Layout('layouts.app')] class extends Component {
    #[Validate(['required', 'string', 'max:255'])]
    public string $phone = '';

    #[Validate(['required', 'string', 'max:5000'])]
    public string $message = '';

    public bool $isSending = false;

    public ?string $sendError = null;

    /**
     * @var array{auth:string,ip:string,port:string,device_id:string,action:string,duration:mixed,base_url:string}
     */
    public array $gatewayConfig = [];

    public function mount(): void
    {
        $auth = (string) config('whatsapp.auth');
        $ip = (string) config('whatsapp.ip');
        $port = (string) config('whatsapp.port');

        $this->gatewayConfig = [
            'auth' => $auth,
            'ip' => $ip,
            'port' => $port,
            'device_id' => (string) config('whatsapp.device_id'),
            'action' => (string) config('whatsapp.action'),
            'duration' => config('whatsapp.duration'),
            'base_url' => $auth !== '' && $ip !== '' && $port !== ''
                ? "http://{$auth}@{$ip}:{$port}"
                : '',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'phone' => 'Nomor Tujuan',
            'message' => 'Pesan',
        ];
    }

    public function send(): void
    {
        $this->validate();
        $this->sendError = null;

        $auth = config('whatsapp.auth');
        $ip = config('whatsapp.ip');
        $port = config('whatsapp.port');
        $deviceId = config('whatsapp.device_id');

        if (empty($auth) || empty($ip) || empty($port) || empty($deviceId)) {
            $this->sendError = 'Konfigurasi WhatsApp Gateway tidak lengkap. Periksa file .env.';
            Flux::toast(variant: 'error', text: $this->sendError);
            return;
        }

        $this->isSending = true;

        try {
            $baseUrl = "http://{$auth}@{$ip}:{$port}";
            $jid = $this->normalizePhone($this->phone);

            $response = Http::withHeaders([
                'X-Device-Id' => $deviceId,
                'Content-Type' => 'application/json',
            ])->post("$baseUrl/send/message", [
                        'phone' => $jid,
                        'message' => $this->message,
                        'reply_message_id' => '',
                        'is_forwarded' => false,
                        'action' => config('whatsapp.action'),
                        'duration' => (int) config('whatsapp.duration'),
                    ]);

            if ($response->successful()) {
                Flux::toast(variant: 'success', text: 'Pesan berhasil dikirim ke ' . $this->phone);
                $this->reset(['phone', 'message', 'sendError']);
            } else {
                $errorMessage = $this->extractGatewayErrorMessage($response);

                Log::error('WhatsApp Gateway error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'message' => $errorMessage,
                ]);

                $this->sendError = $errorMessage;
                Flux::toast(variant: 'error', text: 'Gagal mengirim pesan. ' . $errorMessage . ' (Status: ' . $response->status() . ')');
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Gateway exception', ['error' => $e->getMessage()]);
            $this->sendError = $e->getMessage();
            Flux::toast(variant: 'error', text: 'Gagal menghubungi WhatsApp Gateway. ' . $this->sendError);
        } finally {
            $this->isSending = false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (!str_contains($phone, '@')) {
            if (str_starts_with($phone, '+')) {
                $phone = ltrim($phone, '+');
            }

            return $phone . '@s.whatsapp.net';
        }

        return $phone;
    }

    private function extractGatewayErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            foreach (['message', 'error', 'detail'] as $key) {
                $value = data_get($json, $key);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        $body = trim($response->body());

        if ($body !== '') {
            return $body;
        }

        return 'Gateway tidak memberikan detail error.';
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Kirim Pesan WhatsApp') }}</flux:heading>
        <flux:subheading>{{ __('Kirim pesan teks melalui WhatsApp Gateway API.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
        <div class="space-y-5">
            @if ($sendError)
                <div
                    class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    <div class="font-medium">{{ __('Gagal mengirim pesan') }}</div>
                    <div class="mt-1 break-words">{{ $sendError }}</div>
                </div>
            @endif

            <form wire:submit="send" class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('Nomor Tujuan') }}
                        <flux:badge size="sm" color="red">Wajib</flux:badge>
                    </flux:label>
                    <flux:input wire:model="phone" type="text"
                        placeholder="6281310307754 atau 6281310307754@s.whatsapp.net" data-test="input-phone" />
                    <flux:description>{{ __('Format: nomor telepon (628xxx) atau JID lengkap (628xxx@s.whatsapp.net).') }}
                    </flux:description>
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Pesan') }}
                        <flux:badge size="sm" color="red">Wajib</flux:badge>
                    </flux:label>
                    <flux:textarea wire:model="message" rows="4" placeholder="Tulis pesan yang ingin dikirim..."
                        data-test="input-message" />
                    <flux:error name="message" />
                </flux:field>

                <div class="flex justify-end gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" :disabled="$isSending" wire:loading.attr="disabled"
                        wire:target="send" data-test="btn-kirim">
                        <span wire:loading.remove wire:target="send">
                            {{ __('Kirim Pesan') }}
                        </span>
                        <span wire:loading wire:target="send">
                            {{ __('Mengirim...') }}
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>

        <aside class="space-y-5">
            <div
                class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ __('Debug Konfigurasi Gateway') }}</div>
                        <div class="text-zinc-500 dark:text-zinc-400">
                            {{ __('Data ini diambil dari file .env melalui config/whatsapp.php.') }}</div>
                    </div>
                    <flux:badge size="sm"
                        :color="$gatewayConfig['base_url'] !== '' && $gatewayConfig['device_id'] !== '' ? 'green' : 'red'">
                        {{ $gatewayConfig['base_url'] !== '' && $gatewayConfig['device_id'] !== '' ? __('Lengkap') : __('Belum Lengkap') }}
                    </flux:badge>
                </div>

                <dl class="space-y-2">
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">AUTH</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['auth'] !== '' ? $gatewayConfig['auth'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">IP</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['ip'] !== '' ? $gatewayConfig['ip'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">PORT</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['port'] !== '' ? $gatewayConfig['port'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">DEVICE ID</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['device_id'] !== '' ? $gatewayConfig['device_id'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">ACTION</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['action'] !== '' ? $gatewayConfig['action'] : '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">DURATION</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['duration'] !== null && $gatewayConfig['duration'] !== '' ? $gatewayConfig['duration'] : '-' }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-[120px_1fr] gap-3 border-t border-zinc-200 pt-2 dark:border-zinc-800">
                        <dt class="text-zinc-500 dark:text-zinc-400">BASE URL</dt>
                        <dd class="break-all font-mono text-zinc-900 dark:text-zinc-100">
                            {{ $gatewayConfig['base_url'] !== '' ? $gatewayConfig['base_url'] : '-' }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</div>