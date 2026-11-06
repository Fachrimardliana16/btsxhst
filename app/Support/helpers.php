<?php

use App\Support\Pengaturan;
use App\Support\Ukuran;
use Carbon\CarbonImmutable;

if (! function_exists('csp_nonce')) {
    /** Nonce CSP untuk skrip inline. Kosong bila middleware tidak aktif. */
    function csp_nonce(): string
    {
        return app()->bound('csp.nonce') ? app('csp.nonce') : '';
    }
}

if (! function_exists('rupiah')) {
    /** Format mata uang tanpa simbol bawaan, memakai titik ribuan gaya Indonesia. */
    function rupiah(float|int|string|null $amount, string $currency = 'IDR'): string
    {
        $value = (float) $amount;

        /* Simbol mata uang dari panel.
         *
         * `currency_symbol` sudah ada di Pengaturan Lanjutan, tetapi fungsi ini
         * memakai simbol bawaan yang ditulis mati — jadi pemilik toko bisa
         * mengganti simbolnya dan tidak ada yang berubah.
         *
         * Yang diganti HANYA simbolnya; cara pengelompokan angka tetap
         * mengikuti mata uangnya. Menukar pemisah ribuan dan desimal ke gaya
         * Amerika untuk semua mata uang justru membuat angka rupiah terbaca
         * salah ("1,250,000" dapat dibaca sebagai satu koma dua lima).
         *
         * Dibungkus try: fungsi ini dipanggil di hampir setiap tampilan,
         * termasuk halaman galat — dan halaman galat tidak boleh ikut gagal
         * hanya karena pengaturan belum terbaca. */
        $simbol = null;

        try {
            $simbol = Pengaturan::instance()->teks('currency_symbol', '');
        } catch (Throwable) {
            $simbol = null;
        }

        $bawaan = match (strtoupper($currency)) {
            'IDR' => 'Rp',
            'USD' => '$',
            default => null,
        };

        $awalan = filled($simbol) && $bawaan !== null ? (string) $simbol : null;

        return match (strtoupper($currency)) {
            'IDR' => ($awalan ?? 'Rp').' '.number_format($value, 0, ',', '.'),
            'USD' => ($awalan ?? '$').number_format($value, 2, '.', ','),
            default => number_format($value, 2, ',', '.').' '.$currency,
        };
    }
}

if (! function_exists('tanggal')) {
    /**
     * Memformat tanggal memakai pola dari panel (`date_format`).
     *
     * Sebelumnya tanggal ditulis dengan pola yang ditulis mati di setiap view:
     * 'd M Y' di halaman cetak, 'd/m/Y' di daftar pesanan, dan sebagainya —
     * sehingga satu situs menampilkan tiga gaya tanggal yang berbeda.
     * Kolom `date_format` sudah ada di Pengaturan Lanjutan sejak awal, tetapi
     * tidak dibaca kode mana pun.
     *
     * Pola dari panel divalidasi: hanya huruf format tanggal dan pemisah yang
     * diizinkan. Tanpa pemeriksaan itu, pola rusak menghasilkan tanggal yang
     * salah tanpa galat — lebih buruk daripada pola bawaan.
     */
    function tanggal(DateTimeInterface|string|null $nilai, ?string $pola = null): string
    {
        if ($nilai === null || $nilai === '') {
            return '-';
        }

        $waktu = $nilai instanceof DateTimeInterface
            ? CarbonImmutable::instance($nilai)
            : CarbonImmutable::parse($nilai);

        if ($pola === null) {
            try {
                $pola = (string) Pengaturan::instance()->teks('date_format', '');
            } catch (Throwable) {
                $pola = '';
            }
        }

        // Hanya huruf format dan pemisah yang lazim. Pola selain itu diabaikan.
        if ($pola === '' || preg_match('#^[djMNmYy\-/ .:,]+$#', $pola) !== 1) {
            $pola = 'd M Y';
        }

        return $waktu->translatedFormat($pola);
    }
}

if (! function_exists('ukuran_berkas')) {
    function ukuran_berkas(int|float|null $bytes): string
    {
        return Ukuran::berkas((int) $bytes);
    }
}
