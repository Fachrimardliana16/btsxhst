<?php

namespace App\Console\Commands;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Services\StatusPesananService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Throwable;

/**
 * Membatalkan pesanan yang belum dibayar sampai lewat batas waktu (checklist
 * F7-12).
 *
 * Stok dikembalikan lewat StatusPesananService, bukan dengan menulis kolom
 * reserved_quantity langsung di sini. Hanya ada satu tempat yang boleh
 * menyentuh tahanan stok — kalau tidak, cepat atau lambat ada jalur yang lupa
 * melepasnya, dan barang yang sudah dibatalkan tetap tampak terikat.
 *
 * Alasan pembatalan dicatat di order_status_history supaya pesanan yang hilang
 * dari daftar tidak menjadi misteri bagi admin yang mencarinya.
 */
class BatalkanKedaluwarsa extends Command
{
    protected $signature = 'iksan:batalkan-kedaluwarsa
                            {--uji : Tampilkan yang akan dibatalkan tanpa mengubah apa pun}';

    protected $description = 'Batalkan pesanan pending yang belum dibayar sampai batas waktunya';

    public function handle(StatusPesananService $status): int
    {
        $uji = (bool) $this->option('uji');

        $dasar = Order::query()
            ->where('status', StatusPesanan::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            // Bukti transfer yang sedang menunggu verifikasi adalah tanda
            // pembeli sudah membayar. Membatalkannya pada jam ke-24 membuat
            // uang masuk untuk pesanan yang stoknya sudah dilepas.
            ->where('payment_status', '!=', StatusPembayaran::Pending->value);

        $jumlah = (clone $dasar)->count();

        if ($jumlah === 0) {
            $this->info('Tidak ada pesanan kedaluwarsa.');

            return self::SUCCESS;
        }

        // Diproses per potongan, bukan dimuat sekaligus. Setelah toko tutup
        // sehari penuh, daftarnya bisa berisi ratusan pesanan beserta
        // itemnya — semuanya didekripsi hanya untuk membatalkannya satu per
        // satu.
        foreach ($dasar->with('items')->lazyById(100) as $order) {
            $lewat = $order->expires_at?->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE]) ?? '-';

            if ($uji) {
                $this->line(sprintf('%s · kedaluwarsa %s lalu · %d barang', $order->order_number, $lewat, $order->items->count()));

                continue;
            }

            // Keadaan bisa berubah sejak kueri di atas: webhook PayPal atau
            // pembeli yang mengunggah bukti dapat berjalan di antaranya.
            $order->refresh();

            if ($order->status !== StatusPesanan::Pending
                || $order->payment_status === StatusPembayaran::Pending) {
                continue;
            }

            try {
                $status->pindah(
                    $order,
                    StatusPesanan::Cancelled,
                    null,
                    'Cancelled automatically: payment was not received before the deadline.',
                );
            } catch (Throwable $e) {
                // Satu pesanan yang gagal tidak boleh menghentikan sisanya.
                $this->warn(sprintf('%s gagal dibatalkan: %s', $order->order_number, $e->getMessage()));
            }
        }

        $this->info(sprintf(
            $uji ? '%d pesanan kedaluwarsa (uji, tidak ada yang diubah).' : '%d pesanan dibatalkan, tahanan stok dilepas.',
            $jumlah,
        ));

        return self::SUCCESS;
    }
}
