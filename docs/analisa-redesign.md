# Analisa redesign.md — iksanknalpot2

Dokumen sumber: `docs/redesign.md` (blueprint hasil adaptasi estetika Spoon Sports).
Analisa ini memetakan setiap butir ke kondisi kode di `D:\Projects\iksanknalpot2`
per **30 Oktober 2026** — sebelum satu baris pun diubah.

Kesimpulan singkat: **±35% blueprint sudah ada di kode**, **±40% perlu dibangun
baru**, dan **±25% tidak cocok dengan bisnis toko** (knalpot motor, bukan mobil
dan bukan brand internasional) sehingga harus diterjemahkan, bukan ditiru.

---

## 1. Kondisi kode sekarang (hasil pembacaan, bukan asumsi)

| Aspek | Kenyataan di repo |
|---|---|
| Framework | Laravel 12 + Livewire 3 + Tailwind 4 (CSS-first, `@import`), Alpine |
| Model | 25 model: Product, ProductVariant, ProductMedia, Category, Order, OrderItem, Payment, Coupon, Address, User, ProductLike, HeroSlide, Faq, Setting, Cart/CartItem, ContactMessage, dll. |
| Produk | `products`: kategori, SKU, judul/deskripsi dua bahasa, harga dasar, berat, unggulan, publikasi, view/sold count. **Tidak ada** kolom kendaraan, rating, atau jumlah ulasan. |
| Varian | `product_variants`: nama dua bahasa, penyesuaian harga, stok, stok terpesan, berat, urutan. |
| Media | `product_media` + migrasi `tambah_media_3d_audio` (kolom `path_media`, `durasi_detik`) → **sudah ada tempat untuk media video/3D**, belum dipakai untuk 360°. |
| Halaman front | `/`, `/produk`, `/produk/{slug}`, `/kategori/{slug}`, `/keranjang`, `/checkout`, `/lacak`, `/tentang`, `/kontak`, `/halaman/{slug}` |
| Keranjang | `App\Services\Keranjang` + komponen `jumlah-keranjang`, `tambah-keranjang-cepat` |
| Wishlist | **Tidak ada.** Yang ada `ProductLike` (suka produk per sesi/pengguna) — fondasinya sudah tepat untuk wishlist. |
| Mata uang | `App\Support\Kurs` (kurs USD→IDR, cadangan 16.000) + `components/harga-asing` → **baru perkiraan USD, belum multi-currency**. |
| Internasional | Migrasi `tambah_pengiriman_luar_negeri` (`shipping_country`, `shipping_currency`, `shipping_is_intl`, `shipping_quoted_at`) → **fondasi ongkir sudah ada**, belum ada estimator di halaman produk. |
| Ulasan | **Tidak ada tabel/model.** Login pelanggan ada, tetapi riwayat pesanan per akun belum dijadikan dasar "verified buyer". |
| Navigasi | Baru dirombak: sidebar kiri saat di puncak + bilah melayang saat digulir (beranda), bilah biasa di halaman lain. |
| Hero | `partials/front/hero-race.blade.php`, gambar mengisi satu layar, judul per slide, kendali slide di kaki. |
| Bottom nav | `partials/front/bottom-nav` — 4 slot: Home, Produk, Keranjang, Kontak. |

---

## 2. Peta butir blueprint → kode

Legenda: **ADA** = sudah jalan dan cukup disetel · **SEBAGIAN** = fondasi ada, fitur belum
· **BARU** = belum ada sama sekali · **TAK COCOK** = bertentangan dengan produk/bisnis.

### 2.1 Tabel fitur (bagian 1)

| Fitur blueprint | Status | Catatan |
|---|---|---|
| My Garage / VIN & model selector | **BARU** | Tidak ada relasi kendaraan sama sekali. Butuh tabel `vehicles` (merek→model→tahun) + pivot `product_vehicle`, atau minimal 3 kolom teks di product. |
| 3D / AR viewer | **SEBAGIAN** | `product_media` sudah punya `path_media`, jadi file glb/urutan gambar 360° bisa diunggah. Viewer-nya belum ada (butuh `<model-viewer>` atau pemutar 360°). |
| Loyalty & poin | **BARU** | Butuh transaksi poin + ambang penukaran; dampaknya ke checkout. |
| Live chat + WhatsApp | **SEBAGIAN** | Tombol WhatsApp melayang sudah ada (baru diperbaiki). Live chat belum. |
| Restock alert / pre-order | **BARU** | Stok per varian sudah ada, tombol habis sudah dinonaktifkan; pemberitahuan belum. |
| Ulasan berfoto (verified buyer) | **BARU** | Perlu tabel `reviews` + moderasi admin + unggah media. |
| Multi-currency & ongkir real-time | **SEBAGIAN** | Kurs USD sudah ada; switcher IDR/USD/SGD dan estimator biaya perlu API/logika baru. |

### 2.2 Identitas visual (bagian 2)

| Butir | Status | Catatan |
|---|---|---|
| Palet `#0062AD` / `#FFF100` | **KONFLIK** | Sekarang aksen toko `#c8102e` (merah) + kuningan `#b58a35`. Mengganti palet = menyentuh `tokens.css` dan seluruh tampilan; perlu keputusan pemilik. |
| Font Space Grotesk + Inter | **KONFLIK** | Sekarang **Barlow** + **Barlow Condensed** (+ IBM Plex Mono) yang di-host sendiri. Mengganti font berarti mengunduh & menyimpan 6–8 berkas woff2 baru dan bocor ke LCP. |
| Ikon Lucide/Feather | **ADA (beda paket)** | Proyek memakai komponen `<x-ikon>` buatan sendiri dengan 40+ ikon. Menambah ikon Garage/ShieldCheck/Truck/MessageCircle cukup menambah satu baris di komponen itu. |

### 2.3 Navbar 3-tier (bagian 3)

| Butir | Status |
|---|---|
| Top utility bar (garage, mata uang, akun) | **BARU** — sekarang utilitas: bahasa, tema, cari, keranjang. |
| Main header + menu + search | **ADA** (baru dirombak; menu di tengah saat digulir). |
| Smart search ber-autocomplete berpreview | **SEBAGIAN** — `partials/front/pencarian` + Livewire `Pencarian` sudah mencari; preview gambar & harga perlu ditambah. |
| Ticker promo | **BARU** — `Pengaturan` sudah punya banyak kunci teks, jadi teks promo bisa ditambahkan tanpa tabel baru. |

### 2.4 Homepage (bagian 5)

| Butir | Status |
|---|---|
| Hero carousel + dual CTA | **ADA** — `hero-race` dengan `$slides`, dua tombol per slide dari admin. |
| Garage selector card di bawah hero | **BARU** (bergantung pada fitur My Garage). |
| Featured categories grid | **ADA** — section 03 beranda, 6 kartu kategori. |
| Featured products + status stok real-time | **ADA (stok) / SEBAGIAN (label)** — `kartu-produk` menampilkan "Habis"/varian; label Low Stock / Pre-Order belum. |
| Community showcase "Shop the Look" | **BARU** — tidak ada tabel foto kendaraan pelanggan. |

### 2.5 Footer (bagian 7)

| Butir | Status |
|---|---|
| 4 kolom gelap | **ADA** — footer `carbon-panel` 4 kolom, akordeon di ponsel. |
| Metode bayar & ekspedisi (logo) | **SEBAGIAN** — data rekening manual ada di `manual_bank_accounts`; logo merek belum. |
| Sosial + hak cipta | **ADA**. |

### 2.6 Mobile (bagian 8)

| Butir | Status |
|---|---|
| Bottom nav 5 slot (Home, Garage, Search, Wishlist, Cart) | **SEBAGIAN** — sekarang 4 slot: Home, Produk, Keranjang, Kontak. |
| Filter off-canvas | **ADA** — `ProdukDaftar` punya filter; perilaku drawer di ponsel perlu dicek ulang. |

---

## 3. Ketidakcocokan yang harus diputuskan lebih dulu

Bagian ini bukan soal teknis, dan **tidak boleh kukerjakan tanpa keputusanmu**:

1. **Mobil vs motor.** Seluruh bagian My Garage di blueprint berbicara VIN dan
   tahun mobil (Civic FK8). Toko menjual knalpot **motor**. Artinya:
   *"My Garage"* harus menjadi *"Motor Saya"* dengan merek motor (Honda, Yamaha,
   Suzuki, Kawasaki) → model (NMAX, PCX, Aerox…) → tahun. VIN mobil tidak relevan;
   yang setara adalah nomor rangka/mesin (opsional).
2. **Kategori produk.** Blueprint menyebut Engine Parts, Suspension, Braking,
   Brake System. Katalog berisi **Full System, Slip-on, Header, Muffler**.
   Grid kategori memakai kategori yang ada, bukan daftar blueprint.
3. **Palet & font.** Merah + Barlow sekarang adalah identitas yang sudah dipakai.
   Mengganti ke biru + Space Grotesk akan mengubah seluruh situs, bukan hanya
   halaman baru. Perlu keputusan: (a) tetap palet sekarang, (b) hanya halaman
   beranda baru yang memakai palet blueprint, atau (c) ganti total.
4. **Loyalty, AR/3D, ulasan, multi-currency** adalah fitur besar yang menyentuh
   database + admin + checkout. Masing-masing pantas jadi pekerjaan tersendiri,
   bukan bagian dari "redesain tampilan".

---

## 4. Urutan kerja yang kusarankan

Disusun agar setiap tahap bisa dilihat hasilnya dan tidak ada tahap yang
menggantung pada fitur yang belum disetujui.

**Tahap 1 — Fondasi tampilan (tanpa tabel baru, aman untuk DB)**
1. Tambah kunci teks promo di `Pengaturan` + bar ticker di bawah navbar.
2. Bottom nav ponsel 5 slot (ganti "Kontak" jadi "Cari", tambah "Suka").
3. Label stok bertingkat di `kartu-produk`: Habis / Stok terbatas / Pre-order
   (murni dari `stock_quantity` yang sudah ada).
4. Footer: baris metode pembayaran & ekspedisi (teks/logo statis dari pengaturan).

**Tahap 2 — Motor Saya (fitur baru, tabel baru)**
5. Tabel `vehicles` (merek, model, tahun) + tabel pivot `product_vehicle`.
6. Selector di area top-bar; memilih motor menyaring `/produk`.
7. Badge "Pas untuk motor Anda" di halaman detail (dari hasil pivot).

**Tahap 3 — Ulasan pelanggan (tabel baru)**
8. `reviews` (produk, order item, bintang, teks, media, status verifikasi).
9. Form ulasan di halaman sukses pesanan + moderasi di admin.
10. Bintang + hitungan ulasan di kartu dan detail produk.

**Tahap 4 — Sisanya (per fitur, sesuai kebutuhan bisnis)**
11. Restock alert (butuh kolom permintaan + notifikasi email/WA).
12. Multi-currency (butuh sumber kurs & label harga di seluruh tampilan).
13. 3D/360 viewer (butuh produksi aset glb/360° per produk).
14. Loyalty poin.

---

## 5. Catatan teknis yang akan kuhindari

- **Tidak** menambah tabel/kolom tanpa migrasi yang bisa dibalik.
- **Tidak** mengganti font/palet global tanpa persetujuan — dampaknya ke semua halaman.
- **Tidak** memakai angka karangan (rating, jumlah ulasan, "5.000+ pengguna") agar
  halaman tidak memuat klaim palsu.
- **Tidak** menambah komponen Livewire baru per kartu produk: pelajaran dari
  halaman produk sebelumnya (dua tombol tambah-keranjang) — semua badge baru
  harus dirender server sekali, bukan komponen per kartu.
- Keranjang, checkout, dan alur pembayaran tidak disentuh kecuali diminta.

---

## 6. Yang kubutuhkan darimu sebelum Tahap 1 dikerjakan

1. **Motor atau mobil?** Kuasumsikan motor (isi katalog memang motor) — setuju?
2. **Palet & font:** tetap merah + Barlow, atau ikut blueprint (biru + Space Grotesk)?
3. **Tahap 1 saja dulu**, atau langsung lanjut Tahap 2 (Motor Saya)?
