<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mempercayai proxy (cloudflared) yang berjalan di mesin ini.
 *
 * Kenapa ini middleware sendiri, bukan `$middleware->trustProxies()` di
 * bootstrap/app.php:
 *
 * 1. Closure `withMiddleware()` berjalan SEBELUM konfigurasi terpasang di
 *    kontainer. Memanggil config() di sana melempar
 *    "ReflectionException: Class config does not exist" dan seluruh situs mati
 *    dengan 500. Memanggil env() di sana juga tidak menolong: setelah
 *    `php artisan config:cache`, Laravel TIDAK memuat .env sama sekali,
 *    sehingga env() mengembalikan null untuk semua kunci.
 *
 * 2. `$middleware->trustProxies()` hanya MENYETEL nilai pada kelas
 *    TrustProxies; ia tidak memasang middleware itu ke stack. Tanpa
 *    pemasangan, header X-Forwarded-Proto diabaikan.
 *
 * Akibat bila keduanya keliru (pernah terjadi di proyek ini): permintaan dari
 * tunnel tiba sebagai HTTP polos, seluruh URL aset dirender `http://`, dan
 * peramban memblokirnya sebagai mixed content — halaman tampil TANPA CSS.
 */
class PercayaiProxy
{
    public function handle(Request $request, Closure $next): mixed
    {
        $proxies = config('iksan.trusted_proxies');

        // Lingkungan lokal: cloudflared berjalan di mesin yang sama, jadi hanya
        // 127.0.0.1 yang perlu dipercaya. BUKAN '*': itu membuat Laravel
        // mempercayai X-Forwarded-For dari siapa pun, dan pemanggil dapat
        // memalsukan alamat IP untuk melewati pembatas laju.
        if ($proxies === null || $proxies === '') {
            $proxies = config('app.env') === 'local' ? '127.0.0.1' : null;
        }

        if (is_string($proxies) && $proxies !== '') {
            $daftar = $proxies === '*' ? ['*'] : array_map('trim', explode(',', $proxies));

            // Hanya PROTO yang dibutuhkan di sini: skema HTTPS dari
            // X-Forwarded-Proto. Alamat IP asli TIDAK dipercaya dari header,
            // supaya pembatas laju tidak dapat dipalsukan.
            // Hanya PROTO yang dipercaya. Setiap permintaan baru membawa set
            // kosong, jadi diulang di sini — bukan sekali saat boot.
            $request->setTrustedProxies($daftar, Request::HEADER_X_FORWARDED_PROTO);
        }

        // ── SKEMA DIKUNCI SETELAH PROXY DIPERCAYA ──────────────────────────
        //
        // Ini perbaikan "Does not use HTTPS — insecure requests" dari
        // PageSpeed. Sebelumnya skema dibiarkan mengikuti permintaan, jadi
        // halaman yang datang lewat tunnel tetap dapat merender URL aset,
        // canonical, dan formulir sebagai http:// bila ada satu jalur kode
        // yang memakai APP_URL atau kehilangan header. Peramban lalu
        // memblokirnya sebagai campuran konten.
        //
        // Kuncinya: HANYA ketika permintaan memang sudah terbukti aman
        // ($request->isSecure()). Akses pengembangan lewat
        // http://127.0.0.1:8100 tetap http, jadi CSS tidak hilang seperti
        // percobaan forceScheme tanpa syarat yang pernah merusak situs ini.
        if ($request->isSecure()) {
            URL::forceScheme('https');
        }

        $respons = $next($request);

        // HSTS: perintahkan peramban memakai https untuk permintaan
        // berikutnya — termasuk aset yang URL-nya terlanjur ditulis http di
        // CSS atau basis data. Ini penetral racun campuran konten yang paling
        // andal, karena berlaku sebelum aset diminta.
        if ($request->isSecure() && $respons instanceof Response) {
            $respons->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $respons;
    }
}
