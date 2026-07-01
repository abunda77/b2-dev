<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\QrCodeTemporaryFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QrCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_generate_qr_tampil_untuk_user_terautentikasi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        $response = $this->get(route('qr-code.generate'));

        $response->assertOk();
        $response->assertSee('Generate QR Code');
    }

    public function test_halaman_generate_qr_blokir_user_tidak_terautentikasi(): void
    {
        $response = $this->get(route('qr-code.generate'));

        $response->assertRedirect(route('login'));
    }

    public function test_validasi_teks_qr_wajib_diisi(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        Livewire::test('pages::qr-code.generate')
            ->set('content', '')
            ->call('generate')
            ->assertHasErrors('content');
    }

    public function test_generate_qr_menyimpan_png_dan_jpg_ke_temporary_storage(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        $component = Livewire::test('pages::qr-code.generate')
            ->set('content', 'https://contoh.test/qr')
            ->call('generate')
            ->assertSet('generateError', null)
            ->assertSee('Hasil QR Code');

        $pngFilename = $component->get('pngFilename');
        $jpgFilename = $component->get('jpgFilename');
        $previewDataUri = $component->get('previewDataUri');

        $this->assertIsString($pngFilename);
        $this->assertIsString($jpgFilename);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $previewDataUri);

        Storage::disk('local')->assertExists(QrCodeTemporaryFileService::Directory.'/'.$pngFilename);
        Storage::disk('local')->assertExists(QrCodeTemporaryFileService::Directory.'/'.$jpgFilename);
    }

    public function test_file_qr_temporary_bisa_diunduh_dalam_format_png_dan_jpg(): void
    {
        Storage::fake('local');

        $service = app(QrCodeTemporaryFileService::class);
        $result = $service->generate('QR download test');

        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        $pngResponse = $this->get(route('qr-code.download', ['filename' => $result['png_filename']]));
        $pngResponse->assertOk();
        $pngResponse->assertHeader('content-type', 'image/png');

        $jpgResponse = $this->get(route('qr-code.download', ['filename' => $result['jpg_filename']]));
        $jpgResponse->assertOk();
        $jpgResponse->assertHeader('content-type', 'image/jpeg');
    }

    public function test_file_qr_temporary_bisa_dihapus_manual_dari_halaman(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        $component = Livewire::test('pages::qr-code.generate')
            ->set('content', 'hapus temp')
            ->call('generate');

        $pngFilename = $component->get('pngFilename');
        $jpgFilename = $component->get('jpgFilename');

        $component->call('clearTemporaryFiles')
            ->assertSet('pngFilename', null)
            ->assertSet('jpgFilename', null)
            ->assertSet('previewDataUri', null);

        Storage::disk('local')->assertMissing(QrCodeTemporaryFileService::Directory.'/'.$pngFilename);
        Storage::disk('local')->assertMissing(QrCodeTemporaryFileService::Directory.'/'.$jpgFilename);
    }

    public function test_service_membersihkan_file_qr_temporary_kedaluwarsa(): void
    {
        Storage::fake('local');

        $service = app(QrCodeTemporaryFileService::class);
        $result = $service->generate('expired qr');

        $oldPngPath = Storage::disk('local')->path(QrCodeTemporaryFileService::Directory.'/'.$result['png_filename']);
        $oldJpgPath = Storage::disk('local')->path(QrCodeTemporaryFileService::Directory.'/'.$result['jpg_filename']);

        touch($oldPngPath, Carbon::now()->subHours(QrCodeTemporaryFileService::ExpiryHours + 1)->getTimestamp());
        touch($oldJpgPath, Carbon::now()->subHours(QrCodeTemporaryFileService::ExpiryHours + 1)->getTimestamp());

        $deletedCount = $service->cleanupExpiredFiles();

        $this->assertSame(2, $deletedCount);
        Storage::disk('local')->assertMissing(QrCodeTemporaryFileService::Directory.'/'.$result['png_filename']);
        Storage::disk('local')->assertMissing(QrCodeTemporaryFileService::Directory.'/'.$result['jpg_filename']);
    }
}
