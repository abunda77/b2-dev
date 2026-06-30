<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendOtpJob;
use App\Models\LoginOtpChallenge;
use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LoginOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_redirected_to_otp_challenge_after_password_login(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('otp.challenge', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('login_otp_challenges', 1);
    }

    public function test_unverified_otp_session_cannot_access_dashboard(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('otp.challenge', absolute: false));
    }

    public function test_user_can_verify_otp_and_access_dashboard(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = LoginOtpChallenge::query()->firstOrFail();
        $challenge->forceFill([
            'code_hash' => Hash::make('123456'),
        ])->save();

        $service = app(LoginOtpService::class);

        $this->assertTrue($service->verifyChallenge($challenge->fresh(), '123456'));

        session()->put('auth.pending_otp_passed', true);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_whatsapp_preference_creates_whatsapp_challenge(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'ok'], 200),
        ]);

        config()->set('whatsapp.auth', 'demo:secret');
        config()->set('whatsapp.ip', '127.0.0.1');
        config()->set('whatsapp.port', '3000');
        config()->set('whatsapp.device_id', '628123456789@s.whatsapp.net');

        $user = User::factory()->prefersWhatsapp()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('otp.challenge', absolute: false));

        $challenge = LoginOtpChallenge::query()->firstOrFail();

        $this->assertSame('whatsapp', $challenge->channel);
        $this->assertSame('6281310307754@s.whatsapp.net', $challenge->destination);
    }

    public function test_send_otp_job_is_dispatched_on_login(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('otp.challenge', absolute: false));

        Queue::assertPushedOn('otp', SendOtpJob::class);
    }

    public function test_send_otp_job_marks_challenge_as_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = LoginOtpChallenge::query()->firstOrFail();

        $this->assertSame('sent', $challenge->sent_status);
    }

    public function test_send_otp_job_marks_challenge_as_failed_on_error(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'gateway error'], 500),
        ]);

        config()->set('whatsapp.auth', 'demo:secret');
        config()->set('whatsapp.ip', '127.0.0.1');
        config()->set('whatsapp.port', '3000');
        config()->set('whatsapp.device_id', '628123456789@s.whatsapp.net');

        $user = User::factory()->prefersWhatsapp()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = LoginOtpChallenge::query()->firstOrFail();

        $this->assertSame('failed', $challenge->sent_status);
        $this->assertNotNull($challenge->send_error);
    }

    public function test_send_otp_job_respects_idempotency(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = LoginOtpChallenge::query()->firstOrFail();
        $challenge->forceFill(['sent_status' => 'sent'])->save();

        Mail::fake();

        $job = new SendOtpJob($challenge, '123456');
        $job->handle();

        Mail::assertNothingSent();
    }

    public function test_send_otp_job_skips_revoked_challenge(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = LoginOtpChallenge::query()->firstOrFail();
        $challenge->forceFill(['revoked_at' => now(), 'sent_status' => 'pending'])->save();

        Mail::fake();

        $job = new SendOtpJob($challenge->fresh(), '123456');
        $job->handle();

        Mail::assertNothingSent();
    }

    public function test_resend_otp_dispatches_job(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = LoginOtpChallenge::query()->firstOrFail();
        $challenge->forceFill([
            'sent_status' => 'sent',
            'last_sent_at' => now()->subMinutes(2),
        ])->save();

        Queue::fake();

        $service = app(LoginOtpService::class);
        $request = request()->merge([]);
        $request->setLaravelSession(app('session.store'));

        $service->resendChallenge($request, $challenge->fresh());

        Queue::assertPushedOn('otp', SendOtpJob::class);
    }
}
