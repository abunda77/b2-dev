<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public string $to,
        public string $subject,
        public string $message,
        public string $mailer,
    ) {}

    public function handle(): void
    {
        Mail::mailer($this->mailer)->raw($this->message, function ($mail): void {
            $mail->to($this->to)
                ->subject($this->subject);
        });

        Log::info('Dashboard email sent successfully', [
            'to' => $this->to,
            'subject' => $this->subject,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Dashboard email send failed permanently', [
            'to' => $this->to,
            'subject' => $this->subject,
            'error' => $exception?->getMessage(),
        ]);
    }
}
