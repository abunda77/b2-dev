<?php

use App\Services\LoginOtpService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Verifikasi OTP Login')] #[Layout('layouts.auth')] class extends Component {
    #[Validate(['required', 'digits:6'])]
    public string $code = '';

    public ?string $errorMessage = null;

    public ?string $statusMessage = null;

    public string $maskedDestination = '-';

    public string $channelLabel = 'email';

    public ?string $expiresAtLabel = null;

    public string $sentStatus = 'pending';

    public ?string $sendError = null;

    public function mount(LoginOtpService $loginOtpService): void
    {
        $challenge = $loginOtpService->getPendingChallenge(request());

        if (Auth::guest() || $challenge === null) {
            $this->redirectRoute('login');

            return;
        }

        if ($loginOtpService->isSessionVerified(request())) {
            $this->redirectRoute('dashboard');

            return;
        }

        $this->fillChallengeState($loginOtpService);
    }

    public function verify(LoginOtpService $loginOtpService): void
    {
        $this->validate();
        $this->errorMessage = null;

        $challenge = $loginOtpService->getPendingChallenge(request());

        if ($challenge === null) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
            $this->redirectRoute('login');

            return;
        }

        try {
            if (! $loginOtpService->verifyChallenge($challenge, $this->code)) {
                $this->errorMessage = 'Kode OTP tidak valid.';
                Flux::toast(variant: 'error', text: $this->errorMessage);
                $this->code = '';
                $this->fillChallengeState($loginOtpService);

                return;
            }

            $loginOtpService->markSessionVerified(request());
            $loginOtpService->clearPendingChallengeState(request());
            session()->regenerate();
            Flux::toast(variant: 'success', text: 'Verifikasi OTP berhasil.');
            $this->redirectRoute('dashboard');
        } catch (\Throwable $throwable) {
            $this->errorMessage = $throwable->getMessage();
            Flux::toast(variant: 'error', text: $this->errorMessage);
            $this->fillChallengeState($loginOtpService);
        }
    }

    public function resend(LoginOtpService $loginOtpService): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        $challenge = $loginOtpService->getPendingChallenge(request());

        if ($challenge === null) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
            $this->redirectRoute('login');

            return;
        }

        try {
            $loginOtpService->resendChallenge(request(), $challenge);
            $this->statusMessage = 'Kode OTP baru sedang dikirim...';
            $this->code = '';
            $this->fillChallengeState($loginOtpService);
        } catch (\Throwable $throwable) {
            $this->errorMessage = $throwable->getMessage();
            Flux::toast(variant: 'error', text: $this->errorMessage);
            $this->fillChallengeState($loginOtpService);
        }
    }

    public function pollStatus(LoginOtpService $loginOtpService): void
    {
        $challenge = $loginOtpService->getPendingChallenge(request());

        if ($challenge === null) {
            $this->sentStatus = 'pending';

            return;
        }

        $this->fillChallengeState($loginOtpService);

        if ($this->sentStatus !== 'sent') {
            return;
        }

        if ($this->statusMessage === null) {
            $this->statusMessage = 'Kode OTP berhasil dikirim.';
            Flux::toast(variant: 'success', text: $this->statusMessage);
        }
    }

    private function fillChallengeState(LoginOtpService $loginOtpService): void
    {
        $challenge = $loginOtpService->getPendingChallenge(request());

        if ($challenge === null) {
            $this->maskedDestination = '-';
            $this->channelLabel = 'email';
            $this->expiresAtLabel = null;
            $this->sentStatus = 'pending';
            $this->sendError = null;

            return;
        }

        $this->maskedDestination = $loginOtpService->maskDestination($challenge->channel, $challenge->destination);
        $this->channelLabel = $challenge->channel === 'whatsapp' ? 'WhatsApp' : 'email';
        $this->expiresAtLabel = $challenge->expires_at->format('H:i:s');
        $this->sentStatus = $challenge->sent_status;
        $this->sendError = $challenge->send_error;
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.2s="pollStatus">
    <x-auth-header :title="__('Verifikasi OTP Login')"
        :description="__('Login berhasil. Masukkan kode OTP 6 digit yang dikirim ke :channel Anda.', ['channel' => $channelLabel])" />

    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-200">
        <div>{{ __('Tujuan: :destination', ['destination' => $maskedDestination]) }}</div>
        <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Masa berlaku hingga :time', ['time' => $expiresAtLabel ?? '-']) }}</div>
    </div>

    @if ($sentStatus === 'pending')
        <div class="flex items-center gap-3 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700 dark:border-yellow-900/60 dark:bg-yellow-950/40 dark:text-yellow-300">
            <svg class="size-4 shrink-0 animate-spin text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span>{{ __('Kode OTP sedang dikirim ke :channel Anda...', ['channel' => $channelLabel]) }}</span>
        </div>
    @elseif ($sentStatus === 'failed')
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            <div class="font-medium">{{ __('Gagal mengirim OTP.') }}</div>
            @if ($sendError)
                <div class="mt-1 text-red-600 dark:text-red-400">{{ $sendError }}</div>
            @endif
            <div class="mt-2">{{ __('Silakan klik "Kirim Ulang OTP" untuk mencoba lagi.') }}</div>
        </div>
    @endif

    @if ($statusMessage)
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="verify" class="space-y-6">
        <div class="flex justify-center">
            <flux:otp wire:model="code" length="6" name="code" :label="__('Kode OTP')" label:sr-only class="mx-auto" />
        </div>

        <flux:error name="code" />

        <div class="flex flex-col gap-3">
            <flux:button variant="primary" type="submit" class="w-full" wire:loading.attr="disabled" wire:target="verify,resend"
                :disabled="$sentStatus !== 'sent'">
                <span wire:loading.remove wire:target="verify">{{ __('Verifikasi OTP') }}</span>
                <span wire:loading wire:target="verify">{{ __('Memverifikasi...') }}</span>
            </flux:button>

            <flux:button variant="ghost" type="button" class="w-full" wire:click="resend" wire:loading.attr="disabled" wire:target="verify,resend"
                :disabled="$sentStatus === 'pending'">
                <span wire:loading.remove wire:target="resend">{{ __('Kirim Ulang OTP') }}</span>
                <span wire:loading wire:target="resend">{{ __('Mengirim Ulang...') }}</span>
            </flux:button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <flux:button variant="ghost" type="submit" class="w-full text-sm cursor-pointer" data-test="logout-button">
            {{ __('Log out') }}
        </flux:button>
    </form>
</div>
