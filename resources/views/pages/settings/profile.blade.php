<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $whatsapp_phone = '';
    public string $otp_channel_preference = 'email';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->whatsapp_phone = Auth::user()->whatsapp_phone ?? '';
        $this->otp_channel_preference = Auth::user()->otp_channel_preference;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));
        $validated['whatsapp_phone'] = $validated['whatsapp_phone'] !== '' ? $validated['whatsapp_phone'] : null;

        $user->fill($validated);

        if ($user->otp_channel_preference === 'whatsapp' && blank($user->whatsapp_phone)) {
            $this->addError('whatsapp_phone', __('Nomor WhatsApp wajib diisi jika kanal OTP memakai WhatsApp.'));

            return;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name, email, WhatsApp number, and login OTP settings')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <flux:field>
                <flux:label>{{ __('Nomor WhatsApp') }}</flux:label>
                <flux:input wire:model="whatsapp_phone" type="text" autocomplete="tel" placeholder="6281310307754" />
                <flux:description>{{ __('Dipakai bila kanal OTP login menggunakan WhatsApp.') }}</flux:description>
                <flux:error name="whatsapp_phone" />
            </flux:field>

            <div class="space-y-3">
                <flux:text>{{ __('Kanal OTP Login') }}</flux:text>
                <flux:radio.group wire:model="otp_channel_preference" variant="segmented">
                    <flux:radio value="email">{{ __('Email') }}</flux:radio>
                    <flux:radio value="whatsapp">{{ __('WhatsApp') }}</flux:radio>
                </flux:radio.group>
                <flux:error name="otp_channel_preference" />
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
