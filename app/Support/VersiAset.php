<?php

namespace App\Support;

use Throwable;

/**
 * Versi aset statis yang perlu diketahui sisi server.
 *
 * Sejauh ini hanya satu: versi cache service worker (`public/sw.js`).
 *
 * KENAPA DIBACA DARI BERKAS, BUKAN DISIMPAN DI SINI
 * -------------------------------------------------
 * Nilai versi sudah ada di `sw.js` sebagai `const VERSI = 'iksan-vN'`, dan
 * skrip `resources/js/naikkan-versi-sw.js` menaikkannya otomatis setiap
 * `npm run build`. Menyalin angkanya ke kelas PHP berarti dua tempat yang
 * harus dijaga tetap sama — dan yang kedua pasti terlupakan suatu hari.
 *
 * Jadi kelas ini MEMBACA berkas itu. Hasilnya diingat selama satu permintaan
 * supaya tidak membaca disk berkali-kali dalam satu halaman.
 */
class VersiAset
{
    private static ?string $versiSw = null;

    /**
     * Versi service worker, dipakai sebagai query string saat mendaftarkannya.
     *
     * Alamat yang berubah membuat peramban memperlakukan sw.js sebagai service
     * worker BARU. Tanpa itu, peramban boleh memakai salinan lamanya tanpa
     * bertanya ke server, dan cache aset lama tidak pernah dibuang.
     */
    public static function serviceWorker(): string
    {
        if (self::$versiSw !== null) {
            return self::$versiSw;
        }

        $jalur = public_path('sw.js');

        try {
            $isi = is_file($jalur) ? (string) file_get_contents($jalur) : '';
        } catch (Throwable) {
            $isi = '';
        }

        if (preg_match("/const VERSI = '([^']+)'/", $isi, $cocok) === 1) {
            return self::$versiSw = $cocok[1];
        }

        // Berkas tidak terbaca: pakai penanda waktu berkasnya sebagai
        // pengganti. Yang penting nilainya BERUBAH ketika sw.js berubah —
        // bukan bahwa bentuknya rapi.
        return self::$versiSw = is_file($jalur)
            ? 'x'.filemtime($jalur)
            : 'x0';
    }
}
