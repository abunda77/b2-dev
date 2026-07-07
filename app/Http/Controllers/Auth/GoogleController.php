<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleController
{
    /**
     * Arahkan user ke halaman izin Google.
     */
    public function redirect(Request $request): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Tangani callback dari Google setelah user menyetujui.
     */
    public function callback(Request $request, LoginOtpService $loginOtpService): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback gagal', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => 'Login Google gagal. Silakan coba lagi.',
            ]);
        }

        $isNewUser = ! User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->exists();

        $user = $this->findOrCreateUser($googleUser);

        Auth::login($user, false);

        // Hormati alur OTP dua langkah yang sudah ada.
        try {
            $loginOtpService->issueChallenge($user, $request, false);
        } catch (\Throwable $e) {
            Log::error('Google login OTP send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Gagal mengirim OTP login. Silakan coba lagi.',
            ]);
        }

        return redirect()->route('otp.challenge')->with('google_new_user', $isNewUser);
    }

    /**
     * Cari user berdasar google_id atau email; buat bila belum ada.
     */
    private function findOrCreateUser(AbstractUser $googleUser): User
    {
        $existing = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($existing !== null) {
            // Tautkan google_id bila user ini dulu mendaftar via form biasa.
            if ($existing->google_id === null) {
                $existing->forceFill(['google_id' => $googleUser->getId()])->save();
            }

            return $existing;
        }

        return User::create([
            'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Google User',
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'password' => Hash::make(Str::random(32)),
            'whatsapp_phone' => null,
            'otp_channel_preference' => 'email', // default; minta user lengkapkan nanti
            'email_verified_at' => now(), // email sudah diverifikasi Google
        ]);
    }
}
