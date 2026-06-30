<?php

namespace App\Http\Responses;

use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse;

class LoginOtpLoginResponse implements LoginResponse
{
    public function __construct(private LoginOtpService $loginOtpService) {}

    public function toResponse($request): RedirectResponse
    {
        /** @var Request $request */
        $user = Auth::user();

        if ($user === null) {
            return redirect()->route('login');
        }

        try {
            $this->loginOtpService->issueChallenge(
                $user,
                $request,
                $request->boolean('remember'),
            );
        } catch (\Throwable $throwable) {
            Log::error('Login OTP send failed', [
                'user_id' => $user->id,
                'error' => $throwable->getMessage(),
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Gagal mengirim OTP login. Silakan coba lagi.',
            ]);
        }

        return redirect()->route('otp.challenge');
    }
}
