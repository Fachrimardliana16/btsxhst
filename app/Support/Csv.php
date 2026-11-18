<?php

namespace App\Support;

/**
 * Pengaman nilai sel CSV.
 *
 * Excel dan LibreOffice menafsirkan sel yang dimulai dengan `=`, `+`, `-`, atau
 * `@` sebagai rumus. Nama pembeli, alamat, dan nomor resi berasal dari pengguna,
 * jadi tanpa penawaran sebuah sel dapat menjalankan rumus saat admin membuka
 * ekspor — termasuk memanggil alamat luar.
 */
class Csv
{
    /** Awali sel berbahaya dengan kutip tunggal agar dibaca sebagai teks. */
    public static function aman(mixed $nilai): mixed
    {
        if (! is_string($nilai) || $nilai === '') {
            return $nilai;
        }

        $pertama = $nilai[0];

        if (in_array($pertama, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$nilai;
        }

        return $nilai;
    }

    /**
     * @param  array<int, mixed>  $sel
     * @return array<int, mixed>
     */
    public static function baris(array $sel): array
    {
        return array_map([self::class, 'aman'], $sel);
    }
}
