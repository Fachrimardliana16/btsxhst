<?php

namespace App\Http\Middleware;

use App\Support\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mode pemeliharaan dari panel.
 *
 * ── KENAPA MIDDLEWARE SENDIRI, BUKAN `php artisan down` ────────────────
 * `artisan down` menulis berkas di storage dan hanya bisa dinyalakan dari
 * server (SSH). Pemilik toko tidak punya akses itu — dan justru dialah yang
 * perlu menutup toko sementara saat sedang renovasi atau kehabisan stok.
 *
 * Sakelar `maintenance_mode` sudah lama ada di panel Pengaturan, lengkap
 * dengan teks bantuannya: "Saat aktif, pengunjung melihat halaman
 * pemeliharaan. Panel admin tetap dapat diakses."
 *
 * Tetapi TIDAK ADA SATU BARIS KODE YANG MEMBACANYA. Sakelar itu bisa
 * dinyalakan, tersimpan rapi di basis data, dan tidak mengubah apa pun —
 * kegagalan yang sepenuhnya senyap, karena tidak ada yang error.
 *
 * Middleware ini menutup celah itu.
 *
 * ── APA YANG TETAP TERBUKA ────────────────────────────────────────────
 *   - Panel admin      : tanpa itu, tidak ada cara mematikan mode ini lagi
 *                        selain lewat basis data langsung.
 *   - /livewire/*      : permintaan internal panel; menutupnya memutus
 *                        tombol-tombol di panel yang sedang dipakai.
 *   - /up              : health check.
 *   - Aset statis      : CSS/JS/gambar tetap disajikan supaya halaman
 *                        pemeliharaan tampil rapi, bukan tanpa gaya.
 *   - Perayap mesin pencari DIBLOKIR (503 + Retry-After) supaya halaman
 *     pemeliharaan tidak terindeks menggantikan halaman asli.
 */
class ModePemeliharaan
{
    /** Awalan jalur yang selalu dilewatkan. */
    private const SELALU_TERBUKA = [
        'admin',
        'livewire',
        'up',
        'build',
        'storage',
        'fonts',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->aktif()) {
            return $next($request);
        }

        if ($this->dilewatkan($request)) {
            return $next($request);
        }

        /* 503 dengan Retry-After.
         *
         * 503 adalah kode yang benar untuk "sementara tidak tersedia": mesin
         * pencari memahaminya sebagai "coba lagi nanti" dan TIDAK mengganti
         * halaman asli di indeks dengan halaman pemeliharaan. Jawaban 200
         * akan membuat halaman pemeliharaan terindeks sebagai isi toko. */
        return response()
            ->view('errors.503-pemeliharaan', [
                'pesan' => $this->pesan(),
            ], 503)
            ->header('Retry-After', '3600')
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    private function aktif(): bool
    {
        try {
            return Pengaturan::instance()->boolean('maintenance_mode');
        } catch (Throwable) {
            // Basis data belum siap (migrasi, pemasangan baru): jangan pernah
            // mengunci situs karena kesalahan membaca sakelar.
            return false;
        }
    }

    /**
     * Pesan yang tampil di halaman pemeliharaan.
     *
     * Diambil dari pengaturan `maintenance_message` supaya pemilik toko dapat
     * menulis sendiri, mis. "Kami tutup sampai 3 Januari". Bila kosong,
     * dipakai kalimat bawaan — lebih baik daripada halaman tanpa keterangan.
     */
    private function pesan(): string
    {
        try {
            $pesan = trim((string) Pengaturan::instance()->teks('maintenance_message', ''));
        } catch (Throwable) {
            $pesan = '';
        }

        return $pesan !== ''
            ? $pesan
            : 'Situs sementara tidak dapat diakses. Kami segera kembali.';
    }

    private function dilewatkan(Request $request): bool
    {
        // Jalur yang selalu boleh lewat.
        foreach (self::SELALU_TERBUKA as $awalan) {
            if ($request->is($awalan, $awalan.'/*')) {
                return true;
            }
        }

        // Berkas statis (css, js, gambar, font). Tanpa ini halaman
        // pemeliharaan tampil tanpa gaya sama sekali.
        if ($request->is('*.css', '*.js', '*.webp', '*.jpg', '*.jpeg', '*.png',
            '*.svg', '*.woff2', '*.woff', '*.ico', 'manifest.webmanifest')) {
            return true;
        }

        return false;
    }
}
