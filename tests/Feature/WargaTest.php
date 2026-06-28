<?php

namespace Tests\Feature;

use App\Models\Warga;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_membuat_warga_dengan_factory(): void
    {
        $warga = Warga::factory()->create();

        $this->assertDatabaseHas('wargas', [
            'nik' => $warga->nik,
            'nama' => $warga->nama,
        ]);
    }

    public function test_nik_harus_unik(): void
    {
        $warga = Warga::factory()->create(['nik' => '3201010101010001']);

        $this->expectException(QueryException::class);

        Warga::factory()->create(['nik' => '3201010101010001']);
    }

    public function test_dokumen_boleh_null(): void
    {
        $warga = Warga::factory()->create(['dokumen' => null]);

        $this->assertNull($warga->dokumen);
        $this->assertNull($warga->dokumen_url);
    }

    public function test_accessor_pas_foto_url(): void
    {
        $warga = Warga::factory()->create([
            'pas_foto' => 'warga/pas_foto/test.jpg',
        ]);

        $this->assertStringContainsString('warga/pas_foto/test.jpg', $warga->pas_foto_url);
    }

    public function test_accessor_dokumen_url_ketika_tersedia(): void
    {
        $warga = Warga::factory()->create([
            'dokumen' => 'warga/dokumen/test.jpg',
        ]);

        $this->assertStringContainsString('warga/dokumen/test.jpg', $warga->dokumen_url);
    }

    public function test_fillable_bekerja(): void
    {
        $warga = Warga::create([
            'nik' => '3201010101010002',
            'nama' => 'Budi Santoso',
            'alamat' => 'Jl. Merdeka No. 1, Jakarta',
            'pas_foto' => 'warga/pas_foto/budi.jpg',
            'dokumen' => null,
        ]);

        $this->assertInstanceOf(Warga::class, $warga);
        $this->assertEquals('Budi Santoso', $warga->nama);
        $this->assertEquals('3201010101010002', $warga->nik);
    }
}
