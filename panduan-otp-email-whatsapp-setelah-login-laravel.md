# Panduan OTP Email / WhatsApp Setelah Login Password di Laravel 13

Dokumen ini menjelaskan pola implementasi **OTP lapis kedua** setelah user **sukses login dengan email dan password** di aplikasi **Laravel 13**. Tujuan utama: menambah layer keamanan akun sebelum user mendapat akses penuh ke area aplikasi.

Panduan ini disusun berdasarkan referensi internal berikut:

- `panduan-smtp-brevo-laravel.md`
- `WHATSAPPGATEWAY.md`
- `config/mail.php:17`
- `config/mail.php:40`
- `config/mail.php:113`
- `config/whatsapp.php:4`
- `config/fortify.php:18`
- `config/fortify.php:76`
- `config/fortify.php:163`
- `app/Providers/FortifyServiceProvider.php:48`

---

## 1. Tujuan

Flow yang ingin dicapai:

1. User login memakai **email + password**.
2. Kredensial utama valid.
3. Sistem **belum** langsung memberi akses penuh ke dashboard.
4. Sistem mengirim **OTP** ke **email** atau **WhatsApp**.
5. User memasukkan OTP.
6. Jika OTP valid dan belum kedaluwarsa, barulah user dianggap lolos verifikasi login.

Manfaat pola ini:

- mengurangi risiko akun diambil alih walau password bocor
- menambah verifikasi kepemilikan email atau nomor WhatsApp
- cocok untuk akun admin, operator, atau user dengan data sensitif

---

## 2. Konteks project ini

Project ini sudah punya fondasi yang relevan:

### 2.1 Login utama memakai Fortify

Fortify aktif pada:

- `config/fortify.php:18` → guard `web`
- `config/fortify.php:48` → username login adalah `email`
- `config/fortify.php:76` → redirect default ke `/dashboard`
- `app/Providers/FortifyServiceProvider.php:48` → view login sudah didaftarkan

Artinya, autentikasi awal email + password sudah ada dan OTP bisa ditempatkan sebagai **step lanjutan setelah login password sukses**.

### 2.2 Kanal email sudah siap dipakai via mailer SMTP Laravel

Konfigurasi SMTP project sudah memakai mailer `smtp`:

- `config/mail.php:17`
- `config/mail.php:40`
- `config/mail.php:113`

Untuk integrasi Brevo, acuan teknis ada di `panduan-smtp-brevo-laravel.md` dengan host umum:

- `smtp-relay.brevo.com`
- port `587`
- login SMTP Brevo
- SMTP key Brevo

### 2.3 Kanal WhatsApp sudah punya konfigurasi gateway

Project ini juga sudah punya konfigurasi gateway WhatsApp:

- `config/whatsapp.php:4` → `WHATSAPP_AUTH`
- `config/whatsapp.php:5` → `WHATSAPP_IP`
- `config/whatsapp.php:6` → `WHATSAPP_PORT`
- `config/whatsapp.php:7` → `WHATSAPP_DEVICE_ID`
- `config/whatsapp.php:8` → `WHATSAPP_ACTION`
- `config/whatsapp.php:9` → `WHATSAPP_DURATION`

Acuan API dan format request ada di `WHATSAPPGATEWAY.md`.

---

## 3. Rekomendasi arsitektur OTP

Untuk use case ini, pola paling aman dan rapi adalah:

### 3.1 Password tetap jadi faktor pertama

User tetap login biasa lewat Fortify menggunakan:

- email
- password

### 3.2 OTP jadi faktor kedua

Setelah password valid, sistem membuat status **“login belum final”** lalu mengirim OTP ke salah satu channel:

- email
- WhatsApp

### 3.3 Akses penuh ditahan dulu

Sebelum OTP lolos, user hanya boleh mengakses:

- halaman input OTP
- halaman kirim ulang OTP
- endpoint logout

Route lain seperti dashboard, data master, atau halaman sensitif harus ditahan oleh middleware khusus.

---

## 4. Flow login yang disarankan

## 4.1 Urutan proses

1. User submit form login email + password.
2. Fortify memvalidasi kredensial.
3. Jika gagal, user tetap di halaman login seperti biasa.
4. Jika sukses, sistem cek apakah akun wajib OTP.
5. Jika wajib OTP:
   - buat OTP acak
   - simpan hash OTP + waktu expired
   - simpan status session `pending_otp`
   - kirim OTP ke email atau WhatsApp
   - redirect ke halaman verifikasi OTP
6. User input OTP.
7. Sistem cocokkan OTP.
8. Jika valid:
   - hapus status `pending_otp`
   - tandai login sudah terverifikasi
   - arahkan ke `/dashboard`
9. Jika tidak valid atau expired, tampilkan error dan beri opsi kirim ulang.

## 4.2 Prinsip penting

- **Jangan** simpan OTP mentah di database bila tidak perlu.
- Simpan **hash OTP** seperti menyimpan password sementara.
- OTP harus punya **masa berlaku pendek**.
- OTP harus dibatasi percobaan inputnya.
- OTP lama harus dinonaktifkan saat OTP baru dibuat.

---

## 5. Pilihan model data

Ada dua pola umum.

### Opsi A — kolom langsung di tabel `users`

Contoh field:

- `login_otp_code_hash`
- `login_otp_expires_at`
- `login_otp_channel`
- `login_otp_sent_to`
- `login_otp_attempts`
- `login_otp_verified_at`

Cocok bila:

- flow sederhana
- satu OTP aktif per user
- tidak perlu histori detail

### Opsi B — tabel terpisah misalnya `login_otp_challenges`

Contoh field:

- `user_id`
- `channel` (`email` / `whatsapp`)
- `destination`
- `code_hash`
- `expired_at`
- `verified_at`
- `attempts`
- `max_attempts`
- `sent_at`
- `last_sent_at`

Cocok bila:

- ingin histori login challenge
- ingin audit trail
- ingin dukung resend, revoke, atau multi-device session

### Rekomendasi

Untuk aplikasi yang ingin lebih aman dan mudah diaudit, pilih **tabel terpisah**.

---

## 6. Struktur state session yang disarankan

Saat password valid, jangan anggap proses login final sebelum OTP lolos. Simpan state session khusus, misalnya:

```php
session([
    'auth.pending_otp_user_id' => $user->id,
    'auth.pending_otp_channel' => 'email',
    'auth.pending_otp_passed' => false,
]);
```

Setelah OTP valid:

```php
session([
    'auth.pending_otp_passed' => true,
]);
```

Lalu hapus state sementara yang tidak perlu.

### Tujuan state ini

- membedakan user yang baru lolos password vs user yang sudah lolos OTP
- memudahkan middleware memblok akses route sensitif
- mencegah bypass langsung ke `/dashboard`

---

## 7. Rekomendasi penempatan logika di Laravel

## 7.1 Saat login sukses

Titik integrasi umum:

- kustomisasi response login Fortify
- atau kustomisasi pipeline autentikasi Fortify
- atau intercept setelah autentikasi sukses lalu redirect ke halaman OTP

Karena project ini memakai Fortify, pendekatan paling rapi adalah membuat flow login sukses menjadi:

- jika user **tidak wajib OTP** → redirect biasa ke `config('fortify.home')`
- jika user **wajib OTP** → redirect ke halaman verifikasi OTP

## 7.2 Halaman verifikasi OTP

Buat halaman/form khusus untuk:

- input OTP
- kirim ulang OTP
- ubah channel bila bisnis mengizinkan
- logout

## 7.3 Middleware route sensitif

Buat middleware misalnya `EnsureLoginOtpVerified` untuk memastikan:

- user sudah login
- session `pending_otp_passed` bernilai `true`

Jika belum, redirect ke halaman verifikasi OTP.

---

## 8. Rekomendasi aturan bisnis OTP

Gunakan aturan seperti ini:

- panjang OTP: **6 digit**
- expiry: **5 menit**
- resend cooldown: **60 detik**
- max resend: **3–5 kali** per challenge
- max input salah: **5 kali**
- OTP baru membatalkan OTP lama
- OTP hanya berlaku untuk **1 sesi login aktif**

### Tambahan keamanan

- simpan IP dan user-agent saat challenge dibuat
- cocokkan saat verifikasi bila ingin lebih ketat
- catat event sukses/gagal OTP di log atau tabel audit

---

## 9. Implementasi channel email

## 9.1 Dasar konfigurasi

Berdasarkan `panduan-smtp-brevo-laravel.md`, email OTP memanfaatkan konfigurasi `.env` berikut:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-login@smtp-brevo.com
MAIL_PASSWORD=your-smtp-key
MAIL_SCHEME=tls
MAIL_EHLO_DOMAIN=localhost
MAIL_FROM_ADDRESS=no-reply@contohdomain.com
MAIL_FROM_NAME="B2 Dev"
```

Mapping config project ini berada di:

- `config/mail.php:17`
- `config/mail.php:40`
- `config/mail.php:113`

## 9.2 Isi email OTP yang disarankan

Subjek contoh:

```text
Kode OTP Login Akun Anda
```

Isi minimum:

- kode OTP
- masa berlaku OTP
- info bahwa OTP dipicu setelah login sukses
- instruksi abaikan bila bukan user yang bersangkutan

Contoh isi:

```text
Kode OTP login Anda: 482193
Kode berlaku 5 menit.
Jika Anda tidak merasa baru login, segera ubah password akun Anda.
```

## 9.3 Rekomendasi teknis email

- gunakan **Mailable** atau **Notification** Laravel
- antrikan pengiriman bila trafik tinggi
- jangan tampilkan email penuh di UI; masking lebih aman

Contoh masking:

```text
f***@contohdomain.com
```

---

## 10. Implementasi channel WhatsApp

## 10.1 Dasar konfigurasi

Berdasarkan `WHATSAPPGATEWAY.md`, gateway memakai Basic Auth dan endpoint:

```http
POST /send/message
```

Konfigurasi project ini diambil dari `.env` via `config/whatsapp.php`:

```env
WHATSAPP_AUTH=username:password
WHATSAPP_IP=127.0.0.1
WHATSAPP_PORT=3000
WHATSAPP_DEVICE_ID=628123456789@s.whatsapp.net
WHATSAPP_ACTION=stop
WHATSAPP_DURATION=86400
```

## 10.2 Header penting request

Menurut referensi `WHATSAPPGATEWAY.md`, header utama:

- `Authorization: Basic ...`
- `Content-Type: application/json`
- `X-Device-Id: <device-id>`

## 10.3 Body request OTP yang disarankan

Contoh payload mengikuti format gateway:

```json
{
  "phone": "6281310307754@s.whatsapp.net",
  "message": "Kode OTP login Anda: 482193. Berlaku 5 menit. Jangan bagikan kode ini.",
  "reply_message_id": "",
  "is_forwarded": false,
  "action": "stop",
  "duration": 86400
}
```

## 10.4 Format nomor tujuan

Mengacu referensi gateway, gunakan format JID penuh:

- personal: `6281310307754@s.whatsapp.net`
- group: `<group_id>@g.us`

Untuk OTP login, gunakan **personal chat**, bukan group.

## 10.5 Rekomendasi teknis WhatsApp

- normalisasi nomor user ke format `628xxx`
- konversi ke JID `628xxx@s.whatsapp.net`
- simpan nomor yang sudah diverifikasi user
- jangan kirim OTP ke nomor yang belum pernah diverifikasi kepemilikannya

---

## 11. Strategi pemilihan channel OTP

Ada beberapa model.

### Opsi 1 — selalu email

Cocok bila:

- semua user pasti punya email aktif
- implementasi awal ingin cepat

### Opsi 2 — selalu WhatsApp

Cocok bila:

- semua user punya nomor WhatsApp valid
- pengiriman ingin lebih cepat dan terbaca

### Opsi 3 — user punya preferensi channel

Contoh field user:

- `otp_channel_preference = email`
- `otp_channel_preference = whatsapp`

### Opsi 4 — failover channel

Pola:

1. kirim ke email dulu
2. bila gagal, kirim ke WhatsApp
3. atau sebaliknya sesuai kebutuhan bisnis

### Rekomendasi

Untuk tahap awal, paling stabil:

- **default email**
- **WhatsApp sebagai opsi tambahan atau fallback**

---

## 12. UX flow yang disarankan

Setelah password valid, tampilkan halaman seperti ini:

```text
Login berhasil. Masukkan kode OTP yang baru kami kirim ke email Anda.
```

Atau bila channel WhatsApp:

```text
Login berhasil. Masukkan kode OTP yang baru kami kirim ke WhatsApp Anda.
```

Elemen UI minimum:

- input OTP 6 digit
- tombol verifikasi
- tombol kirim ulang OTP
- info countdown expired
- info masking tujuan kirim
- tombol logout

Contoh masking tujuan:

- Email: `f***@contohdomain.com`
- WhatsApp: `62812****754`

---

## 13. Contoh pseudocode flow backend

## 13.1 Setelah password valid

```php
if ($credentialsAreValid) {
    $otp = random_int(100000, 999999);

    $challenge = createOtpChallenge([
        'user_id' => $user->id,
        'channel' => $channel,
        'destination' => $destination,
        'code_hash' => Hash::make((string) $otp),
        'expired_at' => now()->addMinutes(5),
        'attempts' => 0,
    ]);

    session([
        'auth.pending_otp_user_id' => $user->id,
        'auth.pending_otp_challenge_id' => $challenge->id,
        'auth.pending_otp_passed' => false,
    ]);

    sendOtpToChannel($channel, $destination, $otp);

    return redirect()->route('otp.challenge');
}
```

## 13.2 Saat verifikasi OTP

```php
$challenge = findPendingChallengeFromSession();

if (! $challenge || $challenge->expired_at->isPast()) {
    return otpExpiredResponse();
}

if ($challenge->attempts >= 5) {
    return otpLockedResponse();
}

incrementAttempts($challenge);

if (! Hash::check($request->otp, $challenge->code_hash)) {
    return invalidOtpResponse();
}

markChallengeVerified($challenge);

session([
    'auth.pending_otp_passed' => true,
]);

clearPendingOtpSessionMetadata();

return redirect()->intended('/dashboard');
```

---

## 14. Middleware proteksi route

Tambahkan middleware khusus pada route yang butuh akses penuh.

Logika minimum middleware:

1. jika guest → redirect login
2. jika authenticated tapi `pending_otp_passed !== true` → redirect ke halaman OTP
3. jika OTP sudah lolos → lanjut

Pola ini penting karena pada project ini redirect home Fortify default ada di:

- `config/fortify.php:76`

Jadi tanpa middleware tambahan, user yang lolos password bisa langsung ke dashboard.

---

## 15. Integrasi dengan fitur Fortify bawaan

Project ini sudah mengaktifkan fitur berikut di `config/fortify.php:163`:

- registration
- reset password
- email verification
- two-factor authentication
- passkeys

### Hal penting

Fortify **two-factor authentication** bawaan umumnya mengarah ke **TOTP app authenticator**, bukan OTP sekali pakai via email atau WhatsApp.

Artinya:

- jika ingin **OTP email/WhatsApp setelah login password sukses**, Anda biasanya perlu membuat **flow custom** sendiri
- flow ini bisa hidup berdampingan dengan Fortify
- bila perlu, OTP email/WhatsApp bisa dijadikan **step-up auth** untuk user tertentu, sedangkan TOTP Fortify tetap tersedia untuk user level lebih tinggi

### Rekomendasi strategi

- **TOTP Fortify** untuk user yang siap memakai authenticator app
- **OTP email/WhatsApp** untuk onboarding awal atau akun yang belum mengaktifkan TOTP

---

## 16. Validasi dan rate limit

Tambahkan proteksi berikut:

### Saat kirim OTP

- batasi resend per menit
- batasi total resend per sesi login
- log request resend

### Saat input OTP

- batasi percobaan salah
- lock challenge jika terlalu banyak salah
- jika perlu, logout user dari sesi pending OTP

### Saat login awal

Project ini sudah punya rate limiter login Fortify pada area berikut:

- `app/Providers/FortifyServiceProvider.php:66`

OTP sebaiknya punya limiter terpisah untuk:

- submit OTP
- resend OTP

---

## 17. Logging dan audit

Catat event penting seperti:

- password login sukses
- OTP dibuat
- OTP terkirim ke channel tertentu
- OTP gagal kirim
- OTP salah
- OTP expired
- OTP berhasil diverifikasi

Jangan simpan OTP mentah di log.

Yang aman dicatat:

- user ID
- channel
- tujuan tersamarkan
- timestamp
- IP
- user-agent
- status sukses/gagal

---

## 18. Troubleshooting

## 18.1 OTP email tidak terkirim

Checklist:

1. cek `.env` mail sesuai `panduan-smtp-brevo-laravel.md`
2. cek sender Brevo sudah verified
3. jalankan:

```powershell
php artisan config:clear
php artisan cache:clear
```

4. bila perlu cache ulang:

```powershell
php artisan config:cache
```

5. cek log aplikasi dan dashboard Brevo

Gejala umum:

- `535 Authentication failed`
- timeout SMTP
- sender belum verified

## 18.2 OTP WhatsApp gagal terkirim

Checklist:

1. cek `WHATSAPP_AUTH`
2. cek `WHATSAPP_IP`
3. cek `WHATSAPP_PORT`
4. cek `WHATSAPP_DEVICE_ID`
5. pastikan service gateway aktif
6. pastikan device WhatsApp sudah connected
7. pastikan nomor tujuan dalam format `628xxx@s.whatsapp.net`

## 18.3 User selalu kembali ke halaman OTP

Penyebab umum:

- session `pending_otp_passed` tidak pernah di-set `true`
- session dibersihkan terlalu cepat
- middleware route membaca key session berbeda
- challenge sudah expired tapi UI tidak memberi pesan jelas

## 18.4 User lolos password lalu langsung masuk dashboard tanpa OTP

Penyebab umum:

- redirect login tidak dioverride
- middleware OTP belum dipasang di route terlindungi
- state session pending OTP tidak dibuat setelah login sukses

---

## 19. Rekomendasi produksi

Untuk production, pakai praktik ini:

1. OTP 6 digit, expiry 5 menit.
2. Simpan OTP dalam bentuk hash.
3. Gunakan queue untuk pengiriman email/WhatsApp bila trafik tinggi.
4. Aktifkan monitoring pengiriman Brevo dan gateway WhatsApp.
5. Pastikan nomor WhatsApp user sudah diverifikasi saat onboarding.
6. Masking email dan nomor di UI.
7. Tambahkan audit trail login dan OTP.
8. Batasi resend dan attempt.
9. Hapus challenge yang sudah verified atau expired secara berkala.
10. Untuk akun sangat sensitif, pertimbangkan TOTP Fortify atau passkeys sebagai layer lebih kuat.

---

## 20. Checklist implementasi cepat

1. Pastikan login email + password Fortify berjalan normal.
2. Pastikan SMTP Brevo aktif sesuai `panduan-smtp-brevo-laravel.md`.
3. Pastikan WhatsApp Gateway aktif sesuai `WHATSAPPGATEWAY.md`.
4. Tentukan channel OTP: email, WhatsApp, atau preference user.
5. Buat penyimpanan challenge OTP.
6. Setelah login sukses, generate OTP dan kirim ke channel terpilih.
7. Simpan state session `pending_otp`.
8. Buat halaman input OTP.
9. Buat endpoint verifikasi OTP.
10. Buat middleware untuk menahan akses sebelum OTP lolos.
11. Tambahkan resend, expiry, attempt limit, dan logging.
12. Uji flow sukses, flow OTP salah, flow expired, flow resend, dan flow logout.

---

## 21. Ringkasan akhir

Untuk menambah keamanan akun, flow paling tepat adalah:

1. user login dengan email + password
2. sistem validasi kredensial utama
3. sistem kirim OTP ke email atau WhatsApp
4. user baru mendapat akses penuh setelah OTP valid

Pada project ini, fondasi integrasi sudah tersedia:

- email lewat SMTP Laravel + Brevo dari `panduan-smtp-brevo-laravel.md`
- WhatsApp lewat gateway dari `WHATSAPPGATEWAY.md`
- login utama lewat Fortify pada `config/fortify.php`

Jika ingin pola yang aman, audit-friendly, dan mudah dikembangkan, gunakan:

- challenge OTP terpisah
- session `pending_otp`
- middleware proteksi route
- rate limit resend dan verify
- hash OTP, bukan plaintext
