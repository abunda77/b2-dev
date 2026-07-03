<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WargaCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('b2');

        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------
    // Halaman & Autentikasi
    // -------------------------------------------------------

    public function test_halaman_warga_dapat_diakses_user_terautentikasi(): void
    {
        $this->actingAs($this->user);
        session()->put('auth.pending_otp_passed', true);

        $this->get(route('warga.index'))
            ->assertOk();
    }

    public function test_halaman_warga_redirect_jika_belum_login(): void
    {
        $this->get(route('warga.index'))
            ->assertRedirect(route('login'));
    }

    // -------------------------------------------------------
    // Tabel & Pencarian
    // -------------------------------------------------------

    public function test_tabel_menampilkan_data_warga(): void
    {
        $wargas = Warga::factory()->count(3)->create();

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->assertSee($wargas->first()->nama)
            ->assertSee($wargas->first()->nik);
    }

    public function test_pencarian_menyaring_data(): void
    {
        Warga::factory()->create(['nama' => 'Budi Santoso', 'nik' => '3201010101010001']);
        Warga::factory()->create(['nama' => 'Siti Rahayu', 'nik' => '3201010101010002']);

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Rahayu');
    }

    // -------------------------------------------------------
    // Tambah Warga
    // -------------------------------------------------------

    public function test_dapat_menambah_warga_baru_dengan_upload_b2(): void
    {
        $pasFoto = UploadedFile::fake()->image('foto.jpg');
        $dokumen = UploadedFile::fake()->image('dok.jpg');

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('create')
            ->assertSet('showFormModal', true)
            ->set('nik', '3201010101010099')
            ->set('nama', 'Ahmad Fauzi')
            ->set('alamat', 'Jl. Merdeka No. 1, Jakarta')
            ->set('pasFoto', $pasFoto)
            ->set('dokumen', $dokumen)
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('wargas', [
            'nik' => '3201010101010099',
            'nama' => 'Ahmad Fauzi',
        ]);

        $warga = Warga::where('nik', '3201010101010099')->first();
        Storage::disk('b2')->assertExists($warga->pas_foto);
        Storage::disk('b2')->assertExists($warga->dokumen);
    }

    public function test_dapat_menambah_warga_tanpa_dokumen(): void
    {
        $pasFoto = UploadedFile::fake()->image('foto.jpg');

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('create')
            ->set('nik', '3201010101010088')
            ->set('nama', 'Dewi Lestari')
            ->set('alamat', 'Jl. Sudirman No. 5, Bandung')
            ->set('pasFoto', $pasFoto)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('wargas', ['nik' => '3201010101010088']);

        $warga = Warga::where('nik', '3201010101010088')->first();
        $this->assertNull($warga->dokumen);
    }

    public function test_validasi_gagal_jika_nik_bukan_16_digit(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('create')
            ->set('nik', '123')
            ->set('nama', 'Test')
            ->set('alamat', 'Test Alamat')
            ->set('pasFoto', UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasErrors(['nik']);
    }

    public function test_validasi_gagal_jika_nik_duplikat(): void
    {
        Warga::factory()->create(['nik' => '3201010101010001']);

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('create')
            ->set('nik', '3201010101010001')
            ->set('nama', 'Warga Lain')
            ->set('alamat', 'Alamat Lain')
            ->set('pasFoto', UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasErrors(['nik']);
    }

    public function test_validasi_gagal_jika_pas_foto_kosong_saat_tambah(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('create')
            ->set('nik', '3201010101010077')
            ->set('nama', 'No Foto')
            ->set('alamat', 'Jl. Test')
            ->call('save')
            ->assertHasErrors(['pasFoto']);
    }

    // -------------------------------------------------------
    // Edit Warga
    // -------------------------------------------------------

    public function test_dapat_edit_data_warga_tanpa_ganti_foto(): void
    {
        $warga = Warga::factory()->create(['pas_foto' => 'warga/pas_foto/existing.jpg']);

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('edit', $warga->id)
            ->assertSet('showFormModal', true)
            ->assertSet('nik', $warga->nik)
            ->set('nama', 'Nama Diubah')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('wargas', [
            'id' => $warga->id,
            'nama' => 'Nama Diubah',
        ]);
    }

    public function test_edit_dengan_ganti_pas_foto_menghapus_file_lama(): void
    {
        Storage::disk('b2')->put('warga/pas_foto/lama.jpg', 'isi_file');

        $warga = Warga::factory()->create([
            'pas_foto' => 'warga/pas_foto/lama.jpg',
        ]);

        $fotoBaru = UploadedFile::fake()->image('baru.jpg');

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('edit', $warga->id)
            ->set('pasFoto', $fotoBaru)
            ->call('save')
            ->assertHasNoErrors();

        Storage::disk('b2')->assertMissing('warga/pas_foto/lama.jpg');

        $warga->refresh();
        Storage::disk('b2')->assertExists($warga->pas_foto);
    }

    // -------------------------------------------------------
    // Hapus Warga
    // -------------------------------------------------------

    public function test_dapat_menghapus_warga_dan_file_di_b2(): void
    {
        Storage::disk('b2')->put('warga/pas_foto/foto.jpg', 'isi_file');
        Storage::disk('b2')->put('warga/dokumen/dok.jpg', 'isi_file');

        $warga = Warga::factory()->create([
            'pas_foto' => 'warga/pas_foto/foto.jpg',
            'dokumen' => 'warga/dokumen/dok.jpg',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::warga.index')
            ->call('confirmDelete', $warga->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('wargaToDeleteId', $warga->id)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('wargas', ['id' => $warga->id]);
        Storage::disk('b2')->assertMissing('warga/pas_foto/foto.jpg');
        Storage::disk('b2')->assertMissing('warga/dokumen/dok.jpg');
    }

    public function test_route_warga_dapat_diakses(): void
    {
        $this->actingAs($this->user);
        session()->put('auth.pending_otp_passed', true);

        $this->get('/warga')
            ->assertOk();
    }
}
