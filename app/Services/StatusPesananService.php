<?php

namespace App\Services;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiAdmin;
use App\Jobs\KirimNotifikasiPesanan;
use App\Mail\PesananAdmin;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\SakelarNotifikasi;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perpindahan status pesanan.
 *
 * Setiap perubahan status yang menyentuh stok dilakukan di sini, bukan di
 * komponen Livewire, supaya pemulihan dan pengurangan stok selalu berjalan
 * bersama perubahan status. Mengubah status langsung lewat model akan membuat
 * reserved_quantity dan stock_quantity tidak lagi seimbang.
 */
class StatusPesananService
{
    /**
     * Pindahkan pesanan ke status baru.
     *
     * - Dibayar: tidak menyentuh stok, hanya menandai pembayaran.
     * - Selesai: stok fisik dikurangi dan reserved dilepas, karena barang
     *   benar-benar keluar dari gudang.
     * - Dibatalkan: tahanan dilepas. Bila barang sudah dikirim, stok fisik
     *   ikut berkurang karena barangnya sudah keluar dari gudang.
     */
    public function pindah(
        Order $order,
        StatusPesanan $tujuan,
        ?User $oleh = null,
        ?string $catatan = null,
    ): Order {
        if (! $order->status->bisaKe($tujuan)) {
            throw new RuntimeException(sprintf(
                'Status cannot move from %s to %s.',
                $order->status->value,
                $tujuan->value,
            ));
        }

        $hasil = DB::transaction(function () use ($order, $tujuan, $oleh, $catatan): Order {
            // Baca ulang di dalam transaksi. Pemeriksaan di atas memakai objek
            // yang mungkin sudah basi — pembayaran atau pembatalan lain dapat
            // tersimpan di antaranya.
            $order->refresh();

            if (! $order->status->bisaKe($tujuan)) {
                throw new RuntimeException(sprintf(
                    'Status cannot move from %s to %s.',
                    $order->status->value,
                    $tujuan->value,
                ));
            }

            $asal = $order->status;

            // Varian dimuat bersama produknya.
            //
            // Sejak hargaAkhir() mewajibkan relasi product terpasang, jalur
            // apa pun yang menyentuh varian harus memuatnya. Dilakukan sekali
            // di sini — bukan per baris di dalam perulangan — supaya tetap satu
            // kueri meski pesanannya berisi banyak barang.
            $order->load('items.variant.product');

            match ($tujuan) {
                StatusPesanan::Completed => $this->selesaikanStok($order),
                StatusPesanan::Cancelled => $this->lepaskanStok($order, $asal),
                default => null,
            };

            // Kuota kupon dipakai saat pesanan dibuat. Pesanan yang batal
            // tidak pernah menghasilkan penjualan, jadi kuotanya harus kembali
            // — kalau tidak, beberapa pembatalan dapat menghabiskan kuota
            // kupon tanpa satu pun pembelian.
            if ($tujuan === StatusPesanan::Cancelled && $order->coupon_id !== null) {
                DB::table('coupons')
                    ->where('id', $order->coupon_id)
                    ->where('used_count', '>', 0)
                    ->update(['used_count' => DB::raw('used_count - 1')]);
            }

            $order->status = $tujuan;

            if ($tujuan === StatusPesanan::Paid) {
                $order->payment_status = StatusPembayaran::Paid;
                $order->paid_at = $order->paid_at ?? now();
            }

            if ($tujuan === StatusPesanan::Shipped) {
                $order->shipped_at = $order->shipped_at ?? now();
            }

            if ($tujuan === StatusPesanan::Completed) {
                $order->completed_at = now();
            }

            if ($tujuan === StatusPesanan::Cancelled) {
                $order->cancelled_at = now();
            }

            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $asal->value,
                'to_status' => $tujuan->value,
                'note' => $catatan,
                'changed_by' => $oleh?->id,
            ]);

            return $order->fresh(['items', 'statusHistory']);
        }, 3);

        // Notifikasi hanya untuk kejadian yang berarti bagi pembeli. Dikirim
        // setelah transaksi selesai: pekerjaan antrean di dalam transaksi dapat
        // berjalan sebelum datanya tersimpan.
        if ($tujuan === StatusPesanan::Shipped && SakelarNotifikasi::kirimAktif('kirim_saat_dikirim')) {
            KirimNotifikasiPesanan::dispatch($hasil->id, KirimNotifikasiPesanan::KEJADIAN_DIKIRIM);
        }

        /* Pesanan SELESAI — dua penerima, dua job.
         *
         * Pembeli diberi tahu sebagai penutup transaksi (barang sudah ia
         * terima, urusannya selesai). Admin diberi tahu sebagai rekap.
         *
         * Job-nya sengaja dipisah: lihat catatan di KirimNotifikasiAdmin
         * tentang kenapa kegagalan salah satunya tidak boleh menjatuhkan yang
         * lain.
         *
         * Kejadian lain TIDAK memicu email di sini:
         *   - Shipped  -> sudah ditangani blok di atas
         *   - Processing, Cancelled -> admin sedang menangani pesanan ini di
         *     panel, atau pembeli melihatnya sendiri di halaman pesanan.
         */
        if ($tujuan === StatusPesanan::Completed) {
            if (SakelarNotifikasi::kirimAktif('kirim_saat_selesai')) {
                KirimNotifikasiPesanan::dispatch($hasil->id, KirimNotifikasiPesanan::KEJADIAN_SELESAI);
            }

            if (SakelarNotifikasi::kirimAktif('kirim_admin_saat_selesai')) {
                KirimNotifikasiAdmin::dispatch($hasil->id, PesananAdmin::SELESAI);
            }
        }

        return $hasil;
    }

    /**
     * Barang benar-benar keluar. Stok fisik berkurang, dan tahanan dilepas
     * dengan jumlah yang sama.
     */
    private function selesaikanStok(Order $order): void
    {
        foreach ($order->items as $item) {
            $varian = $item->variant;

            if ($varian === null) {
                continue;
            }

            $varian->increment('stock_quantity', -$item->quantity);
            $varian->decrement('reserved_quantity', $item->quantity);

            // Jaga-jaga terhadap pembulatan atau data lama: nilai tidak boleh
            // menjadi negatif.
            $varian->refresh();
            $varian->reserved_quantity = max(0, $varian->reserved_quantity);
            $varian->stock_quantity = max(0, $varian->stock_quantity);
            $varian->save();

            // Angka "terjual" dipakai sortir terlaris di katalog dan panel.
            // Tanpa ini kolomnya tetap 0 selamanya dan sortir tidak bermakna.
            DB::table('products')
                ->where('id', $item->product_id)
                ->update(['sold_count' => DB::raw('sold_count + '.(int) $item->quantity)]);
        }
    }

    /**
     * Pesanan batal: tahanan dilepas.
     *
     * Stok fisik hanya tersentuh bila barang sudah dikirim. Pembatalan dari
     * status Dikirim berarti barang sudah keluar dari gudang: stoknya harus
     * ikut berkurang, bukan dikembalikan utuh. Sebelum ini stok kembali penuh
     * dan barang yang sama dapat terjual lagi.
     */
    private function lepaskanStok(Order $order, StatusPesanan $asal): void
    {
        foreach ($order->items as $item) {
            $varian = $item->variant;

            if ($varian === null) {
                continue;
            }

            $varian->decrement('reserved_quantity', $item->quantity);

            if ($asal === StatusPesanan::Shipped) {
                $varian->increment('stock_quantity', -$item->quantity);
            }

            $varian->refresh();
            $varian->reserved_quantity = max(0, $varian->reserved_quantity);
            $varian->stock_quantity = max(0, $varian->stock_quantity);
            $varian->save();
        }
    }
}
