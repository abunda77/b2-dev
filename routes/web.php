<?php

use App\Services\QrCodeTemporaryFileService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('auth/otp-challenge', 'pages::auth.otp-challenge')->name('otp.challenge');
});

Route::middleware(['auth', 'verified', 'login-otp'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('chat', 'pages::chat.index')->name('chat.index');

    Route::livewire('warga', 'pages::warga.index')->name('warga.index');
    Route::livewire('whatsapp/send-message', 'pages::whatsapp.send-message')->name('whatsapp.send-message');
    Route::livewire('email/send-message', 'pages::email.send-message')->name('email.send-message');
    Route::livewire('qr-code/generate', 'pages::qr-code.generate')->name('qr-code.generate');
    Route::livewire('faktur/generate', 'pages::faktur.generate')->name('faktur.generate');

    Route::get('qr-code/download/{filename}', function (string $filename, QrCodeTemporaryFileService $temporaryFileService) {
        $path = $temporaryFileService->path($filename);
        $storage = Storage::disk($temporaryFileService->disk());

        abort_unless($storage->exists($path), 404);

        return response()->streamDownload(function () use ($storage, $path): void {
            echo $storage->get($path);
        }, $filename, [
            'Content-Type' => $temporaryFileService->mimeType($filename),
        ]);
    })->name('qr-code.download');
});

require __DIR__.'/settings.php';
