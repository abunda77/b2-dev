<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('warga', 'pages::warga.index')->name('warga.index');
    Route::livewire('whatsapp/send-message', 'pages::whatsapp.send-message')->name('whatsapp.send-message');
});

require __DIR__.'/settings.php';
