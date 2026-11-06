<?php

namespace App\Jobs;

use App\Mail\PesananAdmin;
use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Memberi tahu pemilik toko lewat email.
 *
 * Dipisah dari KirimNotifikasiPesanan (yang mengurus pembeli) karena tiga
 * alasan yang berbeda dari sekadar "rapi":
 *
 *   1. TUJUANNYA BERBEDA. KirimNotifikasiPesanan memakai alamat pembeli dari
 *      pesanan itu sendiri. Job ini memakai alamat toko dari pengaturan.
 *   2. KEGAGALANNYA TIDAK BOLEH SALING MENJATUHKAN. Kalau email pembeli gagal,
 *      admin tetap harus tahu ada pesanan masuk. Digabung dalam satu job,
 *      satu exception di tengah berarti keduanya berhenti.
 *   3. PEMICUANNYA BEDA. Pembeli diberi tahu saat pesanan dibuat, dibayar, dan
 *      dikirim. Admin hanya saat pesanan MASUK dan SELESAI — kejadian lain
 *      dilakukan admin sendiri, jadi mengabarinya hanya menambah kebisingan.
 *
 * Alamat tujuan diambil dari pengaturan (bukan config), supaya pemilik toko
 * dapat mengubahnya dari panel tanpa menyentuh berkas .env di server.
 */
class KirimNotifikasiAdmin implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $pesananId,
        public readonly string $kejadian = PesananAdmin::BARU,
    ) {}

    public function handle(): void
    {
        $pesanan = Order::query()->with(['items', 'user'])->find($this->pesananId);

        if ($pesanan === null) {
            return;
        }

        $tujuan = $this->tujuan();

        if ($tujuan === []) {
            // Tidak ada alamat tujuan: bukan kegagalan, hanya belum diatur.
            // Dicatat sekali supaya tidak menjadi misteri "mengapa tidak ada
            // email padahal pesanan masuk".
            Log::info('Notifikasi admin dilewati: alamat tujuan belum diisi', [
                'pesanan' => $pesanan->order_number,
            ]);

            return;
        }

        try {
            $pesan = Mail::to($tujuan);

            /* CC dari pengaturan `contact_email_cc`.
             *
             * Berguna saat pesanan perlu diketahui orang kedua — staf gudang
             * atau pemilik kedua — tanpa menjadikannya penerima utama.
             * Sebelumnya kolom ini ada di panel tetapi tidak dibaca kode mana
             * pun, jadi mengisinya tidak berpengaruh apa-apa.
             *
             * Alamatnya disaring sama seperti tujuan: satu alamat salah ketik
             * tidak boleh menggagalkan seluruh pengiriman. */
            $cc = $this->cc();

            if ($cc !== []) {
                $pesan->cc($cc);
            }

            $pesan->send(new PesananAdmin($pesanan, $this->kejadian));

            Log::info('Notifikasi admin terkirim', [
                'pesanan' => $pesanan->order_number,
                'kejadian' => $this->kejadian,
                'tujuan' => count($tujuan),
            ]);
        } catch (Throwable $e) {
            // Dicatat, tidak dilempar: pesanan sudah tersimpan, dan gagal
            // mengirim email tidak boleh membuat pesanan tampak gagal dibuat.
            Log::warning('Notifikasi admin gagal terkirim', [
                'pesanan' => $pesanan->order_number,
                'sebab' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Alamat CC, dari pengaturan `contact_email_cc`.
     *
     * Dipisah dari tujuan() karena artinya berbeda: penerima utama menentukan
     * SIAPA yang harus bertindak, sedangkan CC hanya "ikut tahu". Alamat yang
     * juga sudah menjadi penerima utama dibuang dari CC supaya tidak menerima
     * email yang sama dua kali.
     *
     * @return array<int, string>
     */
    private function cc(): array
    {
        $toko = Pengaturan::instance();

        $kandidat = collect(explode(',', (string) $toko->teks('contact_email_cc', '')))
            ->map(fn (string $s) => trim($s))
            ->filter(fn (string $s) => filter_var($s, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        return array_values(array_diff($kandidat, $this->tujuan()));
    }

    /**
     * Alamat tujuan, dari pengaturan toko.
     *
     * `admin_email` didahulukan; `contact_email` menjadi cadangan supaya toko
     * yang sudah mengisi alamat kontak tidak perlu mengisi kolom baru.
     *
     * Hasilnya larik karena pemilik toko mungkin ingin pesanan masuk ke lebih
     * dari satu alamat (mis. pemilik + staf gudang). Koma sebagai pemisah.
     *
     * @return array<int, string>
     */
    private function tujuan(): array
    {
        $toko = Pengaturan::instance();

        /* Cadangan dipakai bila alamat utama KOSONG **atau TIDAK SAH**.
         *
         * Versi sebelumnya hanya memeriksa kosong:
         *
         *     $daftar = $mentah !== '' ? $mentah : $cadangan;
         *
         * Akibatnya alamat yang salah ketik — "pemilik@toko" tanpa .test,
         * atau "bukan-email" — dianggap "sudah diisi", sehingga cadangan
         * tidak pernah dipakai, dan toko TIDAK menerima email pesanan sama
         * sekali. Kegagalannya senyap: tidak ada galat, hanya kotak masuk
         * yang tetap kosong.
         *
         * Sekarang: kumpulkan dulu alamat sah dari kolom utama DAN cadangan,
         * lalu pakai yang ada. Kalau kolom utama berisi satu alamat sah dan
         * satu rusak, yang sah tetap dipakai.
         *
         * Hasilnya larik karena pemilik toko mungkin ingin pesanan masuk ke
         * lebih dari satu alamat (mis. pemilik + staf gudang). Koma sebagai
         * pemisah.
         *
         * @return array<int, string>
         */
        $kandidat = array_merge(
            explode(',', (string) $toko->teks('admin_email', '')),
            explode(',', (string) $toko->teks('contact_email', '')),
        );

        return collect($kandidat)
            ->map(fn (string $s) => trim($s))
            ->filter(fn (string $s) => filter_var($s, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
