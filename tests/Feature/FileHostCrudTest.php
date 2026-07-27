<?php

namespace Tests\Feature;

use App\Models\FileHost;
use App\Models\User;
use App\Services\B2UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class FileHostCrudTest extends TestCase
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

    public function test_halaman_file_host_dapat_diakses_user_terautentikasi(): void
    {
        $this->actingAs($this->user);
        session()->put('auth.pending_otp_passed', true);

        $this->get(route('file-host.index'))
            ->assertOk();
    }

    public function test_halaman_file_host_redirect_jika_belum_login(): void
    {
        $this->get(route('file-host.index'))
            ->assertRedirect(route('login'));
    }

    // -------------------------------------------------------
    // Tabel & Pencarian
    // -------------------------------------------------------

    public function test_tabel_menampilkan_data_file_host(): void
    {
        $files = FileHost::factory()->count(3)->create();

        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->assertSee($files->first()->nama)
            ->assertSee($files->first()->original_name);
    }

    public function test_pencarian_menyaring_data(): void
    {
        FileHost::factory()->create(['nama' => 'Laporan Keuangan 2024', 'original_name' => 'laporan.pdf']);
        FileHost::factory()->create(['nama' => 'Foto Kegiatan', 'original_name' => 'foto.jpg']);

        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->set('search', 'Laporan')
            ->assertSee('Laporan Keuangan 2024')
            ->assertDontSee('Foto Kegiatan');
    }

    // -------------------------------------------------------
    // Upload File (Single)
    // -------------------------------------------------------

    public function test_dapat_konfirmasi_single_upload_dan_menyimpan_record(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->call('confirmUpload', 'file-host/doc.pdf', 'Dokumen Penting', 'doc.pdf', 'application/pdf', 2048000)
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('file_hosts', [
            'nama' => 'Dokumen Penting',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048000,
            'path' => 'file-host/doc.pdf',
            'disk' => 'b2',
        ]);
    }

    // -------------------------------------------------------
    // Upload File (Multipart)
    // -------------------------------------------------------

    public function test_dapat_konfirmasi_multipart_upload_dan_menyimpan_record(): void
    {
        $mock = Mockery::mock(B2UploadService::class);
        $mock->shouldReceive('completeMultipartUpload')->once()->with(
            'file-host/big.zip',
            'upload-123',
            Mockery::type('array')
        );

        $this->app->instance(B2UploadService::class, $mock);

        $parts = [
            ['PartNumber' => 1, 'ETag' => 'abc123'],
            ['PartNumber' => 2, 'ETag' => 'def456'],
        ];

        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->call('confirmMultipartUpload', 'file-host/big.zip', 'upload-123', $parts, 'File Besar', 'big.zip', 'application/zip', 10485760)
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('file_hosts', [
            'nama' => 'File Besar',
            'original_name' => 'big.zip',
            'mime_type' => 'application/zip',
            'size' => 10485760,
            'path' => 'file-host/big.zip',
        ]);
    }

    // -------------------------------------------------------
    // Edit File
    // -------------------------------------------------------

    public function test_dapat_edit_nama_file(): void
    {
        $file = FileHost::factory()->create(['nama' => 'Nama Lama']);

        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->call('edit', $file->id)
            ->assertSet('showFormModal', true)
            ->assertSet('nama', 'Nama Lama')
            ->set('nama', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('file_hosts', [
            'id' => $file->id,
            'nama' => 'Nama Baru',
        ]);
    }

    public function test_validasi_gagal_jika_nama_kosong_saat_edit(): void
    {
        $file = FileHost::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->call('edit', $file->id)
            ->set('nama', '')
            ->call('save')
            ->assertHasErrors(['nama']);
    }

    // -------------------------------------------------------
    // Hapus File
    // -------------------------------------------------------

    public function test_dapat_menghapus_file_dan_record(): void
    {
        Storage::disk('b2')->put('file-host/test.pdf', 'file content');

        $file = FileHost::factory()->create([
            'path' => 'file-host/test.pdf',
            'disk' => 'b2',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::file-host.index')
            ->call('confirmDelete', $file->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('fileToDeleteId', $file->id)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('file_hosts', ['id' => $file->id]);
        Storage::disk('b2')->assertMissing('file-host/test.pdf');
    }

    public function test_route_file_host_dapat_diakses(): void
    {
        $this->actingAs($this->user);
        session()->put('auth.pending_otp_passed', true);

        $this->get('/file-host')
            ->assertOk();
    }
}
