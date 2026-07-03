<?php

namespace App\Services;

use App\Jobs\SendOtpJob;
use App\Models\LoginOtpChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginOtpService
{
    public const PendingUserIdKey = 'auth.pending_otp_user_id';

    public const PendingChallengeIdKey = 'auth.pending_otp_challenge_id';

    public const PendingPassedKey = 'auth.pending_otp_passed';

    public const PendingRememberKey = 'auth.pending_otp_remember';

    public const OtpLength = 6;

    public const ExpiryMinutes = 5;

    public const ResendCooldownSeconds = 60;

    public const MaxAttempts = 5;

    public const MaxResends = 3;

    public function issueChallenge(User $user, Request $request, bool $remember = false): LoginOtpChallenge
    {
        $session = $request->session();
        $sessionId = $session->getId();

        $user->loginOtpChallenges()
            ->where('session_id', $sessionId)
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        $otp = $this->generateCode();
        [$channel, $destination] = $this->resolveChannelAndDestination($user);

        /** @var LoginOtpChallenge $challenge */
        $challenge = $user->loginOtpChallenges()->create([
            'session_id' => $sessionId,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::ExpiryMinutes),
            'attempts' => 0,
            'max_attempts' => self::MaxAttempts,
            'resend_count' => 0,
            'max_resends' => self::MaxResends,
            'sent_at' => now(),
            'last_sent_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 65535, ''),
            'sent_status' => 'pending',
        ]);

        try {
            SendOtpJob::dispatch($challenge, $otp);
        } catch (\Throwable $throwable) {
            $challenge->forceFill([
                'sent_status' => 'failed',
                'send_error' => $throwable->getMessage(),
            ])->save();

            throw new \RuntimeException('Gagal mengirim OTP. Silakan coba lagi.', 0, $throwable);
        }

        $session->put(self::PendingUserIdKey, $user->id);
        $session->put(self::PendingChallengeIdKey, $challenge->id);
        $session->put(self::PendingPassedKey, false);
        $session->put(self::PendingRememberKey, $remember);

        Log::info('Login OTP issued', [
            'user_id' => $user->id,
            'channel' => $challenge->channel,
            'destination' => $this->maskDestination($challenge->channel, $challenge->destination),
            'ip' => $request->ip(),
        ]);

        return $challenge;
    }

    public function resendChallenge(Request $request, LoginOtpChallenge $challenge): LoginOtpChallenge
    {
        if ($challenge->revoked_at !== null || $challenge->verified_at !== null) {
            throw new \RuntimeException('Kode OTP sudah tidak aktif. Silakan login ulang.');
        }

        if ($challenge->resend_count >= $challenge->max_resends) {
            throw new \RuntimeException('Batas kirim ulang OTP tercapai. Silakan login ulang.');
        }

        if ($challenge->last_sent_at !== null && $challenge->last_sent_at->diffInSeconds(now()) < self::ResendCooldownSeconds) {
            throw new \RuntimeException('Tunggu sebelum meminta OTP baru.');
        }

        $otp = $this->generateCode();

        $challenge->forceFill([
            'code_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::ExpiryMinutes),
            'attempts' => 0,
            'resend_count' => $challenge->resend_count + 1,
            'last_sent_at' => now(),
            'sent_status' => 'pending',
            'send_error' => null,
        ])->save();

        $challenge->loadMissing('user');

        try {
            SendOtpJob::dispatch($challenge, $otp);
        } catch (\Throwable $throwable) {
            $challenge->forceFill([
                'sent_status' => 'failed',
                'send_error' => $throwable->getMessage(),
            ])->save();

            throw new \RuntimeException('Gagal mengirim OTP. Silakan coba lagi.', 0, $throwable);
        }

        Log::info('Login OTP resent', [
            'user_id' => $challenge->user_id,
            'channel' => $challenge->channel,
            'destination' => $this->maskDestination($challenge->channel, $challenge->destination),
            'ip' => $request->ip(),
        ]);

        $challenge->refresh();

        return $challenge;
    }

    public function verifyChallenge(LoginOtpChallenge $challenge, string $code): bool
    {
        if ($challenge->revoked_at !== null || $challenge->verified_at !== null) {
            throw new \RuntimeException('Kode OTP sudah tidak aktif. Silakan login ulang.');
        }

        if ($challenge->expires_at->isPast()) {
            throw new \RuntimeException('Kode OTP sudah kedaluwarsa. Silakan kirim ulang.');
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            throw new \RuntimeException('Percobaan OTP melebihi batas. Silakan login ulang.');
        }

        $challenge->increment('attempts');
        $challenge->refresh();

        if (! Hash::check($code, $challenge->code_hash)) {
            Log::warning('Login OTP invalid', [
                'user_id' => $challenge->user_id,
                'channel' => $challenge->channel,
                'destination' => $this->maskDestination($challenge->channel, $challenge->destination),
            ]);

            return false;
        }

        $challenge->forceFill([
            'verified_at' => now(),
        ])->save();

        Log::info('Login OTP verified', [
            'user_id' => $challenge->user_id,
            'channel' => $challenge->channel,
            'destination' => $this->maskDestination($challenge->channel, $challenge->destination),
        ]);

        return true;
    }

    public function getPendingChallenge(Request $request): ?LoginOtpChallenge
    {
        $challengeId = $request->session()->get(self::PendingChallengeIdKey);
        $userId = $request->session()->get(self::PendingUserIdKey);

        if (! is_int($challengeId) && ! ctype_digit((string) $challengeId)) {
            return null;
        }

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            return null;
        }

        return LoginOtpChallenge::query()
            ->whereKey((int) $challengeId)
            ->where('user_id', (int) $userId)
            ->first();
    }

    public function clearPendingChallengeState(Request $request): void
    {
        $request->session()->forget([
            self::PendingUserIdKey,
            self::PendingChallengeIdKey,
            self::PendingRememberKey,
        ]);
    }

    public function clearPendingState(Request $request): void
    {
        $request->session()->forget([
            self::PendingUserIdKey,
            self::PendingChallengeIdKey,
            self::PendingPassedKey,
            self::PendingRememberKey,
        ]);
    }

    public function markSessionVerified(Request $request): void
    {
        $request->session()->put(self::PendingPassedKey, true);
    }

    public function isSessionVerified(Request $request): bool
    {
        return $request->session()->get(self::PendingPassedKey) === true;
    }

    public function rememberLogin(Request $request): bool
    {
        return $request->session()->get(self::PendingRememberKey, false) === true;
    }

    public function maskDestination(string $channel, string $destination): string
    {
        if ($channel === 'whatsapp') {
            $phone = preg_replace('/@s\.whatsapp\.net$/', '', $destination) ?? $destination;

            if (strlen($phone) <= 6) {
                return $phone;
            }

            return substr($phone, 0, 5).'****'.substr($phone, -3);
        }

        [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $destination;
        }

        return substr($local, 0, 1).'***@'.$domain;
    }

    public function normalizeWhatsappPhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (Str::startsWith($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        if (Str::startsWith($normalized, '8')) {
            $normalized = '62'.$normalized;
        }

        return $normalized;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveChannelAndDestination(User $user): array
    {
        $preferredChannel = $user->otp_channel_preference === 'whatsapp' ? 'whatsapp' : 'email';
        $normalizedPhone = $user->whatsapp_phone !== null ? $this->normalizeWhatsappPhone($user->whatsapp_phone) : '';

        if ($preferredChannel === 'whatsapp' && $normalizedPhone !== '') {
            return ['whatsapp', $normalizedPhone.'@s.whatsapp.net'];
        }

        return ['email', $user->email];
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::OtpLength, '0', STR_PAD_LEFT);
    }
}
