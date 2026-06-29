# Panduan Passkey di Laravel 13

Dokumen ini merangkum cara implementasi dan cara penggunaan fitur **passkey** pada aplikasi Laravel 13 yang memakai **Laravel Fortify**.

## Ringkas

Passkey adalah metode login tanpa password berbasis **WebAuthn**. User bisa masuk memakai:

- sidik jari
- face unlock
- PIN device
- security key USB / NFC
- passkey manager seperti iCloud Keychain, Google Password Manager, atau Windows Hello

Pada Laravel, fitur ini tersedia melalui **Laravel Fortify** dengan integrasi frontend memakai `@laravel/passkeys`.

---

## Referensi Teknis

### Context7

- `/laravel/fortify`
- `/laravel/docs/__branch__13.x`

### Topik utama dari referensi

- aktivasi `Features::passkeys()` di `config/fortify.php`
- konfigurasi WebAuthn: `relying_party_id`, `allowed_origins`, `timeout`
- trait dan interface pada model User
- endpoint registrasi, login, konfirmasi, dan hapus passkey
- integrasi frontend memakai `@laravel/passkeys`

---

## Syarat Implementasi

### 1. Aktifkan fitur passkey di Fortify

Di `config/fortify.php`:

```php
use Laravel\Fortify\Features;

'features' => [
    Features::registration(),
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::updateProfileInformation(),
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]),
    Features::passkeys([
        'confirmPassword' => true,
    ]),
],
```

### 2. Konfigurasi passkeys

Di `config/fortify.php`:

```php
'passkeys' => [
    'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
    'allowed_origins' => [config('app.url')],
    'timeout' => 60000,
],
```

Keterangan:

- `relying_party_id`: domain utama aplikasi
- `allowed_origins`: origin yang diizinkan untuk WebAuthn
- `timeout`: batas waktu challenge dalam milidetik

### 3. Siapkan model user

Model user harus mendukung passkey dengan menambahkan trait `PasskeyAuthenticatable` dan implement interface `PasskeyUser`.

Contoh bentuk umum:

```php
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable;
}
```

Catatan: sesuaikan dengan model auth utama aplikasi.

### 4. Publish migration Fortify bila perlu

```powershell
php artisan vendor:publish --tag=fortify-migrations --no-interaction
php artisan migrate --no-interaction
```

Jika migration passkeys belum ada, langkah ini akan mem-publish migration bawaan Fortify.

### 5. Siapkan frontend

Frontend perlu memakai package `@laravel/passkeys` untuk:

- registrasi passkey
- login pakai passkey
- konfirmasi aksi sensitif
- hapus passkey

---

## Endpoint Passkey

Semua endpoint ini aktif jika `Features::passkeys()` dinyalakan.

| Aksi | Method | Endpoint |
|---|---|---|
| Ambil opsi login passkey | GET | `/passkeys/login/options` |
| Login dengan passkey | POST | `/passkeys/login` |
| Ambil opsi konfirmasi passkey | GET | `/passkeys/confirm/options` |
| Konfirmasi aksi sensitif | POST | `/passkeys/confirm` |
| Ambil opsi registrasi passkey user | GET | `/user/passkeys/options` |
| Simpan passkey baru | POST | `/user/passkeys` |
| Hapus passkey | DELETE | `/user/passkeys/{passkey}` |

---

## Cara User Menggunakan Fitur Passkey

## 1. Menambahkan passkey

Biasanya dilakukan dari halaman:

- Profil
- Pengaturan akun
- Keamanan akun

### Alur user

1. User login biasa memakai email dan password.
2. User buka halaman keamanan akun.
3. User klik tombol **Tambah Passkey**.
4. Frontend meminta opsi registrasi ke backend:
   - `GET /user/passkeys/options`
5. Browser menampilkan dialog WebAuthn.
6. User verifikasi identitas memakai:
   - sidik jari
   - face unlock
   - PIN device
   - security key
7. Frontend mengirim hasil registrasi ke backend:
   - `POST /user/passkeys`
8. Backend menyimpan credential passkey.
9. UI menampilkan passkey yang sudah terdaftar.

### Hasil

Setelah langkah ini, user bisa login tanpa password memakai perangkat tersebut.

---

## 2. Login memakai passkey

Di halaman login, sediakan tombol seperti:

- **Masuk dengan Passkey**
- **Login tanpa password**

### Alur user

1. User buka halaman login.
2. User klik **Masuk dengan Passkey**.
3. Frontend mengambil challenge login:
   - `GET /passkeys/login/options`
4. Browser menampilkan passkey yang tersedia.
5. User memilih passkey.
6. Device meminta verifikasi:
   - fingerprint
   - face unlock
   - PIN
   - security key
7. Frontend mengirim assertion ke backend:
   - `POST /passkeys/login`
8. Jika valid, user langsung login.

### Hasil

User masuk tanpa perlu mengetik password.

---

## 3. Konfirmasi aksi sensitif dengan passkey

Passkey juga bisa dipakai untuk konfirmasi aksi sensitif, misalnya:

- hapus akun
- ubah email
- ubah pengaturan keamanan
- aksi penting lain

### Alur user

1. User memulai aksi sensitif.
2. Frontend meminta opsi challenge:
   - `GET /passkeys/confirm/options`
3. Browser menampilkan prompt passkey.
4. User verifikasi identitas di device.
5. Frontend mengirim hasil ke backend:
   - `POST /passkeys/confirm`
6. Jika valid, aksi dilanjutkan.

---

## 4. Menghapus passkey

Di halaman keamanan akun, tampilkan daftar passkey terdaftar. Contoh label:

- iPhone pribadi
- Windows Hello kantor
- YubiKey

### Alur user

1. User buka halaman keamanan akun.
2. User pilih passkey yang ingin dihapus.
3. User klik hapus.
4. Frontend kirim request:
   - `DELETE /user/passkeys/{passkey}`
5. Backend menghapus credential passkey tersebut.

### Kapan dipakai

- device hilang
- ganti laptop atau HP
- revoke akses perangkat lama

---

## Contoh UX yang disarankan

### Halaman keamanan akun

- Judul: **Passkeys**
- Deskripsi: `Gunakan passkey untuk login lebih cepat dan aman tanpa password.`
- Tombol: **Tambah Passkey**
- Daftar passkey terdaftar
- Tombol hapus per passkey

### Halaman login

- field email
- field password
- tombol **Login**
- tombol sekunder **Masuk dengan Passkey**

### Pesan sukses

- `Passkey berhasil ditambahkan.`
- `Login berhasil menggunakan passkey.`
- `Passkey berhasil dihapus.`

### Pesan gagal

- `Passkey dibatalkan.`
- `Device tidak mendukung passkey.`
- `Tidak ada passkey yang cocok untuk akun ini.`
- `Origin aplikasi tidak valid untuk WebAuthn.`

---

## Praktik UX yang baik

### Disarankan

- tetap sediakan login password sebagai fallback
- sediakan tambah dan hapus passkey di halaman profil
- tampilkan nama perangkat atau label passkey
- minta password dulu saat registrasi passkey pertama bila perlu
- jelaskan bahwa passkey disimpan di device atau password manager

### Hindari

- memaksa passkey tanpa fallback login lain
- menampilkan error mentah dari browser ke user
- mencampur flow register dan login tanpa label jelas

---

## Syarat di sisi user

User hanya bisa memakai passkey jika:

- browser mendukung WebAuthn
  - Chrome
  - Edge
  - Safari
  - Firefox modern
- device mendukung autentikasi lokal
  - Windows Hello
  - Touch ID / Face ID
  - Android biometrics
  - hardware security key
- domain aplikasi sesuai dengan konfigurasi:
  - `APP_URL`
  - `passkeys.allowed_origins`
  - `passkeys.relying_party_id`

---

## Ringkasan Alur

### Registrasi passkey

1. Login biasa
2. Buka halaman keamanan
3. Klik **Tambah Passkey**
4. `GET /user/passkeys/options`
5. Verifikasi di browser / device
6. `POST /user/passkeys`
7. Passkey tersimpan

### Login dengan passkey

1. Buka halaman login
2. Klik **Masuk dengan Passkey**
3. `GET /passkeys/login/options`
4. Pilih passkey
5. Verifikasi di device
6. `POST /passkeys/login`
7. Login berhasil

---

## Catatan Teknis

- Passkey di Laravel Fortify bergantung pada WebAuthn.
- Origin dan domain harus benar. Salah konfigurasi `APP_URL`, `allowed_origins`, atau `relying_party_id` akan membuat proses gagal.
- Untuk aplikasi production, gunakan HTTPS agar WebAuthn berjalan normal.
- Login password tetap sebaiknya dipertahankan sebagai fallback.

---

## Kesimpulan

Passkey memberi login yang lebih cepat dan aman tanpa password. Pada Laravel 13 dengan Fortify, implementasi utamanya terdiri dari:

- aktifkan `Features::passkeys()`
- konfigurasi `passkeys` di `config/fortify.php`
- siapkan model user agar mendukung passkey
- publish migration Fortify jika dibutuhkan
- bangun UI register, login, confirm, dan delete memakai `@laravel/passkeys`

Bagi user, flow utama sangat sederhana:

1. login biasa sekali
2. tambahkan passkey dari halaman keamanan
3. login berikutnya cukup pakai biometrik, PIN, atau security key
