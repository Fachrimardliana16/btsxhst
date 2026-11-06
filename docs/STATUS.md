# Status Proyek — Bites Exhaust

Terakhir disimpan: 2026-11-06
Commit: `9c512a7` · Tag: `v1.0-live`

---

## Keadaan saat ditinggalkan

**Situs LIVE dan sehat** di <https://bitesexhaust.com> — terverifikasi 200 pada
10/10 halaman publik dan 15/15 halaman panel admin, semuanya berbahasa Inggris.

| Komponen | Status | Catatan |
|---|---|---|
| Cloudflare Tunnel `bites` | **AKTIF** | PID berjalan, 4 koneksi Singapore |
| nginx `127.0.0.1:8200` | AKTIF | conf `bites.conf` |
| php-cgi `9300-9303` | AKTIF | 4 proses |
| Database | `database-produksi.sqlite` | 24 produk, 50 varian |

---

## ⚠️ Yang WAJIB diketahui saat melanjutkan

### 1. Tumpukan mati setelah komputer restart

Tunnel, nginx, dan php-cgi **tidak hidup sendiri**. Setelah reboot:

```powershell
powershell -ExecutionPolicy Bypass -File D:\Projects\iksanknalpot2\tools\nyalakan-produksi.ps1
```

Hentikan: tambahkan `-Stop`. Skrip ini juga membangun ulang cache config,
route, view, dan event dengan `--env=production`.

### 2. Antrean belum jalan — ini yang paling mendesak

`QUEUE_CONNECTION=database`, tetapi tidak ada `queue:work`. Artinya:

- Notifikasi pesanan (surel/WhatsApp) **menumpuk** di tabel `jobs`
- Pemrosesan gambar produk yang diunggah **tidak pernah selesai**

Jalankan di jendela terpisah:

```powershell
cd D:\Projects\iksanknalpot2
& "$env:LOCALAPPDATA\php85\php.exe" artisan queue:work --env=production
```

Untuk produksi sungguhan, pasang sebagai Windows Service (NSSM) supaya
otomatis hidup dan restart sendiri.

### 3. Surel tidak terkirim

`MAIL_MAILER=log` — surel hanya ditulis ke `storage/logs/`, tidak dikirim.
Isi kredensial SMTP asli di `.env.production` sebelum mengandalkan notifikasi.

### 4. Kata sandi admin masih default

`admin@bitesexhaust.test` dan `staff@bitesexhaust.test` memakai sandi bawaan
seeder. **Ganti sebelum toko menerima pesanan sungguhan.** Keduanya sudah
punya 2FA aktif.

### 5. "Always Use HTTPS" belum aktif

HTTP masih menjawab 200, belum redirect ke HTTPS. Aktifkan di dashboard
Cloudflare: SSL/TLS → Edge Certificates → Always Use HTTPS. Tidak mendesak
(HSTS aktif, semua tautan internal sudah HTTPS), tetapi lebih rapi bila aktif.

---

## Yang sudah selesai

### Produksi

- `.env.production` terpisah dari pengembangan
- `public/produksi.php` — memuat `.env.production` karena php-cgi tidak
  menerima `--env=production`
- nginx `bites.conf` (port 8200), `/storage/` dilayani langsung karena
  volume D: exFAT tidak mendukung symlink
- Tunnel bernama, alamat tetap

### Rebranding

Iksan Knalpot → **Bites Exhaust** di seluruh antarmuka, data, manifest PWA,
email, dan media sosial.

### Bahasa Inggris tunggal

Seluruh situs dan panel admin berbahasa Inggris lewat sistem terjemahan
(`lang/en/site.php`), bukan teks hardcoded. Data Indonesia tetap tersimpan
di kolom `*_id` sebagai rujukan.

### UI/UX

Chevron dropdown, footer rata, galeri sederhana, jarak bar keranjang ↔ bottom
nav, nomor section 01–07, duplikasi label stok dihapus, halaman produk
dirapikan, brand auto-scale.

---

## Berkas penting

| Berkas | Isi |
|---|---|
| `docs/PRODUKSI.md` | Panduan lengkap produksi (11 bagian) |
| `tools/nyalakan-produksi.ps1` | Nyalakan/hentikan seluruh tumpukan |
| `tools/siapkan-tunnel.ps1` | Buat tunnel + DNS (sekali jalan) |
| `tools/cdp_audit.py` | Ukur tata letak lewat Chrome CDP |
| `conf/bites.conf` | nginx (`%LOCALAPPDATA%\nginx\nginx-1.28.0\conf\`) |
| `~/.cloudflared/bites.yml` | Konfigurasi tunnel |

---

## Titik pulih (backup)

| Lokasi | Isi | Ukuran |
|---|---|---|
| `D:\backup-iksanknalpot2-20261104\sumber.tgz` | Seluruh kode (tanpa vendor/node_modules) | 17 MB |
| `D:\backup-iksanknalpot2-20261104\database-produksi.sqlite` | Database produksi | 2,2 MB |
| `D:\Projects\iksanknalpot2\.git` | Riwayat git, tag `v1.0-live` | — |

⚠️ **Foto produk tidak ada di git** (`storage/app/public`, ~496 MB). Hanya ada
di disk dan tidak masuk snapshot karena terlalu besar. Kalau folder itu
terhapus, foto hilang. Pertimbangkan menyalinnya ke tempat terpisah.

---

## Cara melanjutkan

1. Nyalakan tumpukan (perintah di atas)
2. Cek situs: <https://bitesexhaust.com>
3. Baca `docs/PRODUKSI.md` bila menyentuh nginx atau tunnel
4. Kalau ada teks Indonesia muncul lagi, lihat bagian 11 di dokumen itu —
   biasanya karena kolom `*_en` di database masih berisi teks Indonesia,
   atau cache halaman belum dibersihkan
