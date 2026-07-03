<?php

namespace Tests\Feature;

use App\Jobs\SendEmailMessageJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SendEmailMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kirim_email_tampil_untuk_user_terautentikasi(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('email.send-message'));

        $response->assertOk();
        $response->assertSee('Kirim Email');
    }

    public function test_halaman_kirim_email_tampilkan_konfigurasi_smtp(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.scheme', 'tls');
        config()->set('mail.mailers.smtp.host', 'smtp-relay.brevo.com');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'brevo-user@smtp-brevo.com');
        config()->set('mail.mailers.smtp.password', 'secret-key');
        config()->set('mail.mailers.smtp.local_domain', 'app.contohdomain.com');
        config()->set('mail.from.address', 'no-reply@contohdomain.com');
        config()->set('mail.from.name', 'B2 Dev');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('email.send-message'));

        $response->assertOk();
        $response->assertSee('Debug Konfigurasi SMTP');
        $response->assertSee('smtp');
        $response->assertSee('smtp-relay.brevo.com');
        $response->assertSee('587');
        $response->assertSee('brevo-user@smtp-brevo.com');
        $response->assertSee('tls');
        $response->assertSee('app.contohdomain.com');
        $response->assertSee('no-reply@contohdomain.com');
        $response->assertSee('B2 Dev');
        $response->assertSee('Lengkap');
    }

    public function test_halaman_kirim_email_blokir_user_tidak_terautentikasi(): void
    {
        $response = $this->get(route('email.send-message'));

        $response->assertRedirect(route('login'));
    }

    public function test_validasi_email_tujuan_wajib_diisi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::email.send-message')
            ->set('to', '')
            ->set('subject', 'Subjek')
            ->set('message', 'Isi pesan')
            ->call('send')
            ->assertHasErrors('to');
    }

    public function test_validasi_subject_wajib_diisi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::email.send-message')
            ->set('to', 'penerima@contohdomain.com')
            ->set('subject', '')
            ->set('message', 'Isi pesan')
            ->call('send')
            ->assertHasErrors('subject');
    }

    public function test_validasi_message_wajib_diisi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::email.send-message')
            ->set('to', 'penerima@contohdomain.com')
            ->set('subject', 'Subjek')
            ->set('message', '')
            ->call('send')
            ->assertHasErrors('message');
    }

    public function test_tampilkan_error_saat_konfigurasi_smtp_tidak_lengkap(): void
    {
        config()->set('mail.default', 'log');
        config()->set('mail.mailers.smtp.host', '');
        config()->set('mail.mailers.smtp.port', null);
        config()->set('mail.mailers.smtp.username', '');
        config()->set('mail.mailers.smtp.password', '');
        config()->set('mail.from.address', '');

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::email.send-message')
            ->set('to', 'penerima@contohdomain.com')
            ->set('subject', 'Subjek')
            ->set('message', 'Isi pesan')
            ->call('send')
            ->assertSet('sendError', 'Konfigurasi SMTP belum lengkap. Periksa file .env.')
            ->assertSee('Gagal mengirim email');
    }

    public function test_dapat_mengirim_email_dari_dashboard(): void
    {
        Queue::fake();

        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'smtp-relay.brevo.com');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'brevo-user@smtp-brevo.com');
        config()->set('mail.mailers.smtp.password', 'secret-key');
        config()->set('mail.from.address', 'no-reply@contohdomain.com');

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::email.send-message')
            ->set('to', 'penerima@contohdomain.com')
            ->set('subject', 'Subjek Test')
            ->set('message', 'Isi email test')
            ->call('send')
            ->assertSet('sendError', null)
            ->assertSet('to', '')
            ->assertSet('subject', '')
            ->assertSet('message', '');

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job): bool {
            return $job->to === 'penerima@contohdomain.com'
                && $job->subject === 'Subjek Test'
                && $job->message === 'Isi email test'
                && $job->mailer === 'smtp';
        });
    }
}
