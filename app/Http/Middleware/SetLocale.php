<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa antarmuka.
 *
 * Situs ini sekarang BERBAHASA INGGRIS TUNGGAL: APP_LOCALE=en dan
 * APP_FALLBACK_LOCALE=en. Karena itu bahasa tidak lagi ditentukan cookie —
 * setiap pengunjung mendapat bahasa yang sama.
 *
 * Kenapa perilaku lama dibuang: middleware ini dulu membaca cookie
 * `iksan_locale` dan memakai nilainya bila ada. Akibatnya pengunjung yang
 * pernah memakai situs versi Indonesia tetap melihat versi Indonesia
 * selamanya, dan pengujian dengan `?lang=en` MENULIS cookie itu — sehingga
 * peramban yang sama terus menampilkan Indonesia walau APP_LOCALE sudah en.
 * Itu persis yang terjadi saat rebranding: judul halaman Inggris, isi section
 * masih Indonesia, dan penyebabnya tidak terlihat dari kode karena tersimpan
 * di cookie peramban.
 *
 * Bila kelak situs perlu dua bahasa lagi, kembalikan pembacaan cookie di sini,
 * dan tambahkan tombol pilih bahasa di navbar (sudah ada tautan front.lang).
 */
class SetLocale
{
    /** Nama cookie lama. Dipertahankan agar dapat dihapus dari peramban. */
    public const COOKIE = 'iksan_locale';

    /** @var array<int, string> */
    public const DIDUKUNG = ['en', 'id'];

    public function handle(Request $request, Closure $next): Response
    {
        // Bahasa tunggal: selalu ikuti konfigurasi aplikasi.
        app()->setLocale(config('app.locale', 'en'));

        $response = $next($request);

        /*
         * Hapus cookie bahasa lama.
         *
         * Tanpa ini, peramban yang pernah menyimpan 'id' akan terus mengirim
         * cookie itu. Tidak berbahaya lagi karena middleware tidak membacanya,
         * tetapi menyisakan data yang membingungkan saat diperiksa.
         */
        if ($request->cookie(self::COOKIE) !== null) {
            $response->headers->clearCookie(self::COOKIE, '/');
        }

        return $response;
    }

    public static function lain(string $locale): string
    {
        return $locale === 'en' ? 'id' : 'en';
    }
}
