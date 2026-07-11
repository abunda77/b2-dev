# OAUTHLOGIN

## 1. Gambaran Umum dan Tujuan

OAuth login Google adalah fitur yang memungkinkan user masuk ke aplikasi memakai akun Google mereka tanpa perlu mengetik username dan password secara manual. Tujuan utamanya:

- menambah jalur login alternatif yang cepat dan nyaman di halaman login
- memanfaatkan verifier identitas Google sehingga aplikasi tidak menyimpan password untuk jalur ini
- mengurangi friksi registrasi dan login bagi user yang sudah punya akun Google
- tetap menghormati alur keamanan yang sudah ada di aplikasi, khususnya login OTP dua langkah

Implementasi memakai `laravel/socialite` sebagai klien OAuth dan terintegrasi dengan Fortify, model `User`, serta `LoginOtpService` yang sudah ada. Panduan ini disusun untuk stack proyek ini: Laravel 13, PHP 8.3, Livewire 4, Fortify, dan alur OTP.

## 2. Prinsip Desain

- **Opsional**: login Google adalah jalur tambahan, bukan pengganti form email/password yang sudah ada.
- **Aman**: token akses Google tidak disimpan permanen; hanya ID Google yang dipakai untuk mencocokkan akun.
- **Konsisten dengan OTP**: setiap login Google tetap melewati gerbang OTP dua langkah yang sudah berlaku, kecuali ada keputusan eksplisit untuk mengecualikan.
- **Akun terhubung via email**: jika email Google cocok dengan user yang sudah terdaftar, akun itu dipakai; jika belum, dibuatkan user baru.
- **Tidak menyimpan password**: user hasil OAuth tidak punya password; kolom `password` boleh kosong agar login manual tidak mungkin terjadi.
- **Auditabel**: setiap login/registrasi via Google dicatat di log aplikasi.

## 3. Komponen yang Terlibat

- **Google Cloud Console**: tempat membuat OAuth client, consent screen, dan redirect URI.
- **`laravel/socialite`**: klien OAuth Laravel untuk provider Google.
- **`config/services.php`**: menyimpan `client_id`, `client_secret`, dan `redirect` Google.
- **Controller OAuth** (`App\Http\Controllers\Auth\GoogleController`): menangani redirect ke Google dan callback.
- **Model `User`**: akun hasil OAuth disimpan di tabel `users` yang sama, tanpa password.
- **`LoginOtpService`**: dipanggil setelah `Auth::login` agar alur OTP tetap konsisten.
- **`login.blade.php`**: halaman login yang ditambah tombol "Masuk dengan Google".
- **Middleware `login-otp`**: tetap berlaku, jadi setelah callback Google user akan diarahkan ke `/auth/otp-challenge`.

## 4. Prasyarat

- Akses Google Cloud Console dengan izin membuat OAuth credentials.
- Domain aplikasi sudah final (atau pakai `APP_URL` lokal saat dev) karena redirect URI harus didaftarkan persis sama.
- `APP_URL` di `.env` sudah benar, misal `http://localhost:8000` atau domain produksi.
- Queue worker `otp` sudah berjalan bila ingin OTP benar-benar terkirim saat uji: `php artisan queue:work --queue=otp,default`.

## 5. Langkah 1 — Instalasi Socialite

Pasang paket `laravel/socialite` (proyek ini belum memasangnya). Karena mengubah dependency butuh persetujuan, jalankan hanya setelah disepakati:

```bash
composer require laravel/socialite
```

Tidak perlu menambah provider manual di `bootstrap/providers.php` karena Socialite menggunakan auto-discovery Laravel.

## 6. Langkah 2 — Konfigurasi Google Cloud Console

1. Buka https://console.cloud.google.com/ lalu buat atau pilih project.
2. Buka menu **APIs & Services → OAuth consent screen**.
   - Pilih tipe **External** (atau **Internal** bila pakai Google Workspace).
   - Isi app name, email support, dan domain aplikasi.
   - Tambahkan scope `userinfo.email` dan `userinfo.profile` (`.../auth/userinfo.email`, `.../auth/userinfo.profile`).
   - Tambahkan domain aplikasi di bagian **Authorized domains**.
3. Buka **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
   - Application type: **Web application**.
   - Authorized JavaScript origins: `APP_URL` (tanpa trailing slash).
   - Authorized redirect URIs: `{APP_URL}/auth/google/callback`, contoh `http://localhost:8000/auth/google/callback`.
4. Salin **Client ID** dan **Client Secret**.

Catatan untuk dev lokal: bila `APP_URL` memakai HTTPS palsu (mkcert) atau port tidak standar, pastikan redirect URI di Google persis sama dengan yang dipakai aplikasi.

## 7. Langkah 3 — Variabel Environment

Tambahkan ke `.env` (dan `.env.example`):

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Pakai `"${APP_URL}/auth/google/callback"` agar redirect URI selalu mengikuti `APP_URL` dan tidak perlu diperbarui manual saat pindah dev ↔ prod.

## 8. Langkah 4 — Konfigurasi `config/services.php`

Tambahkan blok `google` di array `services`:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

Verifikasi nilai sudah terbaca:

```bash
php artisan config:show services.google
```

## 9. Langkah 5 — Migrasi Skema (Opsional, Disarankan)

Tabel `users` saat ini tidak punya kolom penyimpan ID Google. Walaupun pencocokan bisa berbasis email, menyimpan `google_id` lebih aman agar akun tidak dapat dibajak dengan mendaftarkan email Google yang belum diverifikasi.

Buat migrasi:

```bash
php artisan make:migration add_google_id_to_users_table --no-interaction
```

Isi migrasi:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('google_id')->nullable()->unique()->after('email');
        $table->string('avatar')->nullable()->after('google_id');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['google_id', 'avatar']);
    });
}
```

Jalankan:

```bash
php artisan migrate
```

Tambahkan `google_id` dan `avatar` ke atribut `#[Fillable([...])]` di `app/Models/User.php` agar bisa diisi massal.

## 10. Langkah 6 — Controller OAuth

Buat controller dengan Artisan:

```bash
php artisan make:controller Auth/GoogleController --no-interaction
```

Isi `app/Http/Controllers/Auth/GoogleController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController
{
    /**
     * Arahkan user ke halaman izin Google.
     */
    public function redirect(Request $request): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Tangani callback dari Google setelah user menyetujui.
     */
    public function callback(Request $request, LoginOtpService $loginOtpService): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback gagal', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => 'Login Google gagal. Silakan coba lagi.',
            ]);
        }

        $user = $this->findOrCreateUser($googleUser);

        Auth::login($user, false);

        // Hormati alur OTP dua langkah yang sudah ada.
        try {
            $loginOtpService->issueChallenge($user, $request, false);
        } catch (\Throwable $e) {
            Log::error('Google login OTP send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Gagal mengirim OTP login. Silakan coba lagi.',
            ]);
        }

        return redirect()->route('otp.challenge');
    }

    /**
     * Cari user berdasar google_id atau email; buat bila belum ada.
     */
    private function findOrCreateUser(\Laravel\Socialite\AbstractUser $googleUser): User
    {
        $existing = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($existing !== null) {
            // Tautkan google_id bila user ini dulu mendaftar via form biasa.
            if ($existing->google_id === null) {
                $existing->forceFill(['google_id' => $googleUser->getId()])->save();
            }

            return $existing;
        }

        return User::create([
            'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Google User',
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'password' => null, // jalur OAuth tidak punya password
            'whatsapp_phone' => null,
            'otp_channel_preference' => 'email', // default; minta user lengkapkan nanti
            'email_verified_at' => now(), // email sudah diverifikasi Google
        ]);
    }
}
```

Catatan penting:

- `password => null` membuat kolom `password` kosong. Pastikan migrasi/kolom `password` nullable (default bawaan Laravel sudah nullable). Jika tidak nullable, isi dengan `Hash::make(Str::random(32))` agar tidak bisa ditebak, lalu tandai user sebagai OAuth-only.
- `otp_channel_preference => 'email'` dipakai karena user OAuth belum punya nomor WhatsApp. Setelah login pertama, arahkan user melengkapi profil (lihat bagian 14).
- `email_verified_at => now()` sah karena email sudah diverifikasi oleh Google; hindari memaksa verifikasi ulang via email link.

## 11. Langkah 7 — Rute

Tambahkan di `routes/web.php` (di luar middleware `auth`, karena endpoint ini dipanggil sebelum user login):

```php
use App\Http\Controllers\Auth\GoogleController;

Route::get('auth/google/redirect', [GoogleController::class, 'redirect'])
    ->middleware('guest')
    ->name('google.redirect');

Route::get('auth/google/callback', [GoogleController::class, 'callback'])
    ->middleware('guest')
    ->name('google.callback');
```

Pakai middleware `guest` agar user yang sudah login tidak bisa memicu callback lagi. Verifikasi rute:

```bash
php artisan route:list --path=auth/google
```

## 12. Langkah 8 — Tombol "Masuk dengan Google" di Halaman Login

Edit `resources/views/pages/auth/login.blade.php`. Tambahkan blok tombol Google di antara `<x-passkey-verify />` dan `<form>`, atau di bawah form sesuai selera UI. Contoh penempatan sebelum form:

```blade
<div class="flex flex-col gap-3">
    <flux:button
        variant="subtle"
        type="link"
        :href="route('google.redirect')"
        class="w-full"
        wire:navigate
    >
        <flux:icon brand="google" class="size-4" />
        {{ __('Masuk dengan Google') }}
    </flux:button>
</div>

<div class="relative my-2">
    <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-zinc-200 dark:border-zinc-700"></div>
    </div>
    <div class="relative flex justify-center text-xs uppercase">
        <span class="bg-white dark:bg-zinc-900 px-2 text-zinc-500">{{ __('atau') }}</span>
    </div>
</div>
```

Catatan: `wire:navigate` pada link keluar domain (ke Google) sebaiknya tidak dipakai karena tujuannya domain eksternal. Bila Flux `button type="link"` memakai `wire:navigate` internal, ganti jadi tag `<a href="...">` biasa agar redirect HTTP murni:

```blade
<a href="{{ route('google.redirect') }}" class="...">
    <flux:icon brand="google" class="size-4" />
    {{ __('Masuk dengan Google') }}
</a>
```

## 13. Langkah 9 — Interaksi dengan Alur OTP Login

Aplikasi memakai `LoginOtpLoginResponse` yang memaksa setiap login Fortify menuju `/auth/otp-challenge`. Agar konsisten, controller Google di atas juga memanggil `LoginOtpService::issueChallenge()` lalu redirect ke `otp.challenge`.

Alternatif keputusan (pilih satu dan dokumentasikan):

- **A. Sama dengan login biasa** (disarankan): OTP tetap dikirim via email/WhatsApp setelah login Google. Keamanan dua lapis tetap utuh; user OAuth yang belum punya WhatsApp akan menerima OTP via email.
- **B. Skip OTP untuk Google**: bila dianggap Google sudah cukup sebagai faktor pertama, redirect langsung ke dashboard. Untuk ini jangan panggil `issueChallenge`; sebaliknya tandai session sebagai verified (lihat `EnsureLoginOtpVerified`) sebelum redirect ke `dashboard`, dan dokumentasikan pengecualian ini secara eksplisit.

Jangan biarkan jalur Google membypass `login-otp` middleware tanpa keputusan sadar — itu akan menciptakan pintu belakang yang melewati OTP sementara login biasa tetap wajib OTP.

## 14. Langkah 10 — Profil Pasca-Login

User hasil OAuth sering belum punya `whatsapp_phone`. Setelah login pertama, arahkan ke halaman settings profile untuk melengkapi:

- `whatsapp_phone`
- `otp_channel_preference` (bila ingin OTP via WhatsApp)

Tambahkan flash flag saat callback bila user baru:

```php
return redirect()->route('otp.challenge')->with('google_new_user', true);
```

Lalu di halaman otp-challenge atau dashboard, tampilkan banner "Lengkapi profil Anda" bila session berisi flag itu. Detail implementasi banner mengikuti pola Livewire/Blade yang sudah ada di `pages::settings`.

## 15. Langkah 11 — Format Pintar

Setelah semua file PHP diedit, jalankan Pint:

```bash
vendor/bin/pint --dirty --format agent
```

## 16. Langkah 12 — Pengujian

Buat feature test mengikuti konvensi proyek:

```bash
php artisan make:test --phpunit GoogleLoginTest --no-interaction
```

Skenario yang wajib diuji:

1. redirect ke Google mengembalikan 302 ke `accounts.google.com`.
2. callback dengan user Google baru membuat user + menetapkan `google_id`.
3. callback dengan email yang sudah terdaftar (tanpa `google_id`) menautkan `google_id` ke user lama, tidak membuat duplikat.
4. callback gagal (Socialite throw) mengarahkan balik ke `login` dengan error.
5. setelah callback sukses, user diarahkan ke `otp.challenge` (bukan langsung dashboard).

Untuk mock Socialite di test, bind instance `Laravel\Socialite\Contracts\Provider` palsu via `Socialite::shouldReceive('driver->user')->andReturn(...)`. Ikuti pola factory yang ada (`UserFactory`); jangan buat model manual.

Jalankan:

```bash
php artisan test --compact tests/Feature/GoogleLoginTest.php
```

Setelah lulus, jalankan suite penuh untuk memastikan tidak ada regresi:

```bash
php artisan test --compact
```

## 17. Catatan Keamanan

- **State parameter**: Socialite otomatis menyertakan parameter `state` CSRF. Pastikan tidak dimatikan.
- **Email verified**: Google mengembalikan `user` yang sudah diverifikasi hanya bila scope `userinfo.email` disetujui. Tetap periksa `googleUser->user['verified_email']` bila ingin ketat.
- **Jangan simpan access token Google** di DB untuk login; token hanya dipakai sekali untuk mengambil profil.
- **Hindari akun bocor**: gunakan `google_id` sebagai kunci unik, bukan email, agar email Google yang ditiru tidak membuka akun orang lain.
- **Rate limit**: tambahkan rate limiter untuk rute `google.callback` bila khawatir penyalahgunaan, mengikuti pola `RateLimiter::for('login')` di `FortifyServiceProvider`.
- **Produksi**: pastikan `GOOGLE_CLIENT_SECRET` tidak ter-commit. Taruh di `.env` dan rotasi bila bocor.

## 18. Ringkasan Keputusan Desain

- Login Google adalah jalur opsional di samping form email/password.
- Memakai `laravel/socialite` + provider `google` di `config/services.php`.
- Pencocokan akun: `google_id` utama, fallback ke `email`, lalu buat user baru bila tidak ada.
- User OAuth tidak punya password; `password` null atau random tidak tebak-able.
- Alur OTP dua langkah tetap berlaku untuk login Google (opsi A, disarankan).
- Email dianggap terverifikasi (`email_verified_at = now`) karena Google sudah memverifikasi.
- Redirect URI mengikuti `APP_URL` via `GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"`.
- Profil (WhatsApp, preferensi OTP channel) dilengkapi pasca-login pertama.

## 19. Penutup

Panduan ini memberi kerangka menambahkan login Google di halaman login proyek tanpa mengganggu form yang sudah ada. Kunci integrasi ada di konsistensi dengan `LoginOtpService` dan `login-otp` middleware: jalur OAuth tidak boleh diam-diam melewati OTP. Implementasi detail (mock Socialite, banner profil, keputusan skip OTP) dapat disesuaikan selama prinsip "akun terhubung via `google_id`, bukan email liar" dan "OTP tetap berlaku" tetap dipatuhi.
