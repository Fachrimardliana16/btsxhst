<?php

namespace App\Support;

use Throwable;

/**
 * Menerapkan pengaturan SMTP dari basis data ke konfigurasi mail.
 *
 * ── KENAPA DI SINI, BUKAN DI .env ──────────────────────────────────────
 * Pemilik toko memegang akun emailnya sendiri, dan meminta ia menyunting
 * berkas .env di server lalu menjalankan `php artisan config:clear` bukan hal
 * yang wajar — apalagi di shared hosting yang tidak punya akses SSH.
 *
 * Jadi nilai SMTP diisi dari panel, lalu diterapkan ke `config('mail.*')` pada
 * setiap permintaan. Nilai yang KOSONG diabaikan, sehingga pemasangan lama
 * yang masih memakai .env tetap berjalan tanpa perubahan.
 *
 * ── URUTAN PEMANGGILAN PENTING ─────────────────────────────────────────
 * Harus dijalankan SEBELUM mailer dibuat. Laravel menyimpan MailManager di
 * container saat pertama dipakai; kalau konfigurasi diubah setelahnya, mailer
 * yang sudah jadi tetap memakai setelan lama. Karena itu penerapannya
 * dilakukan di AppServiceProvider::boot(), bukan di dalam job.
 */
class KonfigurasiMail
{
    /** Penanda agar tidak menerapkan dua kali dalam satu permintaan. */
    private static bool $sudah = false;

    /**
     * Terapkan pengaturan dari basis data ke config mail.
     *
     * Aman dipanggil walau basis data belum siap: seluruhnya dibungkus try,
     * dan kegagalan apa pun diabaikan. Aplikasi harus tetap bisa jalan dengan
     * konfigurasi .env bila tabel settings belum ada (mis. saat migrasi).
     */
    public static function terapkan(): void
    {
        if (self::$sudah) {
            return;
        }

        self::$sudah = true;

        try {
            $toko = Pengaturan::instance();

            $mailer = trim((string) $toko->teks('mail_mailer', ''));

            // 'log' berarti pemilik toko belum/tidak ingin mengirim email
            // sungguhan — dipakai saat mencoba di lokal.
            if ($mailer !== '') {
                config(['mail.default' => $mailer]);
            }

            $peta = [
                'mail_host' => 'mail.mailers.smtp.host',
                'mail_username' => 'mail.mailers.smtp.username',
                'mail_password' => 'mail.mailers.smtp.password',
                'mail_from_address' => 'mail.from.address',
                'mail_from_name' => 'mail.from.name',
            ];

            foreach ($peta as $kunci => $jalurConfig) {
                $nilai = trim((string) $toko->teks($kunci, ''));

                if ($nilai !== '') {
                    config([$jalurConfig => $nilai]);
                }
            }

            $port = trim((string) $toko->teks('mail_port', ''));
            if ($port !== '' && ctype_digit($port)) {
                config(['mail.mailers.smtp.port' => (int) $port]);
            }

            /* Enkripsi: 'none' berarti null, BUKAN string 'none'.
             *
             * Symfony Mailer menolak nilai yang tidak dikenal. Mengirim
             * 'none' apa adanya membuat setiap email gagal dengan galat yang
             * tidak menyebut penyebabnya. */
            $enkripsi = trim((string) $toko->teks('mail_encryption', ''));
            if ($enkripsi !== '') {
                config(['mail.mailers.smtp.encryption' => $enkripsi === 'none' ? null : $enkripsi]);
            }

            /* Pengirim fallback: bila kolom pengirim kosong tetapi username
             * SMTP terisi, alamat itulah yang dipakai. Banyak penyedia SMTP
             * menolak surat yang alamat pengirimnya berbeda dari akunnya. */
            if (trim((string) config('mail.from.address', '')) === ''
                && trim((string) config('mail.mailers.smtp.username', '')) !== '') {
                config(['mail.from.address' => config('mail.mailers.smtp.username')]);
            }
        } catch (Throwable) {
            // Tabel settings belum ada, basis data terkunci, atau belum
            // dimigrasi: pakai konfigurasi .env apa adanya.
        }
    }

    /** Melupakan penanda, dipakai test antar-kasus. */
    public static function lupakan(): void
    {
        self::$sudah = false;
    }
}
