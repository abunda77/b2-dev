<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('auth/otp-challenge', 'pages::auth.otp-challenge')->name('otp.challenge');
});

Route::middleware(['auth', 'verified', 'login-otp'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('warga', 'pages::warga.index')->name('warga.index');
    Route::livewire('whatsapp/send-message', 'pages::whatsapp.send-message')->name('whatsapp.send-message');
    Route::livewire('email/send-message', 'pages::email.send-message')->name('email.send-message');
});

require __DIR__.'/settings.php';
