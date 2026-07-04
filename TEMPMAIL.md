# TEMPMAIL

## 1. Gambaran Umum dan Tujuan

Temporary email service adalah fitur yang menghasilkan alamat email sementara dengan inbox aktif pada domain yang dikelola sistem. Tujuan utamanya:

- memberi alamat email instan tanpa proses registrasi panjang
- menerima email masuk secara real-time atau near real-time
- membatasi umur inbox untuk mengurangi penyimpanan dan risiko abuse
- menjaga isolasi antara identitas pengguna, inbox, dan domain sistem
- menyediakan API dan antarmuka yang cukup fleksibel untuk integrasi produk lain

Referensi perilaku pengguna dapat meniru pola layanan seperti temp-mail.org/en: pengguna bisa membuat alamat cepat, melihat inbox masuk, dan membiarkan alamat kadaluarsa otomatis. Implementasi internal harus dirancang ulang dari nol, tidak menyalin infrastruktur atau kode platform mana pun.

## 2. Prinsip Desain

- **Instan**: pembuatan alamat harus cepat, idealnya tanpa login.
- **Disposable**: inbox hidup hanya selama jendela waktu tertentu.
- **Terisolasi**: setiap inbox terikat ke token atau identitas sesi, bukan akun permanen.
- **Skalabel**: pemrosesan email masuk harus bisa menyerap lonjakan trafik.
- **Auditabel**: semua alur penting tercatat untuk keamanan dan debugging.
- **Minim data**: simpan metadata seperlunya, hindari penyimpanan isi email terlalu lama.

## 3. Arsitektur Tingkat Tinggi

### 3.1 Komponen Utama

- **Frontend / Client App**
  - menampilkan alamat sementara
  - menampilkan daftar email masuk
  - menangani refresh inbox, salin alamat, dan timer kadaluarsa

- **API Gateway / Backend App**
  - membuat inbox dan alamat email
  - mengembalikan metadata inbox
  - menyediakan endpoint daftar pesan dan detail pesan

- **Ingestion Layer Email**
  - menerima email masuk dari internet
  - memvalidasi domain dan penerima
  - meneruskan email ke queue atau parser

- **Message Processor**
  - mengekstrak header, body, attachment metadata
  - menyimpan isi email dalam format aman
  - memicu event pembaruan inbox

- **Storage Layer**
  - menyimpan metadata inbox, email, dan status hidup
  - menyimpan payload email yang dibatasi retensinya
  - bisa berupa database relasional, document store, atau kombinasi dengan object storage

- **Expiration Worker**
  - menghapus inbox dan pesan kadaluarsa
  - membersihkan attachment dan data turunan
  - menegakkan TTL

- **Abuse Detection / Rate Limiter**
  - membatasi pembuatan inbox dari sumber yang sama
  - memfilter spam, flood, dan penyalahgunaan domain

### 3.2 Alur Data Singkat

1. Pengguna meminta alamat sementara.
2. Backend membuat inbox record dengan token, domain, dan TTL.
3. Sistem mengarahkan domain email ke mail ingress.
4. Email masuk ke domain sistem.
5. Ingestion layer memetakan penerima ke inbox aktif.
6. Pesan diproses, disimpan, lalu inbox diperbarui.
7. Client mengambil daftar pesan dari API.
8. Saat TTL habis, worker menghapus inbox dan isi pesan.

## 4. Breakdown Fitur Inti

### 4.1 Generasi Alamat Email

#### Tujuan
Menciptakan alamat unik yang valid pada domain sistem-managed.

#### Desain

- format umum: `alias@domain-sistem.tld`
- alias berupa:
  - string acak alphanumerik lowercase 12-16 karakter ( entropy minimum 72-bit )
  - contoh: `k9f2j8w1m4p2@domain.tld`
- alias harus lolos validasi RFC 5322 regex
- alias tidak boleh memakai kamus kata umum agar tidak mudah ditebak

#### Pertimbangan

- gunakan generator kriptografis aman ( CSPRNG ) untuk alias
- jika sistem mendukung domain multiple, pilih domain secara round-robin atau random load-balanced
- dukung regenerasi alamat bila inbox lama habis masa aktifnya

### 4.2 Aktivasi Inbox

#### Tujuan
Mengikat alamat ke inbox aktif dengan lifetime tertentu.

#### Desain

- saat alamat dibuat, backend membuat:
  - `inbox_id` (UUIDv4)
  - `address`
  - `created_at`
  - `expires_at` (Default: 60 menit dari `created_at`)
  - `status` (active / expired / purged)
  - `access_token` (plaintext token, hanya dikembalikan sekali saat response HTTP, disimpan hash di DB)
- inbox dianggap aktif sampai TTL habis atau dihapus manual
- refresh TTL diperbolehkan memperpanjang maksimal 60 menit per call, total max lifetime 24 jam.

#### Pertimbangan

- token dikirim ke client dan disimpan di local storage client
- jika token hilang, user harus generate alamat baru (tidak ada recovery flow untuk alamat anonim demi privasi)
- token disimpan di DB menggunakan hashing bcrypt atau SHA-256 dengan salt

### 4.3 Penerimaan Email Masuk

#### Tujuan
Menerima email untuk alamat yang valid dan aktif.

#### Desain

- inbound mail server menerima SMTP untuk domain terdaftar
- recipient lookup memeriksa apakah alias cocok dengan inbox aktif
- jika cocok, pesan diteruskan ke pipeline pemrosesan
- jika inbox tidak aktif, server menolak atau menandai sebagai bounce sesuai kebijakan

#### Pertimbangan

- dukung multiple MX untuk ketersediaan tinggi
- gunakan queue agar ingestion tidak tergantung proses sinkron
- lakukan filtering dasar pada header, ukuran pesan, dan attachment

### 4.4 Pembacaan Inbox

#### Tujuan
Mengambil daftar email masuk secara aman dan cepat.

#### Desain

- endpoint daftar pesan mengembalikan metadata ringkas:
  - subject
  - from
  - received_at
  - preview
  - unread/read state
- endpoint detail pesan mengembalikan body dan attachment metadata bila diizinkan
- client bisa polling atau subscribe via event stream Server-Sent Events (SSE)
- **Kontrak Event SSE (`/inboxes/{id}/stream`)**:
  - Event type: `message.received`
  - Payload JSON: `{"inbox_id": "uuid", "message_id": "uuid", "received_at": "timestamp", "subject": "string", "from": "string"}`
  - Keep-alive ping dikirim setiap 30 detik untuk mencegah disconnection

#### Pertimbangan

- cache daftar inbox di Redis dengan TTL mengikuti `expires_at` inbox
- batasi jumlah pesan yang ditampilkan per inbox maksimal 50 pesan
- pagination wajib jika inbox bisa menerima volume besar

### 4.5 Kedaluwarsa dan Penghapusan

#### Tujuan
Menghapus inbox dan pesan sesuai TTL.

#### Desain

- semua inbox punya `expires_at`
- worker berkala menandai inbox expired
- cleanup job menghapus pesan, attachment, indeks pencarian, dan cache
- jika cleanup gagal sebagian, transaksi dirollback dan item bermasalah dimasukkan ke Failed Job Queue untuk di-retry otomatis maksimal 3 kali.

#### Pertimbangan

- gunakan soft-expire (status berubah expired, tapi data belum di-purge) selama 10 menit
- setelah grace period 10 menit lewat, lakukan hard-delete permanen
- pastikan penghapusan menyentuh semua penyimpanan turunan (DB, Redis, Object Storage)

## 5. Domain dan Manajemen Siklus Hidup Email

### 5.1 Domain System-Managed

Domain harus dikontrol penuh oleh operator sistem:

- DNS MX mengarah ke mail ingress milik sistem
- SPF, DKIM, dan DMARC dikonfigurasi untuk reputasi dan deliverability

#### Contoh Tabel DNS (Cloudflare)
| Tipe | Nama (Host) | Nilai / Target | TTL | Prioritas |
|---|---|---|---|---|
| **A** | `mx` | `203.0.113.45` *(IP publik mail-ingress)* | Auto | – |
| **MX** | `@` | `mx.tempdomain.tld` | Auto | 10 |
| **TXT** | `@` | `v=spf1 mx -all` | Auto | – |
| **TXT** | `mail._domainkey` | `v=DKIM1; k=rsa; p=MIIBIjANBgkqh...` | Auto | – |
| **CNAME** | `mail._domainkey` | `mail._domainkey.tempdomain.tld` *(opsional jika selector dilimpahkan)* | Auto | – |
| **TXT** | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc@tempdomain.tld; pct=100` | Auto | – |

- **DKIM signing**: wajib dikonfigurasi per domain menggunakan RSA-2048 atau Ed25519; jika ada fitur reply/forward outbound di masa depan, DKIM signing sudah siap sejak awal karena konfigurasi per domain diperlukan sebelum ada email outbound
- TLS aktif pada transport email masuk/keluar jika ada outbound
- monitoring reputasi domain dan blacklist perlu disiapkan

### 5.2 Siklus Hidup Inbox

1. **Created**: inbox dibuat, TTL ditetapkan.
2. **Active**: alamat menerima email.
3. **Expired**: akses diblokir atau dibatasi baca saja.
4. **Purged**: seluruh data dihapus.

### 5.3 Siklus Hidup Pesan

1. **Received**: pesan diterima oleh ingress.
2. **Parsed**: header dan body diekstrak.
3. **Stored**: metadata dan konten disimpan.
4. **Delivered to UI**: client bisa membaca.
5. **Expired/Purged**: pesan dihapus mengikuti retensi inbox.

### 5.4 Kebijakan Retensi

- inbox anonim: TTL default 60 menit, maksimal 24 jam
- inbox terautentikasi: TTL default 24 jam, maksimal 7 hari
- attachment: retensi maksimal 30 menit setelah inbox expire (lebih ketat daripada body teks)
- logs teknis: retensi 30 hari, terpisah dari payload email

## 6. API atau Service Endpoint Konseptual

### 6.1 Endpoint Inti

- `POST /inboxes`
  - buat inbox baru
  - respons: alamat, token akses, expires_at

- `GET /inboxes/{id}`
  - ambil status inbox dan metadata TTL

- `GET /inboxes/{id}/messages`
  - daftar pesan ringkas

- `GET /inboxes/{id}/messages/{messageId}`
  - detail pesan

- `DELETE /inboxes/{id}`
  - hapus inbox lebih cepat dari TTL

- `POST /inboxes/{id}/refresh`
  - perpanjang TTL jika kebijakan mengizinkan

### 6.2 Endpoint Integrasi Mail Ingress

- `POST /mail/inbound`
  - menerima hasil parsing dari mail server atau processor
  - **Autentikasi**: shared secret token di header `X-Mail-Secret` atau mTLS; token disimpan di konfigurasi server
  - **Rate limit**: maksimal 500 inbound per detik per domain

- `POST /mail/bounce`
  - memproses bounce atau kegagalan delivery jika sistem mengirim outbound
  - **Autentikasi**: sama seperti `/mail/inbound`

### 6.3 Kontrak Respons

Gunakan bentuk respons yang konsisten:

- `inbox_id`
- `address`
- `domain`
- `expires_at`
- `status`
- `messages_count`
- `latest_message_at`

## 7. Model Data Konseptual

### 7.1 Entitas Inbox

- `id` (UUIDv4, PRIMARY KEY)
- `address` (VARCHAR, UNIQUE INDEX)
- `local_part` (VARCHAR)
- `domain` (VARCHAR)
- `status` (VARCHAR, INDEX)
- `access_token_hash` (VARCHAR)
- `created_at` (TIMESTAMP)
- `expires_at` (TIMESTAMP, INDEX)
- `last_accessed_at` (TIMESTAMP)
- `source_ip_hash` (VARCHAR)

### 7.2 Entitas Message

- `id` (UUIDv4, PRIMARY KEY)
- `inbox_id` (UUIDv4, FOREIGN KEY REFERENCES inboxes.id, INDEX)
- `message_id_header` (VARCHAR)
- `from_address` (VARCHAR)
- `to_address` (VARCHAR)
- `subject` (VARCHAR)
- `preview` (TEXT)
- `body_text` (LONGTEXT)
- `body_html` (LONGTEXT)
- `received_at` (TIMESTAMP, INDEX bersama inbox_id: `idx_messages_inbox_received`)
- `size_bytes` (INTEGER)
- `spam_score` (DECIMAL)
- `has_attachments` (BOOLEAN)

### 7.3 Entitas Attachment

- `id` (UUIDv4, PRIMARY KEY)
- `message_id` (UUIDv4, FOREIGN KEY REFERENCES messages.id, INDEX)
- `filename` (VARCHAR)
- `content_type` (VARCHAR)
- `storage_key` (VARCHAR)
- `size_bytes` (INTEGER)
- `expires_at` (TIMESTAMP, INDEX)

## 8. Pemrosesan Email Masuk

### 8.1 Pipeline Rekomendasi

1. SMTP ingress menerima email (ukuran maksimum: 10MB).
2. Validasi recipient dan domain.
3. Normalisasi header dasar.
4. Deteksi ukuran dan batas attachment.
5. Parse MIME.
6. Simpan payload ke storage sementara.
7. Publish event `message.received`.
8. Update cache inbox.
9. Client mengambil update.

### 8.2 Normalisasi dan Sanitasi

- strip atau lindungi header berbahaya
- sanitasi HTML sebelum ditampilkan
- nonaktifkan eksekusi konten aktif dari email
- blokir remote images jika mode aman diaktifkan

### 8.3 Spam dan Flood Handling

- rate limit per domain, IP, dan recipient
- greylisting atau tarpitting bila diperlukan
- heuristik ukuran pesan, frekuensi, dan pola subject
- karantina pesan mencurigakan sebelum tampil ke user

## 9. Keamanan dan Anti-Abuse

### 9.1 Ancaman Utama

- pembuatan inbox massal untuk abuse
- enumerasi alias aktif
- penyalahgunaan domain untuk registrasi layanan pihak ketiga
- XSS dari HTML email
- penyimpanan data sensitif terlalu lama
- attachment berbahaya

### 9.2 Kontrol Keamanan

- token akses random, disimpan dalam bentuk hash (bcrypt/SHA-256)
- rate limit:
  - pembuatan inbox: 5 inbox per IP per jam
  - pembacaan detail inbox: 100 requests per menit per token
- captcha opsional pada create inbox untuk mencegah botting
- CSRF protection untuk endpoint browser-based
- sanitasi HTML dan rewrite link berbahaya
- anti-phishing banner untuk email eksternal
- antivirus atau file scanning untuk attachment

### 9.3 Proteksi Data

- enkripsi data at-rest bila memungkinkan
- enkripsi transport semua jalur internal
- minimalkan log payload mentah
- pisahkan metadata observability dari isi pesan
- audit akses inbox dan detail pesan

### 9.4 Kebijakan Akses

- alamat tanpa autentikasi: akses via token sesi sekali pakai atau cookie aman
- inbox premium atau custom domain: bisa memakai akun pengguna
- akses detail pesan harus diverifikasi terhadap inbox owner

## 10. Integrasi dengan Custom Domain

### 10.1 Tujuan

Mendukung domain tambahan untuk tenant, partner, atau deployment khusus.

### 10.2 Alur Integrasi

1. Admin menambahkan domain baru ke sistem.
2. Validasi kepemilikan domain dilakukan via DNS TXT atau metode serupa.
3. DNS MX diarahkan ke mail ingress sistem.
4. SPF, DKIM, dan DMARC disesuaikan.
5. Domain masuk ke daftar domain aktif.
6. Inbox baru dapat dibuat memakai domain tersebut.

### 10.3 Pertimbangan Arsitektur

- multi-tenant routing per domain
- quota per domain untuk mencegah overload
- kebijakan TTL berbeda per domain
- isolasi statistik dan audit per tenant

### 10.4 Validasi Kepemilikan

- TXT record challenge
- file-based validation tidak ideal untuk email-only service
- rotasi token verifikasi jika verifikasi gagal

### 10.5 Operasional Custom Domain

- dashboard untuk status DNS, health check, dan reputasi
- alert jika MX salah konfigurasi
- fallback domain sistem jika custom domain gagal

## 11. Frontend dan UX Layer

### 11.1 Elemen UX Inti

- tombol buat alamat baru
- tombol salin alamat
- daftar pesan terbaru
- preview pesan cepat
- indikator waktu tersisa
- refresh otomatis atau manual

### 11.2 Pola Interaksi

- real-time inbox update via WebSocket/SSE bila tersedia
- polling fallback untuk lingkungan sederhana
- state kosong yang jelas saat belum ada email masuk
- indikator expired dan kemampuan generate ulang

### 11.3 Aksesibilitas dan Kejelasan

- tampilkan format alamat secara monospaced agar mudah disalin
- beri label jelas untuk status aktif, expired, dan diblokir
- hindari UI yang menyembunyikan TTL atau batasan retensi

## 12. Observabilitas dan Operasi

### 12.1 Logging

- log event create inbox
- log inbound accepted/rejected
- log cleanup dan purge
- log error parsing dan queue failure

### 12.2 Metrics

- inbox created per menit
- email received per domain
- rejection rate
- processing latency
- cleanup success rate
- cache hit rate inbox lookup

### 12.3 Alerting

- lonjakan inbox creation
- domain MX down
- queue backlog tinggi
- spam surge
- kegagalan cleanup

### 12.4 Health Check

- endpoint `/health` memverifikasi status DB, Redis, Queue, dan status DNS MX domain aktif.
- status metrics ditarik oleh sistem monitoring internal.

## 13. Kinerja dan Skalabilitas

- gunakan queue untuk semua pemrosesan berat
- cache inbox aktif dan lookup recipient
- shard data berdasarkan domain atau hash alamat
- simpan body email besar di object storage bila perlu
- batasi ukuran pesan sejak ingress
- desain cleanup sebagai proses idempotent

## 14. Rekomendasi Implementasi Bertahap

### Tahap 1: MVP

- create inbox dengan rate limit dasar (5 inbox/IP/jam)
- inbound email routing untuk satu domain
- daftar pesan dasar dengan polling
- TTL dan cleanup sederhana (soft-expire + hard-delete)
- endpoint `/mail/inbound` dengan autentikasi shared secret

### Tahap 2: Reliability

- real-time update
- multi-domain support
- anti-abuse dasar
- attachment handling terbatas

### Tahap 3: Scale dan Enterprise

- custom domain onboarding
- multi-tenant quota
- observability lengkap
- security hardening dan retention policy granular

## 15. Catatan Integrasi Sistem Lain

- gunakan named service boundary agar modul email tidak bercampur dengan domain bisnis lain
- bila backend berbasis framework web umum, inbox service sebaiknya dipisah dari aplikasi inti
- jika ada autentikasi pengguna, inbox anonim dan inbox akun harus diperlakukan sebagai dua mode berbeda
- untuk API publik, dokumentasikan batasan TTL, ukuran pesan, dan kebijakan retensi sejak awal

## 16. Ringkasan Keputusan Desain

- domain dikelola sistem, bukan pengguna akhir
- inbox dibuat cepat dan sementara
- penerimaan email harus lewat pipeline asynchronous
- data email diproses dengan sanitasi kuat
- cleanup wajib deterministik dan idempotent
- custom domain butuh validasi kepemilikan dan kontrol operasional terpisah

## 17. Kesimpulan Revisi

- Alur SSE `message.received` dengan keep-alive 30 detik.
- Autentikasi `X-Mail-Secret` atau mTLS pada `/mail/inbound`/`/mail/bounce`.
- Token lifecycle: plaintext satu kali, hash disimpan, tidak ada recovery untuk inbox anonim.
- Rate-limit: 5 inbox/IP/jam, 100 request/menit/token untuk baca.
- Alias: CSPRNG, 12-16 karakter, regex RFC 5322, hindari kata kamus.
- DKIM signing konfigurasi per domain, persiapan outbound.
- Indeks DB: PK UUID, unik `address`, indeks `expires_at`, `status`, `inbox_id`+`received_at`.
- Cleanup worker: soft-expire 10 menit, retry queue max 3, rollback transaksi bila gagal.
- Retensi: anonim TTL 60 menit (max 24 jam), autentikasi TTL 24 jam (max 7 hari), attachment 30 menit pasca-expire, logs 30 hari.
- Roadmap MVP -> Reliability -> Enterprise terpadu.

## 18. Penutup

Blueprint ini memberi kerangka untuk membangun temporary email service dengan inbox aktif pada domain sistem-managed. Fokus utama ada pada pemisahan tanggung jawab, lifecycle yang jelas, keamanan tinggi, dan operasi yang mudah diskalakan. Implementasi detail bisa disesuaikan dengan stack backend apa pun selama kontrak data, routing email, dan kebijakan retensi tetap konsisten.