<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_google(): void
    {
        $response = $this->get(route('google.redirect'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_callback_creates_new_user_and_sets_google_id(): void
    {
        $abstractUser = Mockery::mock(AbstractUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('123456');
        $abstractUser->shouldReceive('getEmail')->andReturn('test@google.com');
        $abstractUser->shouldReceive('getName')->andReturn('Test User');
        $abstractUser->shouldReceive('getNickname')->andReturnNull();
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://avatar.url');

        Socialite::shouldReceive('driver->user')->once()->andReturn($abstractUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('otp.challenge'));

        $this->assertDatabaseHas('users', [
            'email' => 'test@google.com',
            'google_id' => '123456',
            'avatar' => 'https://avatar.url',
            'name' => 'Test User',
        ]);
    }

    public function test_callback_links_google_id_to_existing_user_by_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@google.com',
            'google_id' => null,
        ]);

        $abstractUser = Mockery::mock(AbstractUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('99999');
        $abstractUser->shouldReceive('getEmail')->andReturn('existing@google.com');
        $abstractUser->shouldReceive('getName')->andReturn('Existing User');
        $abstractUser->shouldReceive('getNickname')->andReturnNull();
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://avatar.url');

        Socialite::shouldReceive('driver->user')->once()->andReturn($abstractUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('otp.challenge'));

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'email' => 'existing@google.com',
            'google_id' => '99999',
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_failure_redirects_to_login_with_error(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andThrow(new \Exception('OAuth error'));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_callback_redirects_to_otp_challenge_after_success(): void
    {
        $abstractUser = Mockery::mock(AbstractUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('55555');
        $abstractUser->shouldReceive('getEmail')->andReturn('otp@google.com');
        $abstractUser->shouldReceive('getName')->andReturn('OTP User');
        $abstractUser->shouldReceive('getNickname')->andReturnNull();
        $abstractUser->shouldReceive('getAvatar')->andReturnNull();

        Socialite::shouldReceive('driver->user')->once()->andReturn($abstractUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('otp.challenge'));
        $this->assertNotNull(session('auth.pending_otp_user_id'));
    }
}
