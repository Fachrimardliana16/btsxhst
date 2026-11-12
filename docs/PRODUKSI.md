# Produksi — bitesexhaust.com

Dokumen ini menjelaskan tumpukan produksi untuk **`D:\Projects\iksanknalpot2`**
dan langkah yang perlu dilakukan sekali saja.

---

## 1. Bentuk tumpukannya

```
Pengunjung
    │  HTTPS
    ▼
Cloudflare (edge)   ← DNS zone bitesexhaust.com
    │  tunnel (koneksi KELUAR, tanpa port-forwarding)
    ▼
cloudflared  (tunnel bernama "bites")
    │  http://127.0.0.1:8200   + header X-Forwarded-Proto: https
    ▼
nginx  127.0.0.1:8200   (conf/bites.conf)
    │  FastCGI
    ▼
php-cgi  127.0.0.1:9300..9303   (4 proses paralel)
    │
    ▼
public/produksi.php  →  memuat .env.production  →  public/index.php
    │
    ▼
SQLite  database/database-produksi.sqlite
```

Tidak ada port yang terbuka ke internet maupun ke LAN. `cloudflared` membuka
koneksi **keluar** ke Cloudflare, jadi tidak perlu IP publik, port-forwarding,
atau membuka firewall masuk.

---

## 2. Berkas yang dibuat

| Berkas | Kegunaan |
|---|---|
| `.env.production` | Konfigurasi produksi (tidak masuk git) |
| `public/produksi.php` | Pintu masuk yang memuat `.env.production` |
| `C:\Users\...\nginx\nginx-1.28.0\conf\bites.conf` | nginx produksi (port 8200, CGI 9300-9303) |
| `tools/nyalakan-produksi.ps1` | Menyalakan/mematikan seluruh tumpukan |
| `tools/siapkan-tunnel.ps1` | Membuat tunnel + DNS (sekali saja) |
| `~/.cloudflared/bites.yml` | Konfigurasi ingress tunnel |
| `database/database-produksi.sqlite` | Basis data produksi |

Pengembangan (`tools/nyalakan.ps1`, port 8100, `iksan.conf`, `.env`) **tidak
disentuh** dan tetap dapat berjalan bersamaan.

---

## 3. Kenapa `public/produksi.php` ada

`php-cgi` tidak menerima argumen `--env=production` seperti `artisan`, dan
Laravel selalu membaca berkas bernama `.env` — bukan `.env.production`.

Kalau CGI memakai `public/index.php` biasa, aplikasi produksi akan berjalan
dengan isi `.env` pengembangan: `APP_DEBUG=true` dan **database pengembangan**.
`public/produksi.php` memuat `.env.production` lebih dulu, sehingga kedua
lingkungan dapat hidup berdampingan tanpa saling menimpa.

Di nginx, hanya `/produksi.php` yang dieksekusi. Semua `.php` lain dijawab 404,
supaya `index.php` tidak dapat dipanggil langsung untuk melewati pemuatan
`.env.production`.

---

## 4. Langkah sekali-jalan: Name Server → Cloudflare

Anda yang melakukan ini di registrar.

1. Masuk ke panel registrar domain `bitesexhaust.com`.
2. Cari menu **Name Server / Nameservers** — ubah dari nilai sekarang menjadi
   dua (atau lebih) NS yang diberikan Cloudflare, bentuknya:

   ```
   algin.ns.cloudflare.com
   kay.ns.cloudflare.com
   ```

   (Nama persisnya muncul di dashboard Cloudflare, halaman **Overview** zone
   `bitesexhaust.com`, bagian "Replace your nameservers".)

3. Simpan. Propagasi biasanya < 1 jam, kadang sampai 24 jam.
4. Di Cloudflare, tunggu status zone berubah dari *Pending* menjadi **Active**.

> Zone harus **Active** sebelum catatan DNS bisa dibuat. Skrip tunnel akan
> memberi pesan jelas bila belum.

---

## 5. Langkah sekali-jalan: tunnel

Setelah zone Active, jalankan **di PowerShell** (butuh peramban, akun
Cloudflare Anda):

```powershell
# 1. Login — peramban terbuka, pilih zone bitesexhaust.com
& "C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel login

# 2. Buat tunnel "bites" + konfigurasi + catatan DNS
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\siapkan-tunnel.ps1
```

Skrip kedua membuat:

- tunnel bernama `bites` (alamat tetap, tidak berubah tiap restart)
- `~/.cloudflared/bites.yml` dengan ingress:
  - `bitesexhaust.com` → `http://127.0.0.1:8200`
  - `www.bitesexhaust.com` → `http://127.0.0.1:8200`
  - sisanya → 404
- catatan DNS CNAME untuk kedua host, menunjuk tunnel

---

## 6. Menyalakan sehari-hari

```powershell
# Nyalakan semua (nginx + php-cgi + tunnel)
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\nyalakan-produksi.ps1

# Hanya lokal, tanpa tunnel
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\nyalakan-produksi.ps1 -TanpaTunnel

# Hentikan
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\nyalakan-produksi.ps1 -Stop
```

Skrip ini juga membangun ulang cache `config`, `route`, `view`, dan `event`
dengan `--env=production` setiap kali dinyalakan. Tanpa itu, mengubah
`.env.production` tidak akan berpengaruh.

### 6a. Penjaga otomatis (`BitesExhaust-Watchdog`)

Sejak 9 Nov 2026 tumpukan tidak perlu dinyalakan manual. Scheduled Task
memeriksa setiap 5 menit, saat boot, dan saat logon; bila mendeteksi
tumpukan mati, ia memicu `nyalakan-produksi.ps1` sendiri.

```powershell
# Daftarkan / daftarkan ulang (aman dijalankan berulang)
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\daftarkan-penjaga.ps1

# Lihat keadaan tumpukan + penjaga
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\cek-status.ps1

# Uji pemulihan sungguhan (memadamkan nginx / tunnel lalu menunggu pulih)
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\uji-penjaga.ps1
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\uji-tunnel.ps1
```

Berkas terkait:

| Berkas | Isi |
|---|---|
| `tools/jaga-produksi.ps1` | Pemeriksa + pemicu restart |
| `tools/jalankan-penyalakan.cmd` | Dipanggil lewat WMI agar proses tidak jadi anak tugas |
| `tools/daftarkan-penjaga.ps1` | Pendaftaran tugas (XML, `Duration` tanpa batas) |
| `tools/out/jaga.log` | Catatan setiap kali penjaga bertindak |

Prinsipnya sengaja konservatif: lokal 8200 dicoba 3× sebelum dianggap mati,
dan kegagalan publik butuh 2 putaran berturut-turut. Satu probe gagal tidak
boleh memicu restart — restart salah tembak berarti downtime yang dibuat
sendiri.

---

## 7. Setelah mengubah kode atau `.env.production`

Jalankan ulang skrip nyalakan. Ia membersihkan dan membangun ulang seluruh
cache dengan lingkungan produksi.

Untuk mengubah aset (CSS/JS), `npm run build` dijalankan otomatis bila
`public/build/manifest.json` belum ada. Untuk membangun ulang secara paksa:

```powershell
cd D:\Projects\iksanknalpot2
npm run build
```

---

## 8. Akun admin

Dibuat oleh `UserSeeder` saat basis data produksi di-seed:

| Surel | Peran |
|---|---|
| `admin@bitesexhaust.test` | Super Admin |
| `staff@bitesexhaust.test` | Admin |

Kata sandi awal sama untuk keduanya (lihat `database/seeders/UserSeeder.php`).
**Ganti sebelum toko dibuka.** Kedua akun punya 2FA aktif sejak awal.

Untuk menambah admin baru atau mengganti kata sandi, gunakan panel
`/admin/pengguna`.

---

## 9. Catatan penting tentang mesin ini

### exFAT — tidak ada symlink

Volume `D:` berformat **exFAT**, yang tidak mendukung symlink maupun junction.
Akibatnya `artisan storage:link` gagal dengan
`Local NTFS volumes are required to complete the operation`.

Solusinya ada di nginx: `/storage/` dilayani langsung dari
`storage/app/public/`, tanpa symlink dan tanpa melewati PHP. Skrip nyalakan
menangani kegagalan `storage:link` dengan sengaja (tidak menghentikan proses).

### JANGAN pakai `real_ip_header` di nginx (penting)

Konfigurasi ini pernah dipakai lalu **dibatalkan**:

```nginx
set_real_ip_from 127.0.0.1;
real_ip_header   CF-Connecting-IP;   # JANGAN
```

Keduanya menimpa `$remote_addr` dengan IP publik pengunjung. PHP lalu melihat
`REMOTE_ADDR` sebagai IP internet, `PercayaiProxy` menolak mempercayainya
sebagai proxy, dan **`X-Forwarded-Proto` diabaikan**.

Gejalanya menipu: halaman tetap tampil normal, canonical ikut benar, tetapi
`sitemap.xml` dan `robots.txt` dirender sebagai `http://` — buruk untuk SEO
dan sulit dilacak karena hanya dua berkas itu yang terlihat salah.

Yang benar (sudah dipasang di `bites.conf`): `REMOTE_ADDR` dibiarkan
`127.0.0.1` sebagai bukti permintaan datang dari cloudflared di mesin ini,
sedangkan IP asli pengunjung diteruskan sebagai `X-Real-IP` untuk log dan
pembatas laju.

Jika suatu saat sitemap kembali menampilkan `http://`, periksa dulu apakah
`real_ip_header` muncul lagi di konfigurasi nginx.

### Antrean (`queue:work`) belum dijalankan

`.env.production` memakai `QUEUE_CONNECTION=database`. Pekerjaan antrean
(notifikasi surel/WhatsApp, pemrosesan gambar produk, push) **menumpuk** sampai
ada pekerja yang mengambilnya:

```powershell
cd D:\Projects\iksanknalpot2
& "$env:LOCALAPPDATA\php85\php.exe" artisan queue:work --env=production
```

Saat ini belum dijalankan otomatis oleh `nyalakan-produksi.ps1`. Kalau
notifikasi dan pemrosesan gambar dibutuhkan, jalankan perintah di atas di
jendela terpisah, atau tambahkan sebagai Windows Service (NSSM).

### Surel masih `log`

`MAIL_MAILER=log` berarti surel **tidak terkirim**; isinya hanya ditulis ke
`storage/logs/`. Isi kredensial SMTP asli di `.env.production` sebelum
mengandalkan notifikasi surel.

---

## 10. Branding: apa yang diganti dan apa yang sengaja TIDAK

Situs ini bernama **Bites Exhaust** (domain `bitesexhaust.com`), tetapi kode
internalnya masih memakai nama lama `iksan` di banyak tempat. Itu disengaja
untuk sebagian — mengganti semuanya akan memutus hal yang sedang berjalan.

### Yang SUDAH diganti (terlihat pengunjung)

| Lokasi | Nilai |
|---|---|
| `.env.production` → `APP_NAME` | `Bites Exhaust` |
| DB `settings.site_name` | `Bites Exhaust` |
| DB `settings.contact_email` | `halo@bitesexhaust.com` |
| DB `settings.social_*` | `instagram/tiktok/shopee` → `bitesexhaust` |
| DB nama admin (id 1) | `Bites Exhaust (Super Admin)` |
| `public/manifest.webmanifest` | `Bites Exhaust` |
| `public/offline.html` | `Bites Exhaust` |
| Teks tampil di `app/`, `resources/`, `lang/` | `Bites Exhaust` |

### Yang SENGAJA TIDAK diganti (nama teknis)

Mengganti ini bukan kosmetik — ada yang memutus fungsionalitas:

| Nama | Alasan |
|---|---|
| `config/iksan.php`, `config('iksan.*')` | Nama berkas config; 40+ tempat memanggilnya |
| Perintah artisan `iksan:backup`, `iksan:buka-blokir` | Nama yang dipanggil skrip & dokumentasi |
| Cookie `iksan_locale`, `iksan_zona`, `iksan_izin` | Kalau diganti, cookie pengunjung lama tak terbaca |
| `localStorage` `iksan.theme`, `iksan.lang` | Idem — preferensi tema/bahasa pengguna hilang |
| Objek JS `window.iksanTheme`, `iksanCookie` | Dipanggil dari banyak blade |
| Kode kupon `IKSAN10` | Sudah tersebar di data pesanan & placeholder form |
| Awalan kode pesanan `IK` | Format nomor pesanan yang sudah terbit |
| Nama berkas cadangan `iksan-*.sqlite` | Pola yang dipakai perintah pembersihan |

Mengganti nama-nama itu memerlukan migrasi tersendiri (ganti nama + alias
kompatibilitas + migrasi data cookie). Belum dikerjakan karena risikonya
melebihi manfaatnya untuk saat ini.

### Jika branding berubah lagi

Cukup ubah `APP_NAME` di `.env.production` **dan** `site_name` di panel
`/admin/pengaturan`. Setelah itu bersihkan cache:

```powershell
& "$env:LOCALAPPDATA\php85\php.exe" artisan cache:clear --env=production
```

Nama session cookie diturunkan dari `APP_NAME`, jadi pengunjung akan diminta
login ulang sekali setelah nama berubah. Itu normal.

---

## 11. Bahasa: Inggris tunggal

Situs berjalan **hanya dalam Bahasa Inggris**. `APP_LOCALE=en` dan
`APP_FALLBACK_LOCALE=en`.

### Kenapa cookie bahasa dihapus

`SetLocale` dulu membaca cookie `iksan_locale`, sehingga peramban yang pernah
memakai versi Indonesia tetap melihat versi Indonesia **selamanya** — termasuk
setelah `APP_LOCALE` diubah. Saat rebranding, gejalanya membingungkan: judul
halaman sudah Inggris, tetapi isi section masih Indonesia, karena penyebabnya
tersimpan di cookie peramban, bukan di kode.

Sekarang:

| Hal | Perilaku |
|---|---|
| `SetLocale` | Selalu memakai `config('app.locale')`, tidak membaca cookie |
| Cookie `iksan_locale` lama | Dihapus otomatis pada respons berikutnya |
| Tombol bahasa di navbar & hero | Dihapus |
| Rute `/lang/{locale}` | Dialihkan ke beranda (bukan 404, agar tautan lama aman) |

**Jika kelak perlu dua bahasa:** kembalikan pembacaan cookie di `SetLocale`,
pasang lagi tombol bahasa di navbar, dan pastikan `lang/id/site.php` lengkap.

### Di mana teks berada

| Sumber | Lokasi | Catatan |
|---|---|---|
| Antarmuka publik | `lang/en/site.php` | ~340 kunci |
| Antarmuka panel admin | `lang/en/site.php` → `admin.*` | 37 kelompok, per berkas |
| Pesan validasi & toast | `lang/en/site.php` → `msg.*` | dipanggil dari kelas Livewire |
| Pesan bervariabel tahap lanjut | `lang/en/site.php` → `msg2.*` | |
| Nama produk, kategori, FAQ, hero | Kolom `*_en` di basis data | `*_id` tetap utuh sebagai rujukan |
| Halaman privasi & syarat | `lang/en/site.php` → `halaman.*` | dulu hardcoded di PHP |

Model `Product`, `Category`, `Faq` memilih kolom lewat `app()->getLocale()`:
saat `en` ia membaca `title_en`/`name_en`/dst. Karena itu data Indonesia tetap
tersimpan dan tidak hilang — hanya tidak ditampilkan selama bahasa Inggris.

### Kalau ada teks Indonesia yang muncul lagi

1. Cari di `lang/en/site.php`. Bila kunci ada, masalahnya di tempat pemanggilan.
2. Bila kosong, cari di `app/` dan `resources/views/` untuk string literal
   Indonesia yang belum lewat `__()`.
3. Untuk data, periksa kolom `*_en` di basis data — kolom itu bisa saja masih
   berisi salinan teks Indonesia.
4. Bersihkan cache: `php artisan cache:clear --env=production`, karena
   `CacheHalaman` menyimpan HTML jadi dan bisa menyajikan versi lama.
