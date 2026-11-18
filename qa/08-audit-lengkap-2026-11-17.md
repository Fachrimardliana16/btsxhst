# 08 — Audit Menyeluruh (17 November 2026)

Repo: `/Users/fachrimardliana/Downloads/apps/btsxhst`
Metode: pembacaan kode (read-only) di empat area paralel — keamanan, alur bisnis,
UI/UX + i18n, kualitas kode + operasi. Temuan kritis diverifikasi ulang manual.
Tidak ada berkas aplikasi, basis data, atau production yang diubah saat audit.

Legenda: **V** = terverifikasi dari kode/vendor, **R** = perlu cek runtime/topologi.

---

## P0 — Perbaiki lebih dulu

### P0-1. Capture PayPal tidak terikat ke pesanan yang dibayar (V)
`app/Livewire/Front/PaypalKembali.php:56,85` → `app/Services/Pembayaran/PaypalGateway.php:122-153,340-377`.

`token` dari query string ditangkap apa adanya, lalu dicatat ke pesanan dari
`ulid` di URL. Tidak ada pemeriksaan `purchase_units[0].reference_id`,
`amount`, atau `currency` terhadap `order_number`, `grand_total`, `currency`.
Pembeli bisa menyetujui PayPal order murah lalu mengganti `ulid` ke pesanan
mahal miliknya (atau pesanan lain yang ULID-nya diketahui) dan pesanan itu
ditandai lunas. Webhook path (`:322-328`) juga tidak memeriksa nominal/mata uang.

Perbaikan: sebelum capture, ambil PayPal order dan wajibkan `reference_id`
sama dengan `order_number`; setelah capture, wajibkan nominal + mata uang sama
persis. Simpan PayPal order id di sisi pesanan, jangan percaya token klien.

### P0-2. Pesanan cancelled tetap bisa dibayar lewat PayPal (V)
`resources/views/livewire/front/pesanan-sukses.blade.php:37` menampilkan tombol
selama `payment_status = unpaid`; `routes/web.php:88-114` hanya memblokir yang
sudah Paid. Pesanan yang kedaluwarsa 24 jam (stok sudah dilepas) masih bisa
dibayar: dana tertangkap, status tetap Cancelled, tidak ada refund, tidak ada
pengiriman.

Perbaikan: tolak mulai/capture PayPal bila status bukan `pending` (dan bukan
`paid` untuk pemulihan); sembunyikan tombol pada pesanan cancelled; capture
untuk pesanan tak-bayar harus di-refund otomatis + ditandai untuk admin.

### P0-3. Tolak bukti manual bisa mengembalikan pesanan lunas menjadi unpaid (V)
`app/Services/Pembayaran/PembayaranManual.php:231-278`.

`tolak()` mengisi `verified_at` (jadi bukti ditolak terbaca "verified"), dan
menurunkan `payment_status` ke Unpaid bila tidak ada bukti lain berstatus
`menunggu-verifikasi` — tanpa memeriksa apakah sudah ada pembayaran terverifikasi
atau status pesanan sudah `paid`. Alur nyata: verifikasi bukti A, tolak bukti B →
`status=paid`, `payment_status=unpaid`. `Payment::terverifikasi()` juga salah
menganggap bukti ditolak sebagai terverifikasi.

Perbaikan: jangan isi `verified_at` saat menolak; turunkan ke Unpaid hanya bila
tidak ada pembayaran terverifikasi dan status bukan `paid`; tampilkan tombol
tolak hanya untuk bukti yang masih menunggu.

### P0-4. Cadangan SQLite dapat robek dan tetap lolos validasi (V)
`app/Console/Commands/CadangkanBasisData.php:109` memakai `@copy()` berkas live.
Produksi SQLite + WAL + 4 worker php-cgi (`docs/PRODUKSI.md:23,29`). Penyalinan
berkas saat penulisan berjalan dapat menghasilkan cadangan tidak konsisten;
pemeriksaan di `:94` hanya 16 byte header + ukuran ≥1024.
`app/Livewire/Admin/CadanganPanel.php:61` juga memanggil `Artisan::hasOption()`
yang tidak ada di Kernel → setiap klik backup manual melempar
`BadMethodCallException`, menampilkan "gagal", dan menulis `backup.gagal`
meskipun backup berhasil. Exit code `Artisan::call()` diabaikan.

Perbaikan: `sqlite3 "$db" ".backup '$tujuan'"` atau `VACUUM INTO`; jalankan
`PRAGMA integrity_check` pada hasil sebelum rotasi; pakai exit code; log dan
notifikasi kegagalan backup terjadwal (`routes/console.php:31-34` menelan
kegagalan dengan `onFailure(fn () => null)`).

### P0-5. `lockForUpdate()` tidak berlaku di SQLite — stok bisa bentrok (V)
`app/Services/PesananService.php:61-65,75,113`.
`SQLiteGrammar::compileLock()` mengembalikan string kosong
(`vendor/.../SQLiteGrammar.php:31`). Dua checkout bersamaan dapat sama-sama
lolos cek stok dan menaikkan `reserved_quantity` melebihi stok fisik, atau
transaksi kedua gagal `SQLITE_BUSY`. Produksi memakai SQLite.

Perbaikan: update atomik
`UPDATE product_variants SET reserved_quantity = reserved_quantity + ?
WHERE id = ? AND (stock_quantity - reserved_quantity) >= ?`
dan batalkan bila affected rows = 0.

### P0-6. Job notifikasi menelan exception — retry tidak pernah jalan (V)
`app/Jobs/KirimNotifikasiPesanan.php:92-96,135-139`,
`app/Jobs/KirimNotifikasiAdmin.php:89-96`.
Semua `Throwable` ditangkap dan hanya di-log, lalu job selesai "sukses".
Akibatnya `$tries`/`$backoff` tidak berlaku dan `failed()` nyaris tidak pernah
dipanggil: gangguan SMTP/Fonnte selama berjam-jam membuang semua notifikasi.

Perbaikan: coba tiap kanal secara terpisah, lalu rethrow setelah keduanya
(atau `$this->release($delay)`); biarkan `failed()` yang memberi peringatan.

### P0-7. Web push tidak pernah berhasil (V)
`app/Support/NotifikasiPush.php:95,101`.
`queueNotification()` di vendor mengembalikan `void`
(`vendor/minishlink/web-push/src/WebPush.php:118`), tetapi hasilnya disimpan
sebagai value lalu dicari dengan `array_search($laporan, ...)` — selalu false.
Konsekuensi: `$hasil['push']` selalu 0, `last_used_at` tidak pernah diperbarui,
langganan 404/410 tidak dihapus, semua event jatuh ke email cadangan, dan tombol
"Test notification" selalu melaporkan gagal.

Perbaikan: simpan langganan per endpoint, cocokkan `$laporan->getEndpoint()`.

---

## P1 — Tinggi

### P1-1. Segel checkout dikonsumsi sebelum validasi (V)
`app/Livewire/Front/Checkout.php:321-332`.
`session()->put('checkout.segel', ...)` terjadi sebelum `validate()`. Satu galat
validasi apa pun membuat kirim ulang berikutnya ditolak "Formulir ini sudah
dipakai" dan pembeli harus memuat ulang halaman, kehilangan isian.
Perbaikan: validasi dulu, konsumsi segel setelahnya; simpan nilai harapan di
sesi sisi server dan jangan kirim lewat `wire:model`.

### P1-2. Rate limit checkout per sesi; segel dari klien (V)
`app/Livewire/Front/Checkout.php:277,299-303,314-327`,
`resources/views/livewire/front/checkout.blade.php:29`.
Kunci limiter menambahkan `session()->getId()`, jadi menghapus cookie sesi
memberi kuota baru; nilai `segel` diterima dari klien sehingga segel acak baru
selalu lolos. Checkout berulang menahan stok sampai job kedaluwarsa.
Perbaikan: batasi per IP (atau IP + token terbitan server), dan simpan nonce
sesi di server untuk dibandingkan.

### P1-3. `mail_password` terhapus saat tab Email disimpan (V)
`app/Support/KomponenPengaturan.php:30-34,163-183`,
`app/Support/DefinisiPengaturan.php:107`, `app/Livewire/Admin/PengaturanSitus.php:284`.
Form menampilkan kolom terenkripsi kosong; `saringKosong()` hanya mempertahankan
blank untuk tiga kunci di `KUNCI_RAHASIA`, dan `mail_password` tidak termasuk.
Menyimpan tab Email tanpa mengisi ulang kata sandi menimpa secret dengan string
kosong → SMTP berhenti bekerja diam-diam.
Perbaikan: tambahkan `mail_password` (atau semua kunci `is_encrypted`) ke aturan
"blank = jangan ubah", dan satukan daftar rahasia dengan kolom `is_encrypted`.

### P1-4. CSV export admin rawan formula injection (V)
`app/Http/Controllers/Admin/DokumenPesanan.php:120,129`,
`app/Http/Controllers/Admin/LaporanPenjualan.php:186,195`.
Nama/alamat/kota/resi dari pembeli masuk `fputcsv` apa adanya. Sel yang diawali
`=` `+` `-` `@` dieksekusi Excel saat admin membuka ekspor.
Perbaikan: awali sel berbahaya dengan `'` (atau tab) untuk semua sel string
yang berasal dari pengguna.

### P1-5. Isi situs berbahasa Inggris masih banyak yang Indonesia (V)
Area pembeli yang paling terlihat:
- Email transaksional: `resources/views/mail/pesanan-pembeli.blade.php:40-48`
  (`Transfer ke`, `a.n.`), `pesanan-admin.blade.php`; WhatsApp di
  `app/Jobs/KirimNotifikasiPesanan.php`.
- Halaman kembali PayPal: `paypal-kembali.blade.php` + `PaypalKembali.php`
  (seluruh teks).
- Halaman sukses/unggah bukti: `pesanan-sukses.blade.php:33-75`,
  `unggah-bukti.blade.php`, `UnggahBukti.php`.
- Halaman kategori: `kategori.blade.php` seluruhnya (`Beranda`, `Produk`,
  `Urutkan`, `Terbaru`, `Belum ada produk`). Baris 25 bug:
  `{{ __('site.product.variant') === 'Varian' ? 'produk' : 'produk' }}` —
  kedua cabang sama.
- FAQ beranda (`Beranda.php:170-215`), pesan galat checkout/kupon/stok
  (`Checkout.php`, `Coupon.php`, `PesananService.php`), error pages 403/404/
  419/500/503, label aria hero, WhatsApp template, notifikasi admin,
  `PetaSitus.php`, JS cetak label (`app.js:311-343`).
- Skip link `layouts/front.blade.php:246-251` berlabel "Search" padahal menuju
  `#konten-utama`.
Perbaikan: pindahkan semua string ke `lang/en/`, render lewat `__()`, termasuk
subjek email dan badan WhatsApp.

### P1-6. Bukti transfer yang menunggu tidak menahan pembatalan 24 jam (V)
`app/Services/Pembayaran/PembayaranManual.php:47-106` hanya mengubah
`payment_status`, tidak menyentuh `expires_at`;
`app/Console/Commands/BatalkanKedaluwarsa.php:35-41` membatalkan semua `pending`
yang lewat batas. Bukti yang diunggah jam ke-23 bisa dibatalkan otomatis.
Perbaikan: lewati auto-cancel bila `payment_status = pending`, atau perpanjang
`expires_at` saat bukti diunggah.

### P1-7. Pesanan internasional ditagih tarif domestik (V)
`app/Services/PesananService.php:116-133,146,166-172`.
`shipping_cost` selalu `shipping_flat_rate`; `shipping_quoted_at` tidak pernah
ditulis di mana pun (grep hanya menemukan kolom dan komentar), dan tidak ada
aksi admin untuk memasukkan ongkir hasil penawaran. Pesanan luar negeri kurang
bayar dan tidak punya jalur pelunasan ongkir.
Perbaikan: aksi "quote shipping" di panel yang memperbarui `shipping_cost`,
`grand_total`, `shipping_quoted_at`; blokir pemrosesan sampai terisi.

### P1-8. Kuota kupon habis oleh pesanan yang tidak pernah dibayar (V)
`app/Services/PesananService.php:189-204` menaikkan `used_count` saat pesanan
dibuat; tidak ada penurunan saat cancelled/kedaluwarsa
(`BatalkanKedaluwarsa`, `StatusPesananService` tidak menyentuh kupon).
Perbaikan: turunkan pada Cancelled, atau hitung kuota dari pesanan lunas.

### P1-9. Angka dashboard tidak konsisten dengan laporan (V)
`app/Livewire/Admin/Dashboard.php:26-29,43-50,72-80`.
Pendapatan hari ini, grafik 30 hari, dan terlaris hanya mengecualikan
Cancelled — pesanan `pending` ikut terhitung. `LaporanPenjualan.php:160-168`
memakai `paid/processing/shipped/completed`. KOL (`KolPerforma.php:66-94`)
justru hanya `paid/completed`, melewatkan processing/shipped.
Perbaikan: satu definisi "pendapatan" yang dipakai bersama.

### P1-10. Pembatalan dan verifikasi manual tidak atomik (V)
`app/Console/Commands/BatalkanKedaluwarsa.php` memuat semua pesanan lalu
mengubah model tanpa `WHERE status='pending'`; balapan dengan webhook PayPal
dapat membatalkan pesanan yang baru saja dibayar. `PembayaranManual::verifikasi`
menetapkan `paid` tanpa memeriksa status cancelled.
Perbaikan: update bersyarat + hitung yang benar-benar berubah; tolak verifikasi
untuk pesanan cancelled (atau pulihkan secara eksplisit).

### P1-11. Pengaturan ganda & SSRF kecil di panel (V)
`Pelanggan.php:84`, `KeamananPanel.php:125,155`, `PengaturanSitus.php:166` hanya
mengandalkan gate rute, tanpa `authorize()` per aksi.
`NotifikasiPerangkat.php:33` menerima endpoint push sembarang;
`NotifikasiPush.php:89-95` melakukan POST ke URL itu dari server (SSRF buta,
hanya admin).
Perbaikan: `authorize()` di awal setiap aksi; allowlist host push + tolak IP
loopback/privat.

### P1-12. Semua test dihapus, tidak ada CI (V)
Commit `f93c8f8` menghapus 66 berkas / 14.458 baris `tests/`, `phpunit.xml`,
`phpunit/phpunit` dari `composer.json`, dan skrip `composer test`. Tidak ada
`.github/workflows`. Tidak ada jaring regresi untuk seluruh alur di atas.
Perbaikan: pulihkan suite dari commit sebelum `f93c8f8` (setelah menyesuaikan
perubahan skema), tambahkan minimal test untuk checkout, pembayaran manual,
PayPal, dan transisi status.

### P1-13. Di balik cloudflared semua tamu tampak `127.0.0.1` (R)
`app/Http/Middleware/PercayaiProxy.php:58` hanya mempercayai
`HEADER_X_FORWARDED_PROTO`; `X-Forwarded-For` tidak dipakai sama sekali.
`BlokirIp`/rate limit memakai `$request->ip()` → `127.0.0.1` untuk semua tamu.
Satu ambang brute-force terlampaui memblokir seluruh storefront bagi tamu
(admin yang sudah login dikecualikan). Perlu dipastikan topologi produksi
(`IKK_TRUSTED_PROXIES`, `.env.production`) dan apakah Cloudflare dipakai.

---

## P2 — Sedang

- **Bukti ditolak tercatat terverifikasi** — `PembayaranManual.php:260` mengisi
  `verified_at`; `Payment::terverifikasi()` (`app/Models/Payment.php:50-53`)
  hanya melihat kolom itu.
- **Bukti manual pada pesanan cancelled** — `PembayaranManual.php:218` menetapkan
  `paid` tanpa memindahkan status. `StatusPembayaran::Refunded` tidak pernah
  dipakai di mana pun.
- **"Mark as paid" tanpa baris `Payment`** —
  `app/Livewire/Admin/PesananDaftar.php:95-109`; rekonsiliasi pembayaran jadi
  tidak lengkap.
- **Payload Push dibuang** — `NotifikasiPush.php:95` mengirim `$muatan ?: ''`;
  judul/isi notifikasi tidak sampai, hanya sinyal kosong.
- **Cache halaman tidak pernah hit** — `CacheHalaman.php:88` menolak menyimpan
  bila ada `wire:snapshot`; navbar/bottom-nav selalu memuat Livewire. Penjaga
  `data-csrf` juga salah penanda (layout memakai meta `csrf-token`). Bila cache
  nanti hidup, `ModePemeliharaan` (`bootstrap/app.php:101-110`) berada SETELAH
  `CacheHalaman`, sehingga halaman cache bisa disajikan saat toko ditutup.
- **Push spam tiap navigasi admin** — `LoncengNotifikasi.php:71` properti publik
  `$diberitahukan` reset tiap muat halaman; `beriTahu()` mengirim push lagi.
  Setelah P0-7 diperbaiki, ini langsung terasa.
- **Lonceng menghitung ulang papan notifikasi berkali-kali** —
  `NotifikasiAdmin::semua()` dipanggil di setiap render dan di `tandaiDilihat`
  per item; `hampirBatal()` mengambil semua pesanan tanpa batas.
- **Satu kueri keranjang per kartu produk** —
  `TambahKeranjangCepat::mount()` → `hitungUlang()` untuk setiap kartu katalog.
- **Pesan exception PHP tampil ke pengguna** — `Checkout.php:410,453`,
  `PesananDaftar.php:107`, `PesananDetail.php:103,156,182`,
  `CadanganPanel.php:73`. Bisnis message sah, tetapi PDO/TypeError ikut bocor.
- **Pencarian email pesanan tidak pernah cocok** — `Order.php:159` mencari
  `guest_email_bidx LIKE` atas HMAC; HMAC tidak bisa dicocokkan sebagian.
  Escaping `%`/`_` juga tanpa klausa `ESCAPE`. Pencarian nama pelanggan
  (`Pelanggan.php:246-260`) hanya menyaring halaman aktif, total paginasi
  ikut salah.
- **Indeks tanggal pesanan kurang** — `orders.created_at` tidak diindeks;
  laporan/filter tanggal bisa memindai tabel (perlu `EXPLAIN QUERY PLAN`).
- **`sold_count` tidak pernah dinaikkan** — sortir "populer" di katalog dan
  panel selalu seri di 0.
- **Produk yang ditarik masih bisa dipesan** — `PesananService.php:61-71` dan
  `TambahKeranjangCepat.php:39-43` hanya memeriksa varian aktif.
- **Tarif gratis ongkir mengabaikan batas `max_discount`** —
  `Keranjang.php:390-395`, `PesananService.php:127-129`.
- **Kupon USD bergeser mengikuti kurs** — tidak ada kolom USD di `coupons`;
  nilai dikonversi ulang saat checkout.
- **Tanpa batas kupon per pengguna** — skema hanya punya kuota global.
- **Kurir/metode bayar tidak divalidasi terhadap daftar aktif** —
  `Checkout.php:55-56,88`; `metode=paypal` tetap diproses walau mode pembayaran
  `manual`.
- **Varian: validasi stok memakai `ditahan` dari klien** —
  `MengelolaVarian.php:164-215` membandingkan dengan nilai kiriman form, bukan
  `reserved_quantity` terkini di DB.
- **`gabungkanKe()` keranjang belum dipanggil** dan punya bug bila dipasang
  (catatan tamu/kupon hilang, baris kuantitas 0). Sudah tercatat di
  `qa/03` sebagai O-02.
- **Duplikasi kode tabel admin** — 9 view memakai tabel ad-hoc, dashboard
  memakai `.data-table`; perlu komponen bersama.
- **Aksesibilitas** — drawer mobile tanpa focus trap/Escape/`aria-labelledby`;
  input error tanpa `aria-invalid`/`aria-describedby`; tombol tambah-keranjang
  tanpa state loading dan umpan balik tanpa `aria-live`; target sentuh 40px di
  keranjang; footer `<details>` selalu `open` di mobile; `dim` `#6b7280` di
  `#eef1f5` = 4,27:1 (di bawah AA).
- **`.env.example` tidak lengkap** — `VAPID_*`, `IKK_*` tidak ada;
  `BLIND_INDEX_KEY` kosong sehingga deploy baru gagal 500 pada penyimpanan
  pengguna pertama.
- **`Pengaturan.php:66` menelan kegagalan cache/DB tanpa log** — tabel
  pengaturan rusak berarti default diam-diam aktif.
- **Command pemeliharaan tidak terbatas** — `BatalkanKedaluwarsa` memuat semua
  pesanan; `SalinHargaDolar` N+1 dalam cursor.
- **Kunci cache katalog naik tidak atomik** — `CacheKatalog.php:88-95` rentan
  kehilangan increment di 4 worker.
- **Health check hanya boot** — `/up` bawaan tidak memeriksa DB, cache, antrean,
  disk, atau kesegaran backup.
- **`sw.js`** — versi hanya naik lewat `prebuild`; perbaikan aset tanpa
  `npm run build` bisa menyajikan cache lama. Registrasi SW ganda di layout dan
  `app.js`.
- **`cache:prune-stale-tags` dijadwalkan padahal cache store `file`.**
- **Dependensi** — `composer audit`: 9 advisory / 6 paket. `laravel/framework`
  v12.70.0 (butuh ≥12.71.0), `symfony/http-foundation`/`http-kernel`/`routing`
  v7.4.21, `mime` v7.4.19 (butuh ≥7.4.22/23). `npm audit`: 0.
- **`public/produksi.php`** — bila `.env.production` tidak ada, respons 500
  memuat path absolut server.
- **Path URL masih Indonesia** — `/produk`, `/kategori`, `/keranjang`,
  `/checkout`, `/lacak`, `/halaman/*`; bukan tautan rusak, tetapi tidak
  konsisten dengan situs Inggris.

---

## Yang sudah baik (terverifikasi)

- Verifikasi tanda tangan webhook PayPal memakai endpoint resmi + webhook ID;
  tanda tangan palsu dicatat, tidak dipercaya.
- Bukti pembayaran: disk privat, validasi MIME berbasis isi + `getimagesize`,
  batas ukuran/dimensi, URL unduhan bertanda tangan 15 menit, header `nosniff`
  dan `attachment`.
- CSP nonce per respons tanpa `unsafe-inline`, plus `nosniff`,
  `X-Frame-Options: SAMEORIGIN`, Referrer-Policy, Permissions-Policy, COOP,
  HSTS saat HTTPS.
- IDOR pesanan/keranjang cukup tertutup: pesanan memakai ULID, aksi bukti
  dibatasi pesanan pemilik, otorisasi panel lewat gate rute + `PastikanAdmin`.
- Enkripsi PII + blind index email; login admin punya TOTP/recovery code,
  rate limit per IP/email, lockout, dan regenerasi sesi.
- Tidak ditemukan SQL injection, XSS, bypass CSRF, atau mass assignment publik.
- Dokumen cetak (invoice, surat jalan, label, laporan) sudah bahasa Inggris.

---

## Urutan kerja yang disarankan

1. P0-1 … P0-3 (uang dan status pesanan).
2. P0-4, P0-5, P0-6, P0-7 (data loss, stok, notifikasi).
3. P1-1, P1-2, P1-3, P1-4 (checkout, SMTP, ekspor).
4. P1-5 (i18n pembeli), P1-6 … P1-11 (alur bisnis + panel).
5. Pulihkan test (P1-12) dan cek topologi proxy (P1-13) sebelum menambah fitur.
6. P2 sesuai dampak.
