# Panduan Markdown Reader

Fitur **Docs** adalah halaman viewer dokumen Markdown yang memungkinkan kamu membaca, mengupload, dan mengelola file `.md` langsung dari dalam aplikasi.

---

## Mengakses Halaman

Buka menu **Docs** di sidebar navigasi kiri. Halaman ini hanya bisa diakses setelah login dan verifikasi OTP.

---

## Tampilan

Halaman menggunakan layout dua panel:

- **Sidebar kiri** — daftar semua dokumen yang tersedia, lengkap dengan ikon sumber, nama file, dan ukuran.
- **Panel kanan** — preview konten dokumen yang dipilih, dirender dari Markdown menjadi HTML.

---

## Sumber Dokumen

Terdapat tiga sumber dokumen yang ditampilkan secara otomatis:

| Ikon | Sumber | Keterangan |
| ---- | ------ | ---------- |
| 🔵 | **Project** | File `.md` di root project (misal `README.md`, `CLAUDE.md`) |
| 🟡 | **docs/** | File `.md` di folder `docs/` dalam root project |
| 🟢 | **Upload** | File yang kamu upload sendiri melalui tombol Upload |

> File dari sumber Project dan docs/ bersifat **read-only** — tidak bisa dihapus melalui UI. Hanya file upload yang bisa dihapus.

---

## Sync File Project

Klik tombol **Sync** di pojok kanan atas untuk memperbarui daftar dokumen dari root project dan folder `docs/`. Lakukan ini jika ada file `.md` baru yang ditambahkan atau dihapus dari project.

---

## Upload Dokumen

1. Klik tombol **Upload** di pojok kanan atas.
2. Pilih file Markdown dari komputer (format `.md` atau `.markdown`, maksimal 2MB).
3. Isi kolom **Judul** jika ingin menentukan judul manual — jika dikosongkan, judul diambil otomatis dari heading pertama (`# ...`) di dalam file, atau dari nama file jika tidak ada heading.
4. Klik **Upload**.

---

## Melihat Dokumen

Klik salah satu item di sidebar kiri untuk menampilkan konten dokumen di panel kanan. Konten Markdown dirender dengan dukungan:

- Heading dengan anchor permalink
- **Bold**, *italic*, ~~strikethrough~~
- Tabel (GitHub Flavored Markdown)
- Blok kode (`code block`) dengan sintaks
- Checklist task list `- [x]`
- Autolink URL
- Blockquote

---

## Download Dokumen

Setelah memilih dokumen, klik tombol **Download** di bagian atas panel kanan untuk mengunduh file Markdown-nya.

---

## Menghapus Dokumen

Hapus hanya tersedia untuk dokumen yang diupload (bukan file project). Arahkan kursor ke item dokumen di sidebar, lalu klik ikon sampah yang muncul di sebelah kanannya. Konfirmasi penghapusan di dialog yang tampil.

---

## Catatan Teknis

- File upload disimpan di disk `local` Laravel (`storage/app/documents/`).
- File project root dan `docs/` dibaca langsung dari filesystem — tidak disalin ke storage.
- Parsing Markdown menggunakan `league/commonmark` v2 dengan ekstensi GitHub Flavored Markdown (GFM), Heading Permalink, dan Table of Contents.
- Styling konten menggunakan `@tailwindcss/typography` (kelas `prose`).
