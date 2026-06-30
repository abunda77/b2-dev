<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SendWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kirim_pesan_tampil_untuk_user_terautentikasi(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('whatsapp.send-message'));

        $response->assertStatus(200);
        $response->assertSee('Kirim Pesan WhatsApp');
    }

    public function test_halaman_kirim_pesan_tampilkan_konfigurasi_gateway(): void
    {
        config()->set('whatsapp.auth', 'user:pass');
        config()->set('whatsapp.ip', '10.10.10.10');
        config()->set('whatsapp.port', '8080');
        config()->set('whatsapp.device_id', 'device-123');
        config()->set('whatsapp.action', 'typing');
        config()->set('whatsapp.duration', 60);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('whatsapp.send-message'));

        $response->assertOk();
        $response->assertSee('Debug Konfigurasi Gateway');
        $response->assertSee('user:pass');
        $response->assertSee('10.10.10.10');
        $response->assertSee('8080');
        $response->assertSee('device-123');
        $response->assertSee('typing');
        $response->assertSee('60');
        $response->assertSee('http://user:pass@10.10.10.10:8080');
        $response->assertSee('Lengkap');
    }

    public function test_halaman_kirim_pesan_blokir_user_tidak_terautentikasi(): void
    {
        $response = $this->get(route('whatsapp.send-message'));

        $response->assertRedirect(route('login'));
    }

    public function test_validasi_phone_wajib_diisi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::whatsapp.send-message')
            ->set('phone', '')
            ->set('message', 'test pesan')
            ->call('send')
            ->assertHasErrors('phone');
    }

    public function test_validasi_message_wajib_diisi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::whatsapp.send-message')
            ->set('phone', '6281310307754')
            ->set('message', '')
            ->call('send')
            ->assertHasErrors('message');
    }

    public function test_validasi_phone_maks_255_karakter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::whatsapp.send-message')
            ->set('phone', str_repeat('6', 256))
            ->set('message', 'test')
            ->call('send')
            ->assertHasErrors('phone');
    }

    public function test_validasi_message_maks_5000_karakter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::whatsapp.send-message')
            ->set('phone', '6281310307754')
            ->set('message', str_repeat('a', 5001))
            ->call('send')
            ->assertHasErrors('message');
    }

    public function test_tampilkan_pesan_error_gateway_saat_kirim_gagal(): void
    {
        config()->set('whatsapp.auth', 'user:pass');
        config()->set('whatsapp.ip', '10.10.10.10');
        config()->set('whatsapp.port', '8080');
        config()->set('whatsapp.device_id', 'device-123');

        Http::fake([
            'http://user:pass@10.10.10.10:8080/send/message' => Http::response([
                'message' => 'Nomor tujuan tidak valid.',
            ], 422),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::whatsapp.send-message')
            ->set('phone', '6281310307754')
            ->set('message', 'test pesan')
            ->call('send')
            ->assertSet('sendError', 'Nomor tujuan tidak valid.')
            ->assertSee('Gagal mengirim pesan')
            ->assertSee('Nomor tujuan tidak valid.');
    }
}
