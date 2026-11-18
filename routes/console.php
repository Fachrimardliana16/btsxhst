<?php

use App\Models\LoginAttempt;
use App\Models\MediaUploadLog;
use App\Models\SecurityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Penjadwal
|--------------------------------------------------------------------------
| Dijalankan oleh satu entri cron di server:
|
|     * * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
|
| Di Windows, `php artisan schedule:work` dapat dijalankan sebagai proses latar
| selama pengembangan.
*/

/*
| Cadangan harian pukul 02.00 (checklist F10-13).
|
| Dipilih dini hari karena penyalinan berkas basis data membaca seluruh berkas:
| menjalankannya saat toko ramai berarti menambah beban tepat ketika beban
| sudah tinggi. `withoutOverlapping` mencegah dua penyalinan berjalan
| bersamaan bila yang pertama belum selesai.
*/
Schedule::command('iksan:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onFailure(function (): void {
        // Perintah sudah mencatat sebabnya di catatan aktivitas; baris ini
        // memastikan kegagalan penjadwalan juga masuk log aplikasi.
        Log::error('Cadangan harian gagal dijalankan.');
    });

/*
| Pembersihan berkas karantina (checklist F3-12).
|
| Berkas unggahan yang gagal diproses disimpan sehari supaya penyebabnya masih
| dapat diperiksa. Setelah itu ruangnya diambil kembali.
*/
Schedule::command('iksan:bersihkan-karantina')
    ->dailyAt('03:00')
    ->withoutOverlapping();

/*
| Pembersihan data lama (checklist F10-14).
|
| Sesi kedaluwarsa, potongan cache yang tidak lagi dipakai, dan catatan
| keamanan berumur lebih dari 90 hari. Catatan keamanan dipangkas karena
| tabelnya tumbuh paling cepat — setiap percobaan masuk yang gagal menulis
| satu baris — sementara nilainya untuk penyelidikan hanya beberapa pekan.
*/
/* Catatan: `cache:prune-stale-tags` sengaja TIDAK dijadwalkan. Perintah itu
 * hanya menyentuh cache bertag (database/redis); toko ini memakai penyimpan
 * `file`, jadi menjadwalkannya hanya membuang satu proses tiap jam tanpa
 * membersihkan apa pun. Entri cache file kedaluwarsa sendiri sesuai TTL-nya. */

Artisan::command('iksan:pangkas-data-lama {--hari=90 : Umur catatan keamanan yang disimpan}', function () {
    $hari = (int) $this->option('hari');
    $batas = now()->subDays($hari);

    // Sesi kedaluwarsa
    $sesi = DB::table('sessions')
        ->where('last_activity', '<', now()->subHours(24)->getTimestamp())
        ->delete();

    // Catatan keamanan lama
    $keamanan = SecurityLog::query()->where('created_at', '<', $batas)->delete();

    // Percobaan masuk yang sudah selesai dan tidak relevan lagi
    $percobaan = LoginAttempt::query()
        ->where('last_attempt_at', '<', $batas)
        ->delete();

    // Catatan unggahan yang sudah tidak diperlukan
    $unggahan = MediaUploadLog::query()
        ->whereIn('status', ['rejected', 'failed', 'deduplicated'])
        ->where('created_at', '<', $batas)
        ->delete();

    $this->info(sprintf(
        'Dipangkas: %d sesi, %d catatan keamanan, %d percobaan masuk, %d catatan unggahan.',
        $sesi, $keamanan, $percobaan, $unggahan,
    ));

    return self::SUCCESS;
})->purpose('Membersihkan sesi, catatan keamanan, dan log lama');

Schedule::command('iksan:pangkas-data-lama')
    ->dailyAt('03:30')
    ->withoutOverlapping();

/*
| Pembatalan pesanan kedaluwarsa (checklist F7-12).
|
| Setiap sepuluh menit, bukan sekali sehari: batas waktunya 24 jam sejak pesanan
| dibuat, jadi yang menentukan bukan jam dinding melainkan jarak sejak pesanan
| terakhir kali dibuat. Menjalankannya lebih sering juga berarti tahanan stok
| kembali lebih cepat, dan itulah yang menentukan berapa lama barang yang
| sebenarnya tersedia tampak habis.
*/
Schedule::command('iksan:batalkan-kedaluwarsa')
    ->everyTenMinutes()
    ->withoutOverlapping();

/*
| Pemrosesan antrean gambar produk.
|
| Dijalankan setiap menit dan berhenti setelah antrean kosong. Ini pengganti
| pekerja antrean yang menetap: pada mesin tanpa supervisor, menjalankan
| `queue:work --stop-when-empty` dari penjadwal sudah cukup dan tidak
| meninggalkan proses yang menggantung.
|
| Di produksi, pekerja menetap tetap lebih baik karena menghindari biaya
| menyalakan ulang proses setiap menit. Penjadwal ini jaring pengaman, bukan
| pengganti.
*/
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
