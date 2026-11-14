<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Enums\TipeKupon;
use Throwable;

/**
 * Satu harga, satu mata uang: Dolar.
 *
 * ── MENGAPA DOLAR ──────────────────────────────────────────────────────
 * Pasarnya luar negeri. Pembeli di Singapura, Australia, atau Amerika tidak
 * memesan ban dalam rupiah, dan angka tujuh digit dengan titik ribuan gaya
 * Indonesia membuat mereka berhenti menghitung sebelum sampai di tombol beli.
 *
 * ── HARGA DISIMPAN PER MATA UANG, BUKAN DIKONVERSI SAAT DITAMPILKAN ────
 * `products.base_price` tetap rupiah dan tetap menjadi harga INDUK: seluruh
 * perhitungan pesanan (subtotal, pajak, diskon, ongkir, laporan) berjalan di
 * atasnya. Perubahan di sini HANYA pada cara harga ditampilkan.
 *
 * Tetapi menampilkan hasil konversi kurs saat render punya dua cacat:
 *
 *   1. Harga berubah setiap kali kurs bergerak, padahal tidak ada yang
 *      mengubah harga. Pembeli melihat $265 hari ini dan $271 besok untuk
 *      ban yang sama.
 *   2. Angka yang tampil tidak sama dengan angka yang ditagih PayPal —
 *      gateway menagih nominal yang dikirim, dan nominal itu berasal dari
 *      rupiah.
 *
 * Karena itu nilainya DISALIN ke dolar di `base_price_usd` /
 * `price_adjustment_usd` sekali (lihat `salinKeDolar()`), lalu dibaca dari
 * sana. Kurs tetap dipakai, tetapi sebagai alat untuk MENYALIN dan sebagai
 * cadangan bila kolomnya kosong — bukan sebagai harga jual.
 *
 * ── CADANGAN, BUKAN ANGKA KARANGAN ─────────────────────────────────────
 * Bila `base_price_usd` kosong DAN kurs tidak dapat dihubungi, `dariRupiah()`
 * mengembalikan null. Pemanggil wajib punya jalan keluar; menampilkan angka
 * karangan berarti menagih harga yang tidak pernah disetujui pemilik toko.
 */
class HargaDolar
{
    /** Mata uang tampilan. Satu nilai, supaya tidak ada dua sumber kebenaran. */
    public const MATA = 'USD';

    /** Kurs cadangan bila sumber daring tidak dapat dihubungi sama sekali. */
    private const KURS_CADANGAN = 16000.0;

    /**
     * Nominal dolar untuk sebuah harga rupiah.
     *
     * Urutan: kolom USD yang sudah tersalin → kurs hari ini → null.
     */
    public static function dariRupiah(float|int|string|null $rupiah, float|int|string|null $usd = null): ?float
    {
        // Kolom USD yang sudah terisi selalu menang: itulah harga yang
        // disetujui pemilik toko, dan mengubahnya karena kurs berarti harga
        // berubah sendiri.
        if ($usd !== null && (float) $usd > 0) {
            return round((float) $usd, 2);
        }

        $nilai = (float) $rupiah;

        if ($nilai <= 0) {
            return 0.0;
        }

        return round($nilai / self::kurs(), 2);
    }

    /**
     * Harga rupiah per 1 dolar.
     *
     * Memakai Kurs (tersimpan 6 jam). Bila semua sumber gagal, memakai kurs
     * cadangan — lebih baik daripada harga yang hilang seluruhnya dari
     * halaman, dan nilai USD-nya tetap stabil karena tersalin ke basis data.
     */
    public static function kurs(): float
    {
        try {
            $data = Kurs::usd();
        } catch (Throwable) {
            $data = null;
        }

        $kurs = (float) ($data['kurs'] ?? 0);

        return $kurs > 0 ? $kurs : self::KURS_CADANGAN;
    }

    /** Nominal rupiah untuk input harga dalam dolar di panel. */
    public static function keRupiah(float|int|string|null $usd): float
    {
        return round((float) $usd * self::kurs(), 2);
    }

    /** Format dolar: "$1,250.00" — koma ribuan, titik desimal. */
    public static function format(float|int|string|null $nominal): string
    {
        return '$'.number_format((float) $nominal, 2, '.', ',');
    }

    /**
     * Harga dolar dari sebuah produk: varian aktif termurah.
     *
     * Ini pengganti `Product::hargaMulai()` untuk tampilan. Dipisah, bukan
     * ditambahkan ke model, karena model itu juga dipakai panel admin dan
     * laporan — dan di sana rupiah tetap satu-satunya angka yang sah.
     */
    public static function mulai(Product $produk): float
    {
        $termurah = null;

        foreach ($produk->variants->where('is_active', true) as $varian) {
            $nilai = self::dariVarian($varian);

            if ($termurah === null || $nilai < $termurah) {
                $termurah = $nilai;
            }
        }

        if ($termurah !== null) {
            return $termurah;
        }

        return self::dariRupiah($produk->base_price, $produk->base_price_usd) ?? 0.0;
    }

    /**
     * Harga dolar sebuah varian.
     *
     * `ProductVariant::hargaAkhirUsd()` mengembalikan null bila produk belum
     * punya harga USD sama sekali. Di situlah cadangan kurs dipakai, supaya
     * varian yang lupa disalin tidak menghilang dari daftar harga.
     */
    public static function dariVarian(ProductVariant $varian): float
    {
        $usd = $varian->hargaAkhirUsd();

        if ($usd !== null) {
            return $usd;
        }

        return self::dariRupiah($varian->hargaAkhir()) ?? 0.0;
    }

    /**
     * Format harga dolar sebuah produk, siap tempel di Blade.
     *
     * `$mulai` menambahkan awalan "dari" untuk produk bervarian lebih dari
     * satu: pembeli yang membaca "$280" untuk produk yang varian termahalnya
     * $410 akan memesan dengan harga di kepalanya sendiri.
     */
    public static function teksProduk(Product $produk, bool $denganAwalan = true): string
    {
        $teks = self::format(self::mulai($produk));

        $banyakVarian = $produk->variants
            ->where('is_active', true)
            ->pluck('id')
            ->count() > 1;

        if ($denganAwalan && $banyakVarian) {
            return self::labelDari().' '.$teks;
        }

        return $teks;
    }

    /** Kata "dari" dalam bahasa halaman. */
    public static function labelDari(): string
    {
        return app()->getLocale() === 'en' ? 'from' : 'mulai';
    }

    // ════════════════════════════════════════════════════════════════════
    // KUPON
    //
    // `coupons.min_spend`, `value` (untuk kupon nominal), `max_discount`, dan
    // angka `min` di tangga bertingkat semuanya tersimpan dalam RUPIAH —
    // itu nilai yang diisi pemilik toko di panel, dan tidak diubah di sini:
    // mengubahnya berarti menulis ulang data untuk alasan tampilan.
    //
    // Tetapi subtotal yang dibandingkan SUDAH dalam dolar. Kupon "minimal
    // belanja Rp 500.000" yang dibandingkan dengan subtotal $235 tidak akan
    // pernah lolos, dan kupon nominal Rp 150.000 akan memotong $150.000.
    // Karena itu setiap angka dari kupon diterjemahkan ke dolar LEBIH DULU,
    // lalu logika kupon yang sudah ada berjalan seperti biasa.
    // ════════════════════════════════════════════════════════════════════

    /** Batas minimal belanja kupon, dalam dolar. */
    public static function kuponMinSpend(Coupon $kupon): float
    {
        return self::dariRupiah($kupon->min_spend) ?? 0.0;
    }

    /**
     * Nilai kupon (`value`) dalam dolar.
     *
     * Kupon persen tidak diubah — 10% tetap 10%. Yang dikonversi hanya kupon
     * nominal, karena angkanya adalah uang, bukan perbandingan.
     */
    public static function kuponNilai(Coupon $kupon): float
    {
        if ($kupon->type === TipeKupon::Persen) {
            return (float) $kupon->value;
        }

        return self::dariRupiah($kupon->value) ?? 0.0;
    }

    /** Batas potongan biasa (`max_discount` berupa angka), dalam dolar. */
    public static function kuponMaks(Coupon $kupon): ?float
    {
        $nilai = $kupon->max_discountBiasa();

        return $nilai === null ? null : self::dariRupiah($nilai);
    }

    /**
     * Tangga kupon bertingkat dengan ambang `min` dalam dolar.
     *
     * Persentasenya dibiarkan; hanya `min` yang diterjemahkan, karena itulah
     * yang dibandingkan dengan subtotal.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function kuponTangga(Coupon $kupon): array
    {
        return array_map(static function (array $anak): array {
            if (isset($anak['min'])) {
                $anak['min'] = self::dariRupiah($anak['min']) ?? 0.0;
            }

            return $anak;
        }, KuponLanjutan::tangga($kupon));
    }

    /**
     * Potongan kupon dalam dolar untuk subtotal dolar.
     *
     * Meniru Coupon::hitungPotongan() tetapi memakai angka yang sudah
     * diterjemahkan, sehingga hasilnya sebanding dengan subtotal dan ongkir
     * yang juga sudah dolar.
     */
    public static function kuponPotongan(Coupon $kupon, float $subtotal, float $ongkir = 0.0): float
    {
        // Gratis ongkir TIDAK memotong subtotal — ongkirnya yang dinolkan,
        // ditangani terpisah oleh pemanggil. Tanpa cabang ini, kupon
        // free_shipping memotong sebesar ongkir dari subtotal juga, sehingga
        // pembeli mendapat potongan dua kali. Bug ini sempat lolos karena
        // versi pertama fungsi ini memanggil hitung() untuk semua tipe.
        if ($kupon->type === TipeKupon::GratisOngkir) {
            return 0.0;
        }

        $tangga = self::kuponTangga($kupon);

        if ($tangga !== []) {
            $terpilih = null;

            foreach ($tangga as $anak) {
                $min = (float) ($anak['min'] ?? 0);

                if ($subtotal >= $min && ($terpilih === null || $min >= (float) ($terpilih['min'] ?? 0))) {
                    $terpilih = $anak;
                }
            }

            if ($terpilih !== null) {
                return round($subtotal * ((float) ($terpilih['persen'] ?? 0) / 100), 2);
            }
        }

        return $kupon->type->hitung(
            $subtotal,
            self::kuponNilai($kupon),
            self::kuponMaks($kupon),
            $ongkir,
        );
    }
}
