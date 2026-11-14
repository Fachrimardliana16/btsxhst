<?php

namespace App\Support;

/**
 * Barcode Code 128-B sebagai SVG, tanpa pustaka tambahan.
 *
 * Label pengiriman butuh barcode yang dapat dipindai kurir. dompdf tidak
 * menyediakan pembuat barcode, dan menambahkan pustaka baru hanya untuk satu
 * label terlalu berat. Code 128-B cukup di sini karena nomor resi kurir hanya
 * memakai huruf, angka, dan tanda hubung.
 *
 * Hasilnya SVG (bukan PNG) supaya tidak bergantung pada ekstensi GD di PHP
 * web: bar hanyalah kotak hitam, dan dompdf sudah dapat menggambar SVG.
 */
class Barcode128
{
    /**
     * Pola lebar bar/spasi untuk nilai 0-106. Digit bergantian bar dan spasi,
     * dimulai dari bar. Indeks 106 adalah pola stop yang panjangnya tujuh.
     *
     * @var array<int, string>
     */
    private const POLA = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * SVG barcode, atau null bila teks tidak dapat dikodekan.
     *
     * @param  float  $lebarMm  lebar gambar akhir dalam milimeter
     * @param  float  $tinggiMm  tinggi bar dalam milimeter
     */
    public static function svg(string $teks, float $lebarMm = 84.0, float $tinggiMm = 14.0): ?string
    {
        $teks = trim($teks);

        if ($teks === '' || strlen($teks) > 40) {
            return null;
        }

        $nilai = [];

        foreach (str_split($teks) as $huruf) {
            $kode = ord($huruf);

            // Code 128-B hanya mencakup ASCII 32-126.
            if ($kode < 32 || $kode > 126) {
                return null;
            }

            $nilai[] = $kode - 32;
        }

        $cek = self::START_B;

        foreach ($nilai as $posisi => $angka) {
            $cek += $angka * ($posisi + 1);
        }

        $deret = array_merge([self::START_B], $nilai, [$cek % 103, self::STOP]);

        // Modul = satuan lebar terkecil. 10 modul kosong di kiri dan kanan
        // adalah quiet zone yang dibutuhkan pemindai.
        $modul = 20;

        foreach ($deret as $angka) {
            $modul += array_sum(array_map('intval', str_split(self::POLA[$angka])));
        }

        // Kanvas dalam poin (pt), sama dengan satuan yang dipakai dompdf.
        //
        // dompdf menghitung ukuran gambar dari viewBox-nya dan memperlakukan
        // angka di sana sebagai poin, bukan piksel CSS. Dengan viewBox 600x100
        // barcode tercetak 600pt (211 mm) — meluap keluar kertas label. viewBox
        // yang sudah dalam pt membuat ukuran intrinsiknya tepat 84x14 mm.
        $lebarPt = $lebarMm * 72 / 25.4;
        $tinggiPt = $tinggiMm * 72 / 25.4;
        $skala = $lebarPt / $modul;
        $x = 10.0;
        $kotak = [];

        foreach ($deret as $angka) {
            $pola = self::POLA[$angka];

            foreach (str_split($pola) as $i => $lebar) {
                $lebar = (int) $lebar;

                if ($i % 2 === 0) {
                    $kotak[] = sprintf(
                        '<rect x="%.3F" y="0" width="%.3F" height="%.3F"/>',
                        $x * $skala,
                        $lebar * $skala,
                        $tinggiPt,
                    );
                }

                $x += $lebar;
            }
        }

        // Angka TANPA satuan.
        //
        // dompdf memperlakukan angka bersatuan pada SVG sebagai piksel CSS
        // ("238pt" dicetak 63 mm), sedangkan angka telanjang mengikuti viewBox
        // dan dicetak dalam poin. Terukur: dengan angka telanjang barcode
        // tepat 84x14 mm; dengan satuan, menyusut 25%.
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.2F" height="%.2F" viewBox="0 0 %.3F %.3F">'
            .'<rect width="%.3F" height="%.3F" fill="#fff"/>'
            .'<g fill="#000">%s</g></svg>',
            $lebarPt,
            $tinggiPt,
            $lebarPt,
            $tinggiPt,
            $lebarPt,
            $tinggiPt,
            implode('', $kotak),
        );
    }

    /** Data URI siap pakai pada atribut src gambar. */
    public static function dataUri(string $teks, float $lebarMm = 84.0, float $tinggiMm = 14.0): ?string
    {
        $svg = self::svg($teks, $lebarMm, $tinggiMm);

        return $svg === null ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
