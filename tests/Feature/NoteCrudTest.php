<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoteCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_halaman_notes_dapat_diakses_user_terautentikasi(): void
    {
        $this->actingAs($this->user);
        session()->put('auth.pending_otp_passed', true);

        $this->get(route('notes.index'))
            ->assertOk();
    }

    public function test_halaman_notes_redirect_jika_belum_login(): void
    {
        $this->get(route('notes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_tabel_menampilkan_catatan_milik_user(): void
    {
        $note = Note::factory()->for($this->user)->create([
            'title' => 'Belanja Bulanan',
            'notes' => 'Beli beras dan minyak',
        ]);

        Note::factory()->create([
            'title' => 'Catatan Orang Lain',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->assertSee($note->title)
            ->assertSee($note->notes)
            ->assertDontSee('Catatan Orang Lain');
    }

    public function test_pencarian_menyaring_catatan(): void
    {
        Note::factory()->for($this->user)->create([
            'title' => 'Meeting Tim',
            'notes' => 'Bahas target mingguan',
        ]);
        Note::factory()->for($this->user)->create([
            'title' => 'Belanja',
            'notes' => 'Beli buah',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->set('search', 'Meeting')
            ->assertSee('Meeting Tim')
            ->assertDontSee('Belanja');
    }

    public function test_dapat_menambah_note_baru(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->call('create')
            ->assertSet('showFormModal', true)
            ->set('title', 'Agenda Besok')
            ->set('notes', 'Follow up invoice dan kirim email')
            ->set('noteDate', '2026-07-03')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('notes', [
            'user_id' => $this->user->id,
            'title' => 'Agenda Besok',
        ]);

        $note = Note::query()
            ->where('user_id', $this->user->id)
            ->where('title', 'Agenda Besok')
            ->firstOrFail();

        $this->assertSame('2026-07-03', $note->note_date?->toDateString());
    }

    public function test_validasi_note_wajib_diisi(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->call('create')
            ->set('title', '')
            ->set('notes', '')
            ->set('noteDate', '')
            ->call('save')
            ->assertHasErrors(['title', 'notes', 'noteDate']);
    }

    public function test_dapat_melihat_detail_note(): void
    {
        $note = Note::factory()->for($this->user)->create([
            'title' => 'Catatan Rapat',
            'notes' => 'Bahas target Q3',
            'note_date' => '2026-07-01',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->call('show', $note->id)
            ->assertSet('showViewModal', true)
            ->assertSet('viewTitle', 'Catatan Rapat')
            ->assertSet('viewNotes', 'Bahas target Q3')
            ->assertSet('viewDate', '01/07/2026');
    }

    public function test_dapat_edit_note(): void
    {
        $note = Note::factory()->for($this->user)->create([
            'title' => 'Draft Lama',
            'notes' => 'Isi lama',
            'note_date' => '2026-07-01',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->call('edit', $note->id)
            ->assertSet('title', 'Draft Lama')
            ->set('title', 'Draft Baru')
            ->set('notes', 'Isi baru')
            ->set('noteDate', '2026-07-04')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Draft Baru',
            'notes' => 'Isi baru',
        ]);

        $note->refresh();

        $this->assertSame('2026-07-04', $note->note_date?->toDateString());
    }

    public function test_aksi_table_notes_muncul(): void
    {
        $note = Note::factory()->for($this->user)->create();

        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->assertSeeHtml('data-test="btn-view-note-'.$note->id.'"')
            ->assertSeeHtml('data-test="btn-copy-note-'.$note->id.'"');
    }

    public function test_dapat_menghapus_note(): void
    {
        $note = Note::factory()->for($this->user)->create([
            'title' => 'Hapus Saya',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::notes.index')
            ->call('confirmDelete', $note->id)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('notes', [
            'id' => $note->id,
        ]);
    }
}
