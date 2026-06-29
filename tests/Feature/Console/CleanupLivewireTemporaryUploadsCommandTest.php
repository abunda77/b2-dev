<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupLivewireTemporaryUploadsCommandTest extends TestCase
{
    public function test_command_deletes_only_old_livewire_temporary_files_by_default(): void
    {
        Storage::fake('local');

        config()->set('livewire.temporary_file_upload.disk', 'local');
        config()->set('livewire.temporary_file_upload.directory', 'livewire-tmp');

        Storage::disk('local')->put('livewire-tmp/old-file.txt', 'old');
        Storage::disk('local')->put('livewire-tmp/old-file.txt.json', '{}');
        Storage::disk('local')->put('livewire-tmp/new-file.txt', 'new');

        touch(Storage::disk('local')->path('livewire-tmp/old-file.txt'), Carbon::now()->subHours(30)->getTimestamp());
        touch(Storage::disk('local')->path('livewire-tmp/old-file.txt.json'), Carbon::now()->subHours(30)->getTimestamp());
        touch(Storage::disk('local')->path('livewire-tmp/new-file.txt'), Carbon::now()->subHours(1)->getTimestamp());

        $this->artisan('livewire:clear-tmp')
            ->expectsOutput('2 file temporary dihapus dari [livewire-tmp] pada disk [local].')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('livewire-tmp/old-file.txt');
        Storage::disk('local')->assertMissing('livewire-tmp/old-file.txt.json');
        Storage::disk('local')->assertExists('livewire-tmp/new-file.txt');
    }

    public function test_command_can_delete_all_livewire_temporary_files(): void
    {
        Storage::fake('local');

        config()->set('livewire.temporary_file_upload.disk', 'local');
        config()->set('livewire.temporary_file_upload.directory', 'livewire-tmp');

        Storage::disk('local')->put('livewire-tmp/file-a.txt', 'a');
        Storage::disk('local')->put('livewire-tmp/file-b.txt', 'b');

        $this->artisan('livewire:clear-tmp --all')
            ->expectsOutput('2 file temporary dihapus dari [livewire-tmp] pada disk [local].')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('livewire-tmp/file-a.txt');
        Storage::disk('local')->assertMissing('livewire-tmp/file-b.txt');
    }
}
