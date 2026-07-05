<?php

namespace App\Providers;

use App\Ai\Providers\NineRouterProvider;
use App\Http\Responses\LoginOtpLoginResponse;
use App\Http\Responses\LoginOtpRegisterResponse;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Ai;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, LoginOtpLoginResponse::class);
        $this->app->singleton(RegisterResponse::class, LoginOtpRegisterResponse::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAiDrivers();
    }

    protected function configureAiDrivers(): void
    {
        Ai::extend('9router', function ($app, array $config) {
            return new NineRouterProvider($config, $this->app->make(Dispatcher::class));
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
