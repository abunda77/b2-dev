# Changelog

Semua perubahan penting pada proyek ini dicatat di file ini.

Format berdasarkan [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
dan proyek mengikuti [Semantic Versioning](https://semver.org/lang/id/).

## [Unreleased]

### Added

- Fitur cetak invoice / faktur PDF dengan preview, riwayat, upload logo, dan penyimpanan B2 ([42a64d5](https://github.com/laravel/livewire-starter-kit/commit/42a64d5))
- Fitur AI chatbot multi-provider beserta provider config dan perbaikan 9Router ([a922fbb](https://github.com/laravel/livewire-starter-kit/commit/a922fbb))
- Fitur generate QR code ([5ae108f](https://github.com/laravel/livewire-starter-kit/commit/5ae108f))
- OTP challenge untuk verifikasi login ([f2103b7](https://github.com/laravel/livewire-starter-kit/commit/f2103b7))
- Konfigurasi mail service Brevo ([4b8ae34](https://github.com/laravel/livewire-starter-kit/commit/4b8ae34))
- Panduan integrasi Brevo ([a17c396](https://github.com/laravel/livewire-starter-kit/commit/a17c396))
- WhatsApp Gateway untuk notifikasi ([6c20552](https://github.com/laravel/livewire-starter-kit/commit/6c20552))
- Index database untuk optimasi performa query ([90a9519](https://github.com/laravel/livewire-starter-kit/commit/90a9519))

### Changed

- Animasi spinning pada tombol submit saat loading ([3ac4eb4](https://github.com/laravel/livewire-starter-kit/commit/3ac4eb4))
- Update dokumentasi `Laravel13.md` dan integrasi Laravel SDK ([0ded6cd](https://github.com/laravel/livewire-starter-kit/commit/0ded6cd))
- Sinkronisasi changelog dengan status git terkini ([ddab39a](https://github.com/laravel/livewire-starter-kit/commit/ddab39a))
- Layout form menjadi 2 kolom untuk efisiensi ruang ([e585e9c](https://github.com/laravel/livewire-starter-kit/commit/e585e9c))

### Fixed

- Antrian email tidak terkirim karena kesalahan dispatch job ([04676a0](https://github.com/laravel/livewire-starter-kit/commit/04676a0))

### Security

- Rotasi sensitive key pada konfigurasi ([3abf31e](https://github.com/laravel/livewire-starter-kit/commit/3abf31e))

## [0.1.0] - 2026-06-29

### Added

- Panduan implementasi passkey authentication ([d77497f](https://github.com/laravel/livewire-starter-kit/commit/d77497f))
- Artisan command untuk membersihkan file temporary ([980c79a](https://github.com/laravel/livewire-starter-kit/commit/980c79a))
- Dokumentasi proyek pada README.md ([2c0c261](https://github.com/laravel/livewire-starter-kit/commit/2c0c261))
- Modul CRUD data warga ([eb8e8da](https://github.com/laravel/livewire-starter-kit/commit/eb8e8da))
- Test koneksi ke endpoint B2 ([7940aad](https://github.com/laravel/livewire-starter-kit/commit/7940aad))

### Security

- Rotasi sensitive key pada environment ([3abf31e](https://github.com/laravel/livewire-starter-kit/commit/3abf31e))

## [0.0.1] - 2026-06-28

### Added

- Inisialisasi proyek Laravel Livewire Starter Kit ([cfc9b33](https://github.com/laravel/livewire-starter-kit/commit/cfc9b33))

[Unreleased]: https://github.com/laravel/livewire-starter-kit/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/laravel/livewire-starter-kit/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/laravel/livewire-starter-kit/releases/tag/v0.0.1
