<?php

/**
 * Setelan toko untuk mode dolar.
 *
 * Dijalankan sekali setelah situs beralih ke dolar. Aman diulang: nilainya
 * ditulis ulang, bukan ditambah.
 */

$akar = dirname(__DIR__);

require $akar.'/vendor/autoload.php';

$app = require $akar.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;
use App\Support\Pengaturan;

$nilai = [
    // Simbol rupiah di panel diganti "$" supaya tempat yang masih memakai
    // rupiah() (panel admin, misalnya) tidak menulis "Rp" di depan angka
    // dolar. Yang ditampilkan ke pembeli tetap dolar lewat harga_nilai().
    'currency_symbol' => '$',

    // PayPal menagih dalam mata uang ini. IDR tidak didukung PayPal untuk
    // menerima pembayaran, jadi dolar bukan sekadar pilihan tampilan.
    'paypal_currency' => 'USD',

    // Bawaan bagi pengunjung baru.
    'mata_uang_bawaan' => 'USD',
];

foreach ($nilai as $kunci => $isi) {
    Setting::query()->updateOrCreate(
        ['key' => $kunci],
        ['value' => $isi, 'group' => 'umum', 'is_public' => true],
    );

    echo $kunci.' = '.$isi.PHP_EOL;
}

// Cache pengaturan menyimpan seluruh baris; tanpa ini nilai lama tetap
// terbaca sampai TTL habis.
app(Illuminate\Contracts\Cache\Repository::class)->forget(Pengaturan::KUNCI_CACHE);
app('cache')->forget(Pengaturan::KUNCI_CACHE);

echo 'Cache pengaturan dibersihkan.'.PHP_EOL;
