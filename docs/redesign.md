# Blueprint & Spesifikasi Redesain Website (Brand Custom)

Dokumen ini berisi panduan redesain website e-commerce & katalog interaktif berbasis estetika *clean performance/automotive UI* (mengadaptasi keunggulan tata letak Spoon Sports) yang disesuaikan dengan identitas brand Anda serta penambahan fitur-fitur eksklusif yang tidak dimiliki oleh website referensi.

---

## 1. Perbandingan Fitur: Referensi vs. Website Kita

| Fitur | Website Referensi (Spoon) | Website Kita (Redesain Baru) |
| :--- | :---: | :---: |
| **Pencarian Produk Berdasar Mobil** | Manual Filter Text | **"My Garage" / VIN & Model Selector** (Tersimpan otomatis) |
| **Prinjau Produk 360° / 3D** | Tidak Ada (Gambar 2D) | **Interactive 3D / AR Product Viewer** |
| **Sistem Keanggotaan & Poin** | Tidak Ada | **Loyalty & Garage Rewards Club** |
| **Live Support / Bantuan Teknis** | Email Form Biasa | **Live Chat & WhatsApp Integration** |
| **Notifikasi Stok / Pre-Order** | Tidak Ada | **Restock Alert & Pre-Order System** |
| **Ulasan & Foto Pelanggan** | Tidak Ada | **Verified Buyer Reviews with Media** |
| **Pengiriman & Mata Uang** | Statis / USD | **Multi-Currency & Real-Time Shipping Estimator** |

---

## 2. Identitas Visual & System Design

### A. Palet Warna (Color Palette)
*Saran warna dapat disesuaikan dengan Hex Code brand Anda.*

* **Primary Accent:** `#0062AD` / Brand Primary (Tombol utama, highlight aktif, aksen header)
* **Secondary Accent:** `#FFF100` / Brand Secondary (Badge promo, tag kustom, status garansi)
* **Dark Neutral:** `#111111` / `#1A1A1A` (Background Footer, Header Top-Bar, Dark Mode)
* **Light Neutral:** `#F8F9FA` / `#FFFFFF` (Background Utama & Kartu Produk)
* **Text Colors:** `#1F2937` (Judul & Body), `#6B7280` (Muted/Secondary Info)

### B. Tipografi (Typography)
* **Heading / Display Font:** `Space Grotesk` atau `Montserrat` (Bold 800, Uppercase) – Memberikan kesan modern, tegap, dan presisi.
* **Body / Content Font:** `Inter` atau `Plus Jakarta Sans` (Regular 400, Medium 500, SemiBold 600) – Sangat nyaman dibaca di layar HP maupun Desktop.

### C. Ikonografi
* Menggunakan paket ikon **Lucide / Feather Icons** dengan *stroke-width* 1.75px yang konsisten.
* Ikon Khusus: `Garage` (Garasi), `ShieldCheck` (Garansi), `Truck` (Estimasi Pengiriman), `MessageCircle` (Live Chat).

---

## 3. Komponen Navigation Bar (Navbar)

Header menggunakan konsep **3-Tier Smart Header**:

```
+-----------------------------------------------------------------------------------------+
| [MY GARAGE: Honda Civic FK8 ▾]  |  [IDR / USD ▾]        [Live Chat]  [Sign In / Register]|
+-----------------------------------------------------------------------------------------+
| [ LOGO BRAND KITA ]  HOME   CATALOG   BRANDS   SERVICES   COMMUNITY   CONTACT           |
|                      [🔍 Cari part, SKU, atau model...]     [❤️ Wishlist]  [🛒 Cart (3)]|
+-----------------------------------------------------------------------------------------+
| ⚡ PROMO: Free Shipping untuk Pembelian di Atas Rp 1.500.000 | Garansi Presisi 100%      |
+-----------------------------------------------------------------------------------------+
```

### Detail Komponen Navbar:
1. **Top Utility Bar:**
   * **My Garage Selector:** Dropdown untuk menyimpan kendaraan pengguna (misal: "Honda Civic FK8"). Begitu dipilih, seluruh katalog otomatis terfilter sesuai mobil pengguna.
   * **Currency Switcher:** Pengubah mata uang otomatis (IDR, USD, SGD).
   * **Auth & Support Quick Link:** Akses cepat ke akun dan bantuan teknis.

2. **Main Header:**
   * **Logo Brand:** Berada di sisi kiri dengan ukuran proporsional.
   * **Menu Utama:** Link navigasi interaktif dengan indikator *active state* garis aksen bawah.
   * **Smart Search Bar:** Fitur pencarian otomatis (*auto-complete*) dengan preview gambar & harga saat mengetik.
   * **Wishlist & Cart Badge:** Ikon favorit dan keranjang belanja interaktif dengan animasi jumlah barang.

3. **Promo Announcement Bar:**
   * Bar informasi berjalan (*ticker/banner*) untuk informasi diskon, pengiriman, atau pengumuman penting.

---

## 4. Modul & Fitur Eksklusif (Website Kita)

Berikut adalah detail fitur yang **diintegrasikan khusus ke dalam layout baru** untuk melengkapi kekurangan pada website referensi Spoon:

### A. Fitur "My Garage" (Vehicle Compatibility Filter)
* **Fungsi:** Memastikan part yang dibeli 100% pas (*fitment guarantee*).
* **Cara Kerja:**
  * Pengguna memasukkan Tahun, Brand, dan Model mobil (atau input Nomor Polisi / VIN).
  * Website menampilkan badge hijau **"✓ Guaranteed Fit for Your Vehicle"** pada halaman detail produk jika part sesuai.

### B. Interactive Product Preview (360° / AR View)
* Pada halaman detail produk & modal *Quick View*, gambar tidak hanya 2D statis.
* Pengguna dapat memutar produk 360 derajat atau melihat simulasi pemasangan melalui *Augmented Reality (AR)* di smartphone.

### C. Fitur Restock Alert & Pre-Order Reservation
* Jika stok produk habis, tombol `Add to Cart` otomatis berubah menjadi `Notify Me` atau `Pre-Order`.
* Pengguna akan menerima notifikasi otomatis via WhatsApp / Email saat produk kembali tersedia.

### D. Integrated Live Chat & WhatsApp Technical Desk
* Floating Button di sudut kanan bawah dengan opsi:
  * *Chat dengan Customer Service*
  * *Konsultasi Spesifikasi Teknis / Fitment Part*

### E. User Review with Photo & Video Upload
* Pembeli yang terverifikasi (*Verified Buyer*) dapat memberikan ulasan Bintang, foto hasil pemasangan di mobil mereka, serta catatan performa part.

---

## 5. Layout Halaman Utama (Homepage Design)

### A. Hero Banner Section
* **Visual:** Carousel gambar/video resolusi tinggi (*Background Auto-Play*).
* **Overlay CTA:**
  * Headline: `HIGH PERFORMANCE PRECISION ENGINEERING`
  * Sub-headline: Teks pengantar keunggulan produk brand kita.
  * Dual Button: `EXPLORE CATALOG` & `SELECT YOUR VEHICLE`.

### B. Integrated Vehicle Garage Selector Card
* Card melayang di bawah Hero Banner yang memudahkan pengguna memilih jenis kendaraan secara instan sebelum masuk ke katalog.

### C. Featured Categories & Brands Grid
* Grid interaktif 3 atau 4 kolom dengan efek *hover zoom* gambar untuk kategori produk utama (Engine Parts, Exhaust, Suspension, Brakes, Accessories).

### D. Featured Products & Live Stock Status
* Tampilan produk unggulan dengan label status stok real-time (*In Stock*, *Low Stock*, *Pre-Order*).

### E. Community / Customer Showcase Section ("Built with Our Parts")
* Galeri grid foto dari kendaraan konsumen yang telah terpasang komponen dari brand kita. Foto dapat diklik untuk langsung membeli part yang ada di dalam foto (*Shop the Look*).

---

## 6. Layout Modal "Quick View" (Versi Ditingkatkan)

Mengambil struktur modal Spoon Sports namun menambahkan fitur pintar:

```
+--------------------------------------------------------------------------+
|  [ Gambar Produk 360° ]         | Nama Produk Lengkap                [X]|
|                                 | SKU: SP-EXH-091                        |
|  [Thumb 1] [Thumb 2] [360°]     | ⭐⭐⭐⭐⭐ (24 Ulasan)                 |
|                                 |                                        |
|  🟢 IN STOCK                    | Rp 4.500.000                           |
|  ✓ Fitment for: Civic FK8       | 🚚 Estimasi tiba: 2-3 Hari            |
|                                 | ------------------------------------   |
|  <  > (Navigasi Slide)          | Opsi Variasi: [ Pilih Ukuran / Warna ▾]|
|                                 | Jumlah: [ - ]  1  [ + ]                |
|                                 |                                        |
|                                 | [ 🛒 ADD TO CART                     ] |
|                                 | [ 💳 BUY NOW (DIRECT CHECKOUT)       ] |
|                                 | [ ❤️ Save to Wishlist                ] |
+--------------------------------------------------------------------------+
```

---

## 7. Komponen Footer (Footer Redesign)

Footer 4 Kolom Gelap (`#111111`) yang Rapi & Informatif:

```
+-----------------------------------------------------------------------------------------+
| [LOGO BRAND KITA]     SHOP CATEGORIES       CUSTOMER SUPPORT       ABOUT & COMMUNITY    |
| Alamat Workshop / HQ  - Engine Components   - Order Tracking       - Our Story          |
| No. Telp / WhatsApp   - Exhaust Systems     - Warranty Claim       - Dealer Network     |
| Email Support         - Suspension Kit      - Shipping Policy      - Blog & News        |
| Jam Operasional       - Braking System      - FAQ                  - Member Rewards     |
+-----------------------------------------------------------------------------------------+
| Metode Pembayaran: [VISA] [Mastercard] [BCA] [Mandiri] [QRIS] [GoPay]                   |
| Ekspedisi: [JNE] [TIKI] [Sicepat] [DHL]                                                 |
+-----------------------------------------------------------------------------------------+
| Follow Us: [FB] [IG] [YT] [TikTok]              Copyright © 2026 Brand Kita. All Rights. |
+-----------------------------------------------------------------------------------------+
```

---

## 8. Panduan Layout Responsif (Mobile Friendly)

* **Mobile Sticky Bottom Navigation Bar:**
  Saat dibuka dari Smartphone, muncul bar navigasi tetap di bagian bawah layar:
  `[ 🏠 Home ]` | `[ 🚗 My Garage ]` | `[ 🔍 Search ]` | `[ ❤️ Wishlist ]` | `[ 🛒 Cart (2) ]`
* **Filter Off-Canvas:**
  Sidebar filter pada katalog otomatis berubah menjadi tombol *Slide-up Drawer* di layar mobile.