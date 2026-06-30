<?php

namespace App\Http\Middleware;

use App\Services\LoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginOtpVerified
{
    public function __construct(private LoginOtpService $loginOtpService) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if ($this->loginOtpService->isSessionVerified($request)) {
            return $next($request);
        }

        if ($this->loginOtpService->getPendingChallenge($request) === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return redirect()->route('otp.challenge');
    }
}
