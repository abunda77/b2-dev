<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\MarkdownRendererService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_halaman_docs_dapat_diakses_user_terautentikasi(): void
    {
        $this->actingAs($this->user);
        session()->put('auth.pending_otp_passed', true);

        $this->get(route('docs.index'))
            ->assertOk();
    }

    public function test_halaman_docs_redirect_jika_belum_login(): void
    {
        $this->get(route('docs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_menampilkan_dokumen_milik_user(): void
    {
        $doc = Document::factory()->for($this->user)->create([
            'title' => 'Panduan Setup',
        ]);

        Document::factory()->create([
            'title' => 'Dokumen Orang Lain',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->assertSee('Panduan Setup')
            ->assertDontSee('Dokumen Orang Lain');
    }

    public function test_pencarian_menyaring_dokumen(): void
    {
        Document::factory()->for($this->user)->create([
            'title' => 'Panduan API',
            'filename' => 'panduan-api.md',
        ]);
        Document::factory()->for($this->user)->create([
            'title' => 'Catatan Deploy',
            'filename' => 'catatan-deploy.md',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->set('search', 'API')
            ->assertSee('Panduan API')
            ->assertDontSee('Catatan Deploy');
    }

    public function test_dapat_memilih_dokumen(): void
    {
        Storage::fake('local');

        $doc = Document::factory()->for($this->user)->create([
            'title' => 'Contoh Dokumen',
            'disk_path' => 'documents/contoh.md',
            'source' => 'upload',
        ]);

        Storage::disk('local')->put('documents/contoh.md', '# Hello World');

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->call('selectDocument', $doc->id)
            ->assertSet('selectedDocumentId', $doc->id);
    }

    public function test_dapat_upload_dokumen_markdown(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'panduan-baru.md',
            "# Panduan Baru\n\nIni adalah panduan baru."
        );

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->call('openUploadModal')
            ->assertSet('showUploadModal', true)
            ->set('uploadFile', $file)
            ->call('upload')
            ->assertHasNoErrors()
            ->assertSet('showUploadModal', false);

        $this->assertDatabaseHas('documents', [
            'user_id' => $this->user->id,
            'source' => 'upload',
        ]);
    }

    public function test_upload_mengambil_judul_dari_heading_markdown(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'test-heading.md',
            "# Judul Otomatis Dari Heading\n\nKonten di sini."
        );

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->call('openUploadModal')
            ->set('uploadFile', $file)
            ->call('upload')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'user_id' => $this->user->id,
            'title' => 'Judul Otomatis Dari Heading',
        ]);
    }

    public function test_upload_judul_manual_override_heading(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'test-manual.md',
            "# Heading File\n\nKonten."
        );

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->call('openUploadModal')
            ->set('uploadFile', $file)
            ->set('uploadTitle', 'Judul Manual')
            ->call('upload')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'user_id' => $this->user->id,
            'title' => 'Judul Manual',
        ]);
    }

    public function test_dapat_menghapus_dokumen_upload(): void
    {
        Storage::fake('local');

        $doc = Document::factory()->for($this->user)->create([
            'title' => 'Hapus Saya',
            'disk_path' => 'documents/hapus.md',
            'source' => 'upload',
        ]);

        Storage::disk('local')->put('documents/hapus.md', '# Hapus');

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->call('confirmDelete', $doc->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('documentToDeleteTitle', 'Hapus Saya')
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('documents', [
            'id' => $doc->id,
        ]);

        Storage::disk('local')->assertMissing('documents/hapus.md');
    }

    public function test_markdown_renderer_service_converts_markdown_to_html(): void
    {
        $renderer = new MarkdownRendererService;

        $html = $renderer->render("# Hello\n\nWorld **bold** text.");

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_markdown_renderer_service_renders_gfm_tables(): void
    {
        $renderer = new MarkdownRendererService;

        $markdown = "| Col A | Col B |\n| --- | --- |\n| 1 | 2 |";
        $html = $renderer->render($markdown);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Col A', $html);
    }

    public function test_markdown_renderer_service_extracts_title(): void
    {
        $renderer = new MarkdownRendererService;

        $this->assertSame('Panduan Install', $renderer->extractTitle("# Panduan Install\n\nKonten."));
        $this->assertSame('Sub Heading', $renderer->extractTitle("## Sub Heading\n\nKonten."));
        $this->assertNull($renderer->extractTitle('Hanya teks biasa tanpa heading.'));
    }

    public function test_document_model_formatted_file_size(): void
    {
        $doc = Document::factory()->for($this->user)->create(['file_size' => 512]);
        $this->assertSame('512 B', $doc->formatted_file_size);

        $doc = Document::factory()->for($this->user)->create(['file_size' => 2048]);
        $this->assertSame('2.0 KB', $doc->formatted_file_size);

        $doc = Document::factory()->for($this->user)->create(['file_size' => 1572864]);
        $this->assertSame('1.5 MB', $doc->formatted_file_size);
    }

    public function test_user_tidak_bisa_menghapus_dokumen_milik_orang_lain(): void
    {
        $otherDoc = Document::factory()->create([
            'title' => 'Bukan Milik Saya',
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test('pages::docs.index')
            ->call('confirmDelete', $otherDoc->id);
    }
}
