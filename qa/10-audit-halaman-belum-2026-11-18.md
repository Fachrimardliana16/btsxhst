# Audit halaman yang belum tersentuh — 18 November 2026

Lanjutan dari `qa/08-audit-lengkap-2026-11-17.md` dan
`qa/09-audit-area-2-7-2026-11-17.md`. Berkas ini menutup sisa cakupan: seluruh
halaman panel `/admin/*`, dokumen cetak, unduhan bertanda tangan, ekspor
laporan, halaman galat, dan berkas PWA.

Tidak ada perubahan pada basis data produksi. Tidak ada seeder yang dijalankan.

## Cara audit

Setiap rute panel diperiksa dari `routes/admin.php` lalu dibuka satu per satu
sebagai super admin; dokumen cetak, laporan, dan unduhan bertanda tangan
dijalankan sungguhan. Pembatasan peran diverifikasi dari rute, Gate, dan
middleware di server, bukan dari tampilan:

| Peran | Perilaku yang diharapkan | Diuji |
| --- | --- | --- |
| Super admin | 200 pada seluruh halaman panel, detail pesanan, dokumen, dan laporan | ya |
| Admin biasa | 403 pada `admin.users`, `admin.security`, `admin.backup`, `admin.settings` | ya |
| Pelanggan (masuk) | 403 di panel, termasuk unduhan dokumen pesanan | ya |
| Tamu | dialihkan ke `admin.login` | ya |
| Bukti bayar | 404 tanpa tanda tangan sah | ya |
| Dokumen cetak | `application/pdf` untuk resi, invoice, surat jalan | ya |

Skrip uji sementara yang dipakai selama audit sudah dihapus; berkas ini yang
menjadi catatannya.

## Temuan dan perbaikan

### P1 — Detail pesanan dan dokumen cetak gagal 500 pada alamat lama

`resources/views/livewire/admin/pesanan-detail.blade.php:323`,
`resources/views/cetak/invoice.blade.php:121`,
`resources/views/cetak/surat-jalan.blade.php:107` membaca
`$alamat['state_province']` dan `$alamat['postal_code']` tanpa penjagaan. Baris
itu hanya dijaga oleh `filled($alamat['city'])`. Snapshot alamat yang dibuat
sebelum sebuah kolom ada — atau alamat luar negeri tanpa provinsi/kode pos —
membuat halaman detail pesanan dan kedua dokumen cetak berhenti dengan
`Undefined array key "state_province"`. Terbukti lewat test: detail pesanan
mengembalikan 500 sebelum diperbaiki.

Perbaikan: helper baru `Order::barisKota()`
(`app/Models/Order.php:186`) merangkai kota, provinsi, dan kode pos dari bagian
yang benar-benar terisi; ketiga tampilan memakainya. `namaPenerima()` dan
`teleponPenerima()` juga dipakai di detail pesanan agar snapshot yang tidak
lengkap tidak lagi melempar galat.

### P1 — Teks Indonesia di antarmuka Inggris

Situs, panel, dan dokumen wajib berbahasa Inggris. String berikut tampil ke
pengguna dan sudah diganti:

| Berkas | Sebelum |
| --- | --- |
| `app/Http/Middleware/PastikanAdmin.php:36,53,58` | "Akses dari jaringan ini diblokir sementara.", "Akun ini tidak memiliki akses ke panel admin.", "Akun ini diblokir." |
| `app/Http/Controllers/Admin/UnduhCadangan.php:43,49,56,58` | "Nama cadangan tidak sah.", "Folder cadangan belum ada.", "Cadangan tidak ditemukan." |
| `resources/views/livewire/admin/pesanan-detail.blade.php:339` | `'belum dipilih'` → kunci `site.admin.pesanan_detail.belum_dipilih` |

Kunci baru ditambahkan di `lang/en/site.php`; `lang/id/site.php` memang tidak
memuat namespace `admin` sama sekali, jadi panel selalu memakai bahasa Inggris
lewat fallback — perilaku yang benar, bukan kekurangan.

### P1 — PWA masih berbahasa Indonesia

`public/manifest.webmanifest` memakai `"lang": "id"`, deskripsi Indonesia, dan
tiga pintasan bernama "Katalog", "Keranjang", "Lacak pesanan".
`public/offline.html` seluruhnya Indonesia dengan `lang="id"`. Keduanya diganti
ke bahasa Inggris (`"lang": "en"`, pintasan "Catalog", "Cart", "Track order").
Nama berkas, `start_url`, dan ikon tidak berubah sehingga cache service worker
lama tetap sah.

### P2 — Halaman galat tidak menandai `noindex`

`resources/views/errors/layout.blade.php` tidak memuat `robots`. Halaman 404
dapat terindeks dan bersaing dengan halaman asli di mesin pencari; 429 dan 419
yang muncul sementara juga ikut terindeks. `503-pemeliharaan.blade.php` sudah
punya penandanya sendiri sejak awal, jadi ketidakcocokan ini jelas tidak
disengaja. Satu baris ditambahkan ke layout bersama.

### P2 — Ekspor laporan tanpa pembatas laju (selesai)

`routes/admin.php:102-105` hanya memasang `auth` + `PastikanAdmin` pada
`laporan/penjualan/csv` dan `laporan/penjualan/pdf`. Keduanya memuat hingga 5000
pesanan beserta relasi, sementara rute unduhan lain sudah dibatasi
(`throttle:10,1` untuk cadangan, `throttle:30,1` untuk bukti bayar). Sesudah
perbaikan, kedua rute memakai `throttle:6,1`.

### P2 — Dua jalur ekspor pesanan yang tumpang tindih (selesai)

`ekspor/pesanan` (`DokumenPesanan::eksporCsv`) dan `laporan/penjualan/csv`
menghasilkan CSV dari data yang sama dengan kolom berbeda. Yang lama tidak
ditautkan dari antarmuka mana pun — diperiksa dengan mencari nama rute
`exports.orders` dan jalur `ekspor/pesanan` di `app/`, `resources/`, dan
`routes/`, hasilnya hanya definisi rutenya sendiri. Rute, metode `eksporCsv`,
serta dua import yang menjadi tidak terpakai (`App\Support\Csv`,
`Illuminate\Http\Request`) sudah dihapus, sehingga tinggal satu jalur ekspor.

## Yang diperiksa dan tidak bermasalah

- Gerbang peran: `admin.pengguna`, `admin.keamanan`, `admin.cadangan`,
  `admin.pengaturan` dijaga Gate di tingkat rute, bukan hanya disembunyikan
  dari sidebar. Admin biasa menerima 403 (diuji).
- Unduhan bertanda tangan: `bukti-bayar/{payment}` dan
  `cadangan/unduh/{berkas}` menolak permintaan tanpa tanda tangan sah (404) dan
  memeriksa jalur berkas dengan `realpath` + pola nama, sehingga `../` tidak
  dapat keluar dari folder cadangan.
- Pesanan tamu: `emailPenerima()` dan pemakaian `??` di jalur ekspor tidak
  melempar galat ketika `user_id` kosong — rantai `??` menutup seluruh operan
  kirinya.
- Dokumen cetak: ketiga jenis menghasilkan `application/pdf` dari dompdf.
- Tidak ada string Indonesia yang tersisa di tampilan panel; sisa kemunculan
  kata Indonesia di `app/Livewire/Admin/*` adalah alias kolom SQL, kunci
  terjemahan, dan ringkasan log — bukan teks yang dibaca pengguna.

## Status

Berkas uji, `phpunit.xml`, dan alur CI `tests.yml` sudah dihapus sesuai
permintaan; skrip composer `analyse`/`style`/`check` ikut dibuang. Yang tetap
tersedia sebagai pemeriksa mandiri: `phpstan.neon` (level 5, 0 error) dan Laravel
Pint. Pint bersih, PHPStan bersih. Tidak ada commit yang dibuat.
