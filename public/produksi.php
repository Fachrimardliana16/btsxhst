<?php

/*
 * Pintu masuk php-cgi PRODUKSI untuk bitesexhaust.com.
 *
 * Kenapa ada berkas ini, bukan langsung public/index.php:
 *
 * php-cgi TIDAK menerima argumen `--env=production` seperti artisan, dan
 * Laravel selalu membaca berkas bernama `.env` — bukan `.env.production`.
 * Kalau CGI memakai public/index.php biasa, aplikasi produksi akan berjalan
 * dengan APP_DEBUG=true dan database PENGEMBANGAN, karena itu isi `.env`.
 *
 * Berkas ini menyetel environment sebelum Laravel boot, sehingga aplikasi
 * berjalan dengan .env.production tanpa harus menimpa `.env` milik
 * pengembangan. Kedua lingkungan dapat hidup berdampingan.
 *
 * Urutan: nilai yang sudah ada di environment PROSES tidak ditimpa; hanya
 * yang kosong diisi.
 */

// dirname(__DIR__) = akar proyek (naik satu dari public/).
$akarProyek = dirname(__DIR__);
$berkasEnv  = $akarProyek.DIRECTORY_SEPARATOR.'.env.production';

if (! is_file($berkasEnv)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit(".env.production tidak ditemukan di: $berkasEnv\n");
}

// Muat .env.production ke $_ENV/$_SERVER tanpa menimpa nilai yang sudah ada.
foreach (file($berkasEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
    $baris = trim($baris);

    if ($baris === '' || str_starts_with($baris, '#')) {
        continue;
    }

    if (! str_contains($baris, '=')) {
        continue;
    }

    [$kunci, $nilai] = explode('=', $baris, 2);
    $kunci = trim($kunci);
    $nilai = trim($nilai);

    // Buang tanda kutip pembungkus, kalau ada.
    if (strlen($nilai) >= 2
        && (($nilai[0] === '"' && $nilai[-1] === '"') || ($nilai[0] === "'" && $nilai[-1] === "'"))) {
        $nilai = substr($nilai, 1, -1);
    }

    if ($kunci === '' || getenv($kunci) !== false) {
        continue;
    }

    putenv("$kunci=$nilai");
    $_ENV[$kunci] = $nilai;
    $_SERVER[$kunci] = $nilai;
}

// Penegasan: produksi TIDAK boleh berjalan di lingkungan lain.
putenv('APP_ENV=production');
$_ENV['APP_ENV'] = 'production';
$_SERVER['APP_ENV'] = 'production';

require $akarProyek.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php';
