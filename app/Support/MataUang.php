<?php

namespace App\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Mata uang tampilan yang dipilih pengunjung.
 *
 * ── RUPIAH TETAP HARGA INDUK ───────────────────────────────────────────
 * Yang disimpan di `products.base_price` adalah rupiah, dan seluruh
 * perhitungan pesanan (subtotal, pajak, diskon, ongkir, laporan penjualan)
 * tetap berjalan dalam rupiah. Kelas ini hanya menentukan BAGAIMANA harga itu
 * DITAMPILKAN dan mata uang apa yang DITAGIH.
 *
 * Alasannya bukan kemalasan: bila pesanan disimpan dalam dua mata uang,
 * setiap laporan harus memilih kurs, dan angka di dashboard berubah setiap
 * hari tanpa ada transaksi. Satu mata uang induk menghindari itu.
 *
 * ── USD DARI HARGA MANUAL, BUKAN KONVERSI ──────────────────────────────
 * `products.base_price_usd` diisi pemilik toko sendiri. Bila kosong, USD
 * TIDAK dihitung dari kurs — pembeli melihat harga rupiah sebagai gantinya.
 * Menampilkan hasil konversi otomatis berarti menagih harga yang tidak pernah
 * disetujui pemilik toko, dan selisihnya ditanggungnya.
 *
 * `Kurs` tetap dipakai, tetapi hanya sebagai SARAN di panel admin saat
 * mengisi harga USD — bukan sebagai penentu harga jual.
 */
class MataUang
{
    /** Cookie pilihan mata uang. */
    public const COOKIE = 'iksan_mata_uang';

    public const IDR = 'IDR';

    public const USD = 'USD';

    /** Mata uang yang didukung. */
    public const DIDUKUNG = [self::IDR, self::USD];

    /** Diingat selama satu permintaan. */
    private static ?string $terpilih = null;

    /**
     * Mata uang yang sedang dipakai pengunjung.
     *
     * Urutannya: parameter ?mata= (tautan yang dibagikan) → cookie → bawaan
     * dari pengaturan toko.
     */
    public static function sekarang(?Request $request = null): string
    {
        if (self::$terpilih !== null) {
            return self::$terpilih;
        }

        $request ??= request();

        $dariTautan = strtoupper(trim((string) $request->query('mata', '')));
        if (in_array($dariTautan, self::DIDUKUNG, true)) {
            return self::$terpilih = $dariTautan;
        }

        $dariCookie = strtoupper(trim((string) $request->cookie(self::COOKIE, '')));
        if (in_array($dariCookie, self::DIDUKUNG, true)) {
            return self::$terpilih = $dariCookie;
        }

        return self::$terpilih = self::bawaan();
    }

    /** Mata uang bawaan dari pengaturan toko. */
    public static function bawaan(): string
    {
        try {
            $nilai = strtoupper(trim((string) Pengaturan::instance()->teks('mata_uang_bawaan', self::USD)));

            return in_array($nilai, self::DIDUKUNG, true) ? $nilai : self::USD;
        } catch (Throwable) {
            return self::USD;
        }
    }

    /** Apakah pengunjung sedang memakai USD. */
    public static function usd(): bool
    {
        return self::sekarang() === self::USD;
    }

    /** Melupakan pilihan yang diingat. Dipakai test antar-kasus. */
    public static function lupakan(): void
    {
        self::$terpilih = null;
    }

    /**
     * Simbol untuk sebuah mata uang.
     *
     * Toko menjual dalam dolar, jadi tidak ada lagi simbol yang dapat diubah
     * dari panel: `currency_symbol` sudah dihapus. Rupiah tetap dikenali untuk
     * data lama, dengan simbol bawaannya.
     */
    public static function simbol(string $mata): string
    {
        return $mata === self::USD ? '$' : 'Rp';
    }

    /**
     * Format nominal dalam mata uang tertentu.
     *
     * Pemisah ribuan mengikuti kebiasaan pembacanya: rupiah memakai titik
     * (1.250.000), dolar memakai koma (1,250.00). Menukar keduanya membuat
     * angka terbaca salah — "$ 1.250.000" dibaca satu juta dua ratus lima
     * puluh ribu dolar.
     */
    public static function format(float|int|string|null $nominal, ?string $mata = null): string
    {
        $nilai = (float) $nominal;
        $mata ??= self::sekarang();

        return $mata === self::USD
            ? self::simbol($mata).number_format($nilai, 2, '.', ',')
            : self::simbol($mata).' '.number_format($nilai, 0, ',', '.');
    }
}
