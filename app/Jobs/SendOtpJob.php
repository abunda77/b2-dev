<?php

namespace App\Jobs;

use App\Models\LoginOtpChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOtpJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public LoginOtpChallenge $challenge,
        public string $otpPlaintext,
    ) {}

    public function uniqueId(): string
    {
        return 'otp_send_'.$this->challenge->id.'_'.$this->challenge->resend_count;
    }

    public function handle(): void
    {
        $this->challenge->refresh();

        if ($this->challenge->sent_status === 'sent') {
            return;
        }

        if ($this->challenge->revoked_at !== null || $this->challenge->verified_at !== null) {
            return;
        }

        $message = sprintf(
            'Kode OTP login Anda: %s. Berlaku %d menit. Jangan bagikan kode ini kepada siapa pun.',
            $this->otpPlaintext,
            5,
        );

        if ($this->challenge->channel === 'whatsapp') {
            $this->sendWhatsapp($this->challenge->destination, $message);
        } else {
            $this->sendEmail($this->challenge->destination, $message);
        }

        $this->challenge->forceFill([
            'sent_status' => 'sent',
            'send_error' => null,
        ])->save();

        Log::info('Login OTP sent successfully', [
            'challenge_id' => $this->challenge->id,
            'channel' => $this->challenge->channel,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->challenge->refresh();

        if ($this->challenge->revoked_at !== null || $this->challenge->verified_at !== null) {
            return;
        }

        $this->challenge->forceFill([
            'sent_status' => 'failed',
            'send_error' => $exception?->getMessage() ?? 'Unknown error',
        ])->save();

        Log::error('Login OTP send failed permanently', [
            'challenge_id' => $this->challenge->id,
            'channel' => $this->challenge->channel,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function sendEmail(string $email, string $message): void
    {
        Mail::mailer((string) config('mail.default'))->raw($message, function ($mail) use ($email): void {
            $mail->to($email)
                ->subject('Kode OTP Login Akun Anda');
        });
    }

    private function sendWhatsapp(string $destination, string $message): void
    {
        $auth = (string) config('whatsapp.auth');
        $ip = (string) config('whatsapp.ip');
        $port = (string) config('whatsapp.port');
        $deviceId = (string) config('whatsapp.device_id');

        if ($auth === '' || $ip === '' || $port === '' || $deviceId === '') {
            throw new \RuntimeException('Konfigurasi WhatsApp Gateway tidak lengkap.');
        }

        [$username, $password] = array_pad(explode(':', $auth, 2), 2, null);

        if ($username === null || $password === null || $username === '' || $password === '') {
            throw new \RuntimeException('Format WHATSAPP_AUTH tidak valid.');
        }

        $response = Http::withBasicAuth($username, $password)
            ->connectTimeout(5)
            ->timeout(15)
            ->withHeaders([
                'X-Device-Id' => $deviceId,
                'Content-Type' => 'application/json',
            ])
            ->post("http://{$ip}:{$port}/send/message", [
                'phone' => $destination,
                'message' => $message,
                'reply_message_id' => '',
                'is_forwarded' => false,
                'action' => config('whatsapp.action'),
                'duration' => (int) config('whatsapp.duration'),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractGatewayErrorMessage($response));
        }
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

        return $body !== '' ? $body : 'Gateway tidak memberikan detail error.';
    }
}
