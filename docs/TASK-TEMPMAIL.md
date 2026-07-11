# TASK-TEMPMAIL

Step-by-step coding task untuk membangun fitur **Temporary Email Service** sesuai blueprint `TEMPMAIL.md`. Disusun mengikuti stack Laravel 13 + Livewire 4 + Tailwind 4 + Flux UI + SQLite/PostgreSQL.

Konvensi penamaan mengikuti `CLAUDE.md`: page-based anonymous Livewire di `resources/views/pages/{feature}/⚡{name}.blade.php`, route via `Route::livewire(...)`, model + factory + migration lewat `php artisan make:`. Setiap selesai satu tugas, jalankan test terkait + `vendor/bin/pint --dirty --format agent`.

---

## Tahap 0 — Persiapan & Fondasi

- [ ] 0.1 Buat branch baru: `git checkout -b feature/tempmail`
- [ ] 0.2 Tambah konfigurasi di `.env` / `config/`:
  - `TEMPMAIL_DEFAULT_TTL_MIN=60`
  - `TEMPMAIL_MAX_TTL_MIN=1440` (24 jam, untuk anonim)
  - `TEMPMAIL_AUTHED_DEFAULT_TTL_MIN=1440` (24 jam default untuk authed)
  - `TEMPMAIL_AUTHED_MAX_TTL_MIN=10080` (7 hari, untuk authed)
  - `TEMPMAIL_ALIAS_MIN_LEN=12`, `TEMPMAIL_ALIAS_MAX_LEN=16`
  - `TEMPMAIL_INBOX_PER_IP_HOUR=5`
  - `TEMPMAIL_READ_PER_TOKEN_MIN=100`
  - `TEMPMAIL_INBOUND_RATE_PER_SEC=500`
  - `TEMPMAIL_MAX_MESSAGE_SIZE_KB=10240` (10MB)
  - `TEMPMAIL_MAX_MESSAGES_PER_INBOX=50`
  - `TEMPMAIL_MAIL_SECRET` (shared secret untuk `/mail/inbound`)
  - `TEMPMAIL_SOFT_EXPIRE_MIN=10`
  - `TEMPMAIL_LOG_RETENTION_DAYS=30`
  - Daftar domain sistem: `TEMPMAIL_DOMAINS` (comma-separated)
- [ ] 0.3 Buat `config/tempmail.php` yang membaca semua env di atas.
- [ ] 0.4 Tambah koneksi/region storage untuk payload email (gunakan disk `r2`/`b2` yang sudah ada) → simpan body besar & attachment di object storage, bukan DB.
- [ ] 0.5 Pastikan `QUEUE_CONNECTION=database` untuk dev (atau `sync` saat test) dan queue worker mendengarkan `tempmail` queue: tambahkan catatan di README bahwa worker perlu `php artisan queue:work --queue=tempmail,otp,default`.
- [ ] 0.6 Setup Redis (atau fallback cache `database`) untuk cache inbox lookup & rate limiter.

---

## Tahap 1 — MVP (Lihat §14.1 TEMPMAIL.md)

### 1. Entitas Data

- [ ] 1.1 Buat model + migration + factory `Inbox`:
  `php artisan make:model Inbox -mf`
  Field: `id` (UUIDv4 PK), `address` (VARCHAR unique index), `local_part` (VARCHAR), `domain` (VARCHAR — nama domain langsung, bukan FK), `status` (active/expired/purged, index), `access_token_hash` (VARCHAR), `created_at`, `expires_at` (TIMESTAMP, index), `last_accessed_at`, `source_ip_hash`, `is_authenticated` (bool, default false), `user_id` (nullable FK).
- [ ] 1.2 Buat model + migration + factory `Message`:
  `php artisan make:model Message -mf`
  Field: `id` (UUID PK), `inbox_id` (FK, index), `message_id_header` (VARCHAR nullable, unique index untuk dedup), `from_address`, `to_address`, `subject`, `preview` (TEXT), `body_text` (LONGTEXT nullable), `body_html` (LONGTEXT nullable), `body_storage_key` (VARCHAR nullable — object storage key), `received_at` (TIMESTAMP, composite index `idx_messages_inbox_received` bersama `inbox_id`), `size_bytes` (INT), `spam_score` (DECIMAL nullable), `has_attachments` (bool), `is_read` (bool default false).
- [ ] 1.3 Buat model + migration + factory `Attachment`:
  `php artisan make:model Attachment -mf`
  Field: `id` (UUID PK), `message_id` (FK, index), `filename`, `content_type`, `storage_key`, `size_bytes`, `expires_at` (TIMESTAMP, index).
- [ ] 1.4 Buat migration `audit_logs`: `id`, `inbox_id` (nullable), `message_id` (nullable), `actor` (system/ingress/user), `action`, `ip_hash`, `metadata` (JSON), `created_at`. Digunakan untuk log: create inbox, inbound accepted/rejected, baca detail, cleanup/purge, queue failure & parse error.
- [ ] 1.5 Buat migration `abuse_events`: `id`, `source_ip_hash`, `kind` (flood/spam/enum-abuse), `payload` (JSON), `created_at`.
- [ ] 1.6 Pastikan `$casts` di semua model untuk UUID (`uuid`), `expires_at` (datetime), `metadata` (array), `is_read`/`is_authenticated`/`has_attachments` (boolean).

### 2. Service Layer

- [ ] 2.1 `App\Services\TempMail\AliasGenerator`: CSPRNG (`random_bytes`) 12-16 char lowercase alphanumerik, validasi RFC 5322 regex, cek unik di DB.
- [ ] 2.2 `App\Services\TempMail\InboxService`:
  - `create(string $ip, ?User $user, ?string $domain): Inbox` — pilih domain dari `TEMPMAIL_DOMAINS`, buat alias, set TTL sesuai authed/anonim.
  - `refresh(Inbox $inbox): Inbox` — perpanjang TTL max 60 menit/call, total max lifetime 24 jam (anonim) / 7 hari (authed).
  - `purge(Inbox $inbox): void`
  - `expire(Inbox $inbox): void`
- [ ] 2.3 `App\Services\TempMail\TokenService`: generate plaintext token (`random_bytes`), hash SHA-256+salt, verify.
- [ ] 2.4 `App\Services\TempMail\MessageProcessor`: parse MIME (gunakan library PHP yang tersedia), ekstrak header/body/attachment metadata, sanitasi HTML, simpan payload ke object storage bila body besar.
- [ ] 2.5 `App\Services\TempMail\HtmlSanitizer`: strip script/event handler, rewrite link, blokir remote images, output HTML aman untuk render di iframe sandbox.
- [ ] 2.6 `App\Services\TempMail\RateLimiter`: wrap `RateLimiter::attempt()` untuk inbox creation (5/IP/jam) & read (100/token/menit). Tambah rate limit inbound (500/detik/domain) untuk `/mail/inbound`.

### 3. Jobs & Workers

- [ ] 3.1 `App\Jobs\ProcessInboundEmail` (queue `tempmail`): terima raw email dari ingress, validasi recipient (inbox aktif), dedup by `Message-ID`, parse via `MessageProcessor`, simpan, dispatch `ExpireInboxJob` jika perlu.
- [ ] 3.2 `App\Jobs\ExpireInboxJob`: cari inbox yang `expires_at` sudah lewat & status masih `active`, tandai `expired` (soft-expire). Idempotent.
- [ ] 3.3 `App\Jobs\PurgeInboxJob`: cari inbox `expired` yang sudah melewati grace period 10 menit (`TEMPMAIL_SOFT_EXPIRE_MIN`), hard-delete inbox + messages + attachments + hapus cache. Idempotent, retry max 3, rollback transaksi bila gagal.
- [ ] 3.4 Daftarkan scheduler di `routes/console.php`:
  - `Schedule::job(new ExpireInboxJob)->everyMinute()`
  - `Schedule::job(new PurgeInboxJob)->everyFiveMinutes()`
- [ ] 3.5 Pastikan cleanup menyentuh semua storage turunan: DB rows, Redis cache keys, object storage files.

### 4. API Endpoints (§6.1)

- [ ] 4.1 Buat Form Requests: `StoreInboxRequest`, `RefreshInboxRequest`, `StoreInboundMailRequest` (validasi header `X-Mail-Secret`).
- [ ] 4.2 Buat API Resources: `InboxResource`, `MessageResource`, `AttachmentResource` sesuai kontrak §6.3: `inbox_id`, `address`, `domain`, `expires_at`, `status`, `messages_count`, `latest_message_at`.
- [ ] 4.3 Controller `App\Http\Controllers\TempMail\InboxController`:
  - `POST /api/tempmail/inboxes` (rate limited 5/IP/jam, `Idempotency-Key` header untuk cegah duplikat)
  - `GET /api/tempmail/inboxes/{id}` (verifikasi token)
  - `GET /api/tempmail/inboxes/{id}/messages` (paginated, max 50)
  - `GET /api/tempmail/inboxes/{id}/messages/{messageId}`
  - `DELETE /api/tempmail/inboxes/{id}`
  - `POST /api/tempmail/inboxes/{id}/refresh`
- [ ] 4.4 Controller `App\Http\Controllers\TempMail\MailInboundController`:
  - `POST /api/tempmail/mail/inbound` (auth `X-Mail-Secret` atau mTLS, rate limit 500 inbound/detik/domain, dispatch `ProcessInboundEmail`)
  - `POST /api/tempmail/mail/bounce` (klasifikasi hard/soft bounce, suppression list)
- [ ] 4.5 Buat `routes/tempmail.php` dengan prefix `api/tempmail` + middleware throttle. Daftarkan di `bootstrap/app.php`.
- [ ] 4.6 Middleware `App\Http\Middleware\VerifyInboxAccessToken`: baca bearer / header `X-Inbox-Token`, hash SHA-256, bandingkan dengan `access_token_hash` di DB.
- [ ] 4.7 Middleware throttle terpisah: `tempmail-client` (untuk InboxController) dan `tempmail-inbound` (untuk MailInboundController).

### 5. Frontend (Livewire + Flux)

- [ ] 5.1 Halaman inbox: `resources/views/pages/tempmail/⚡index.blade.php` (anonymous Livewire, `#[Layout('layouts.app')]`):
  - Alamat ditampilkan **monospaced**, tombol "Buat alamat baru", "Salin alamat"
  - Daftar pesan terbaru dengan preview, label status pesan (read/unread)
  - Indikator waktu tersisa (Alpine `x-data` countdown timer ke `expires_at`)
  - Label status inbox yang jelas: aktif / expired / purged
  - State kosong (belum ada email), state expired (dengan tombol generate ulang)
  - Polling `wire:poll.10s` untuk refresh daftar pesan
- [ ] 5.2 Halaman detail pesan: `resources/views/pages/tempmail/⚡show.blade.php`:
  - Render HTML di **iframe sandbox** (`sandbox="allow-same-origin"`, CSP ketat)
  - Tampilkan metadata: from, subject, received_at, size
  - Daftar attachment (nama file, ukuran, tombol download via temporary URL)
  - Banner anti-phishing untuk email dari pengirim eksternal
- [ ] 5.3 Tambahkan route:
  - `Route::livewire('/tempmail', 'pages::tempmail.index')`
  - `Route::livewire('/tempmail/{messageId}', 'pages::tempmail.show')`
- [ ] 5.4 Tambah menu sidebar ke `resources/views/layouts/app/sidebar.blade.php`.
- [ ] 5.5 Tambahkan meta `<meta name="robots" content="noindex, nofollow">` di layout halaman tempmail + entry di `robots.txt`.

### 6. Test MVP (wajib per §test rules)

- [ ] 6.1 `tests/Feature/TempMail/CreateInboxTest.php`: sukses buat inbox, alamat unik, TTL default 60 menit, token plaintext dikembalikan sekali & disimpan hash SHA-256, tidak bisa recover token yang hilang.
- [ ] 6.2 `tests/Feature/TempMail/RateLimitTest.php`: tepat 5 inbox/IP/jam berhasil, percobaan ke-6 ditolak 429.
- [ ] 6.3 `tests/Feature/TempMail/InboundMailTest.php`: `/mail/inbound` sukses simpan pesan, dedup `Message-ID` (pesan sama tidak duplikat), reject secret salah (401), reject unknown recipient (422).
- [ ] 6.4 `tests/Feature/TempMail/ReadMessagesTest.php`: akses inbox tanpa token ditolak (401), token salah ditolak (403), pagination max 50, baca detail pesan berhasil, `is_read` diupdate setelah dibaca.
- [ ] 6.5 `tests/Feature/TempMail/RefreshInboxTest.php`: refresh perpanjang max 60 menit/call, total max 24 jam untuk anonim, total max 7 hari untuk authed.
- [ ] 6.6 `tests/Feature/TempMail/ExpirationTest.php`: `ExpireInboxJob` menandai expired, `PurgeInboxJob` hard-delete setelah grace period, idempotent (run dua kali tidak error), cleanup object storage dipanggil.
- [ ] 6.7 `tests/Unit/TempMail/HtmlSanitizerTest.php`: script tag di-strip, event handler `onclick` di-strip, external link di-rewrite/diblokir, remote image src diblokir.
- [ ] 6.8 Factory untuk semua model (`Inbox`, `Message`, `Attachment`) sudah lengkap dengan state yang diperlukan (expired, purged, dengan attachment, dll).

---

## Tahap 2 — Reliability (§14.2)

### 7. Real-time & Multi-domain

- [ ] 7.1 Implementasi SSE endpoint `GET /api/tempmail/inboxes/{id}/stream`:
  - Event `message.received`, payload JSON: `{"inbox_id":"uuid","message_id":"uuid","received_at":"timestamp","subject":"string","from":"string"}`
  - Keep-alive ping tiap 30 detik
  - Hentikan stream saat inbox expired
- [ ] 7.2 Frontend: ganti polling `wire:poll` → SSE via `EventSource` (Alpine), fallback ke polling bila SSE gagal.
- [ ] 7.3 Multi-domain support: simpan daftar domain aktif di DB atau config, round-robin domain saat create inbox.

### 8. Anti-abuse & Security Hardening

- [ ] 8.1 Abuse detection: greylisting/tarpitting, karantina pesan mencurigakan sebelum tampil ke user.
- [ ] 8.2 Attachment scanning: integrasi ClamAV / external API, quarantine bila positif, update field `scan_result` di tabel `Attachment`.
- [ ] 8.3 CSP header `X-Content-Type-Options: nosniff` + `Content-Disposition: attachment` untuk download attachment.
- [ ] 8.4 Enkripsi at-rest untuk kolom sensitif (`access_token_hash`, `source_ip_hash`) bila driver mendukung (Laravel `encrypted` cast atau DB-level encryption).
- [ ] 8.5 Captcha (Turnstile/hCaptcha) pada create inbox bila pola abuse terdeteksi.

### 9. Attachment Handling

- [ ] 9.1 Upload attachment ke object storage subdirectory `tempmail/attachments/{inbox_id}/{message_id}/`.
- [ ] 9.2 Temporary URL untuk download (`Storage::disk('b2')->temporaryUrl()`), masa berlaku URL max 30 menit.
- [ ] 9.3 Attachment di-delete 30 menit setelah inbox expire (lebih ketat dari body); `PurgeInboxJob` menangani ini.

---

## Tahap 3 — Scale & Enterprise (§14.3)

- [ ] 10.1 Custom domain onboarding: admin tambah domain, validasi TXT challenge, health check MX/SPF/DKIM/DMARC, alert jika salah konfigurasi, fallback ke domain sistem.
- [ ] 10.2 Custom domain dashboard: status DNS, health check, reputasi, MX alert.
- [ ] 10.3 Multi-tenant quota per domain.
- [ ] 10.4 Observability lengkap: metrics (inbox created/min, email received/domain, rejection rate, processing latency, cleanup success rate, cache hit rate), alerting (lonjakan create, MX down, queue backlog, spam surge, cleanup fail).
- [ ] 10.5 Health check endpoint `/health`: cek DB, Redis, Queue, status DNS MX domain aktif.
- [ ] 10.6 Retention policy granular per domain.
- [ ] 10.7 DMARC RUA aggregate report processing.
- [ ] 10.8 Feedback Loop (FBL) / spam complaint processing.
- [ ] 10.9 Outbound reply/forward (opsional): DKIM signing per domain, IP warmup, STARTTLS enforcement.

---

## Cross-cutting (lakukan sepanjang tahap)

- [ ] C.1 Legal & compliance: ToS + AUP, halaman `abuse@`/`postmaster@` (RFC 2142), disclaimer "mere conduit", pemisahan log vs payload.
- [ ] C.2 Dokumentasi API: dokumentasikan TTL, ukuran pesan, retensi. (Buat dokumen hanya bila diminta user.)
- [ ] C.3 PHPStan: `php artisan types:check` setelah service layer selesai.
- [ ] C.4 Pint: `vendor/bin/pint --dirty --format agent` setiap habis edit PHP.
- [ ] C.5 Setelah tiap tugas: `php artisan test --compact --filter=<TestName>`.
- [ ] C.6 Saat seluruh fitur pass: tanyakan user untuk run full test suite (`php artisan test --compact`).

---

## Definisi Selesai (MVP)

MVP dianggap selesai ketika semua berikut pass:
1. User bisa buat alamat temp-mail anonim tanpa login.
2. Email masuk ke domain sistem tersimpan & muncul di inbox (via `/mail/inbound`).
3. Inbox bisa dibaca lewat token, ada pagination (max 50).
4. TTL & cleanup (soft-expire 10 menit + hard-delete) bekerja otomatis.
5. Rate limit (5/IP/jam create, 100/menit/token read, 500/detik/domain inbound) + auth secret + token hash aktif.
6. HTML email dirender aman (sanitized + iframe sandbox) dengan banner anti-phishing.
7. Semua test di §6 hijau.

Status Tahap 2 & 3 bersifat roadmap — implementasi setelah MVP stabil.
