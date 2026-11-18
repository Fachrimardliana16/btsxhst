<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Umur sesi panel admin.
 *
 * Dipakai dua jalur: middleware PastikanAdmin (permintaan halaman) dan
 * KomponenAdmin::boot() (permintaan /livewire/update). Sebelumnya hanya
 * middleware yang memeriksa, sehingga komponen yang sudah terbuka dapat
 * terus menjalankan aksi setelah waktu tunggu habis.
 */
class SesiAdmin
{
    public const KUNCI = 'admin.terakhir_aktif';

    /**
     * Catat aktivitas, dan laporkan apakah sesi sudah kedaluwarsa.
     *
     * Sesi tanpa catatan (misalnya baru dibentuk dari cookie remember-me)
     * dianggap baru: waktunya mulai sekarang, bukan dilewati. Sebelumnya
     * ketiadaan catatan membuat pemeriksaan dilewati sama sekali, sehingga
     * sesi dari remember-me tidak pernah kedaluwarsa.
     */
    public static function kedaluwarsa(Session $sesi): bool
    {
        $terakhir = $sesi->get(self::KUNCI);

        if ($terakhir === null) {
            $sesi->put(self::KUNCI, now()->timestamp);

            return false;
        }

        return now()->timestamp - (int) $terakhir > config('session.lifetime') * 60;
    }

    public static function sentuh(Session $sesi): void
    {
        $sesi->put(self::KUNCI, now()->timestamp);
    }
}
