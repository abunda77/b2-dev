<?php

namespace Tests\Feature;

use App\Models\Faktur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FakturGenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('b2');
    }

    public function test_halaman_faktur_tampil(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        $this->get(route('faktur.generate'))
            ->assertOk()
            ->assertSee('Cetak Faktur');
    }

    public function test_generate_faktur_default_a4_tanpa_logo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        Livewire::test('pages::faktur.generate')
            ->set('nama', 'Budi Santoso')
            ->set('items', [
                ['description' => 'Jasa konsultasi', 'qty' => 1, 'price' => 100000, 'subtotal' => 100000],
                ['description' => 'Biaya administrasi', 'qty' => 2, 'price' => 25000, 'subtotal' => 50000],
            ])
            ->set('terbilang', 'Seratus lima puluh ribu rupiah')
            ->set('memo', 'Lunas sebelum tanggal 10.')
            ->set('paperSize', 'a4')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertNotSet('previewDataUri', null);

        $this->assertDatabaseHas(Faktur::class, [
            'user_id' => $user->id,
            'nama' => 'Budi Santoso',
            'paper_size' => 'a4',
            'logo_path' => null,
            'nominal' => 150000.00,
        ]);

        $faktur = Faktur::first();
        $this->assertIsArray($faktur->items);
        $this->assertCount(2, $faktur->items);
        Storage::disk('b2')->assertExists($faktur->pdf_path);
    }

    public function test_generate_faktur_dengan_logo_dan_ukuran_third_a4(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        Livewire::test('pages::faktur.generate')
            ->set('nama', 'PT Contoh')
            ->set('items', [
                ['description' => 'Pengembangan software', 'qty' => 1, 'price' => 2500000, 'subtotal' => 2500000],
            ])
            ->set('terbilang', 'Dua juta lima ratus ribu rupiah')
            ->set('logo', $logo)
            ->set('paperSize', 'third_a4')
            ->call('generate')
            ->assertHasNoErrors();

        $faktur = Faktur::first();
        $this->assertNotNull($faktur->logo_path);
        Storage::disk('b2')->assertExists($faktur->logo_path);
        Storage::disk('b2')->assertExists($faktur->pdf_path);
    }

    public function test_validasi_wajib(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        Livewire::test('pages::faktur.generate')
            ->set('nama', '')
            ->set('items', [])
            ->set('terbilang', '')
            ->call('generate')
            ->assertHasErrors(['nama', 'items', 'terbilang']);
    }

    public function test_validasi_item_kosong(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        Livewire::test('pages::faktur.generate')
            ->set('nama', 'Test')
            ->set('items', [
                ['description' => '', 'qty' => 0, 'price' => 0, 'subtotal' => 0],
            ])
            ->set('terbilang', 'Test')
            ->call('generate')
            ->assertHasErrors(['items.0.description']);
    }

    public function test_paper_size_invalid_ditolak(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);

        Livewire::test('pages::faktur.generate')
            ->set('nama', 'X')
            ->set('items', [
                ['description' => 'Item x', 'qty' => 1, 'price' => 1000, 'subtotal' => 1000],
            ])
            ->set('terbilang', 'Seribu')
            ->set('paperSize', 'b5')
            ->call('generate')
            ->assertHasErrors(['paperSize']);
    }

    public function test_delete_faktur_menghapus_file_b2(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session()->put('auth.pending_otp_passed', true);
        $faktur = Faktur::factory()->create([
            'user_id' => $user->id,
            'pdf_path' => 'faktur/documents/test.pdf',
        ]);
        Storage::disk('b2')->put($faktur->pdf_path, 'dummy');

        Livewire::test('pages::faktur.generate')
            ->call('delete', $faktur->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing(Faktur::class, ['id' => $faktur->id]);
        Storage::disk('b2')->assertMissing($faktur->pdf_path);
    }
}
