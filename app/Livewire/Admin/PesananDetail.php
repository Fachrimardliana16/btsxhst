<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pembayaran\PembayaranManual;
use App\Services\StatusPesananService;
use App\Support\Galat;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use RuntimeException;
use Throwable;

/**
 * Detail satu pesanan.
 *
 * Di sinilah pesanan benar-benar diproses: status dipindahkan, nomor resi
 * diisi, lalu dokumennya dicetak. Seluruh perpindahan status melewati
 * StatusPesananService supaya stok fisik dan stok tertahan tetap seimbang —
 * mengubah kolom status langsung dari sini akan merusak keduanya.
 */
#[Layout('layouts.admin')]
class PesananDetail extends KomponenAdmin
{
    /** Alasan penolakan bukti transfer, diisi admin sebelum menolak. */
    public string $alasanTolak = '';

    public string $ulid = '';

    /** Status tujuan yang dipilih admin, belum diterapkan. */
    public string $statusTujuan = '';

    public string $catatanStatus = '';

    /** Data pengiriman yang dapat diisi admin. */
    public string $kurir = '';

    public string $layanan = '';

    public string $nomorResi = '';

    public string $catatanAdmin = '';

    /** Ongkir hasil penawaran untuk pesanan internasional. */
    public string $ongkir = '';

    /** Kejadian notifikasi yang akan dikirim ulang, dipilih admin. */
    public string $kejadianKirimUlang = 'dibuat';

    public function mount(string $ulid): void
    {

        $order = $this->cari($ulid);

        $this->ulid = $order->ulid;
        $this->kurir = $order->shipping_courier ?? '';
        $this->layanan = $order->shipping_service ?? '';
        $this->nomorResi = $order->tracking_number ?? '';
        $this->catatanAdmin = $order->admin_note ?? '';
        $this->ongkir = number_format((float) $order->shipping_cost, 2, '.', '');
    }

    private function cari(string $ulid): Order
    {
        return Order::query()
            ->with(['items.variant', 'user', 'statusHistory', 'payments', 'coupon'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }

    /** Pindahkan status pesanan ke tujuan yang dipilih. */
    public function pindahStatus(StatusPesananService $layanan): void
    {
        $order = $this->cari($this->ulid);

        $tujuan = StatusPesanan::tryFrom($this->statusTujuan);

        if ($tujuan === null) {
            session()->flash('galat-admin', 'Choose a target status first.');

            return;
        }

        // Pesanan luar negeri belum punya ongkir yang sah sampai admin
        // memasukkan penawaran kurir. Memprosesnya lebih dulu berarti menjual
        // barang dengan ongkir domestik.
        if ($tujuan === StatusPesanan::Processing
            && $order->shipping_is_intl
            && $order->shipping_quoted_at === null) {
            session()->flash('galat-admin', 'Set the international shipping quote before processing this order.');

            return;
        }

        try {
            $layanan->pindah(
                $order,
                $tujuan,
                auth()->user(),
                filled($this->catatanStatus) ? $this->catatanStatus : null,
            );

            $this->catatanStatus = '';
            $this->statusTujuan = '';

            session()->flash('pesan-admin', 'Status moved to '.$tujuan->label().'.');
        } catch (RuntimeException $e) {
            // RuntimeException dari StatusPesananService adalah pesan bisnis
            // yang memang ditujukan untuk pengelola ("Status tidak dapat
            // berpindah dari X ke Y"), jadi aman ditampilkan apa adanya.
            //
            // Yang TIDAK aman adalah exception tak terduga (PDO, table missing,
            // TypeError). Pesannya memuat nama tabel, SQL, dan jalur berkas —
            // peta dalam aplikasi bagi penyerang. Itu disaring Galat::ramah()
            // supaya pengguna melihat kalimat biasa, sementara rinciannya
            // tetap tercatat di log.
            // QueryException mewarisi RuntimeException, jadi ia harus disaring
            // di sini sebelum pesan bisnis ditampilkan apa adanya.
            $pesan = $e instanceof QueryException
                ? Galat::denganKode($e, __('site.msg.pesanan.gagal_pindah'))
                : $e->getMessage();

            session()->flash('galat-admin', $pesan);
        } catch (Throwable $e) {
            session()->flash('galat-admin', Galat::denganKode($e, __('site.msg.pesanan.gagal_pindah')));
        }
    }

    /** Simpan data pengiriman tanpa mengubah status. */
    public function simpanPengiriman(): void
    {
        $this->validate([
            'kurir' => ['nullable', 'string', 'max:60'],
            'layanan' => ['nullable', 'string', 'max:60'],
            'nomorResi' => ['nullable', 'string', 'max:60'],
            'catatanAdmin' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'kurir' => 'Courier',
            'layanan' => 'Service',
            'nomorResi' => 'Tracking number',
            'catatanAdmin' => 'Admin note',
        ]);

        $order = $this->cari($this->ulid);

        $order->update([
            'shipping_courier' => filled($this->kurir) ? $this->kurir : null,
            'shipping_service' => filled($this->layanan) ? $this->layanan : null,
            'tracking_number' => filled($this->nomorResi) ? $this->nomorResi : null,
            'admin_note' => filled($this->catatanAdmin) ? $this->catatanAdmin : null,
        ]);

        session()->flash('pesan-admin', __('site.msg.pesanan.pengiriman_disimpan'));
    }

    /**
     * Menetapkan ongkir hasil penawaran untuk pesanan internasional.
     *
     * Checkout hanya punya tarif domestik. Untuk luar negeri, ongkir yang
     * benar baru diketahui setelah admin menanyakan tarif ke kurir. Tanpa
     * tindakan ini kolom `shipping_quoted_at` tidak pernah terisi dan pesanan
     * internasional selamanya ditagih tarif domestik.
     */
    public function simpanOngkir(): void
    {
        $this->validate([
            'ongkir' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [], ['ongkir' => 'Shipping cost']);

        $order = $this->cari($this->ulid);

        if ($order->status === StatusPesanan::Cancelled) {
            session()->flash('galat-admin', 'A cancelled order cannot be re-priced.');

            return;
        }

        // Setelah dibayar, nominal yang ditagih sudah terkunci di transaksi
        // PayPal atau di bukti transfer. Mengubahnya di sini membuat total
        // pesanan tidak lagi sama dengan uang yang benar-benar diterima —
        // selisihnya tidak pernah ditagih dan tidak pernah dikembalikan.
        if ($order->payment_status === StatusPembayaran::Paid) {
            session()->flash('galat-admin', 'A paid order cannot be re-priced. Refund it first, or create a new order.');

            return;
        }

        $ongkir = round((float) $this->ongkir, 2);

        $total = round(max(
            0.0,
            (float) $order->subtotal
                - (float) $order->discount_amount
                + (float) $order->tax_amount
                + $ongkir,
        ), 2);

        $order->update([
            'shipping_cost' => $ongkir,
            'grand_total' => $total,
            'shipping_quoted_at' => now(),
        ]);

        session()->flash('pesan-admin', 'Shipping cost updated. The order total is now '.dolar($total).'.');
    }

    /**
     * Memverifikasi bukti transfer dan menandai pesanan lunas.
     *
     * Perpindahan status diserahkan kepada PembayaranManual, yang meneruskannya
     * ke StatusPesananService. Menulis kolom status di sini akan melewati
     * pengelolaan stok: pesanan lunas masih menahan stok, dan melewati service
     * membuat stok fisik berkurang dua kali atau tidak sama sekali.
     */
    public function verifikasiPembayaran(int $pembayaranId): void
    {
        $bayar = Payment::query()
            ->where('order_id', $this->cari($this->ulid)->id)
            ->findOrFail($pembayaranId);

        try {
            app(PembayaranManual::class)
                ->verifikasi($bayar, auth()->id());

            session()->flash('pesan-admin', __('site.msg.pesanan.pembayaran_diverifikasi'));
        } catch (Throwable $e) {
            $sebab = $e instanceof RuntimeException && ! $e instanceof QueryException
                ? $e->getMessage()
                : Galat::denganKode($e);

            $this->addError('pembayaran', __('site.msg.pesanan.gagal_verifikasi', ['pesan' => $sebab]));

            return;
        }

        $this->redirectRoute('admin.orders.show', ['ulid' => $this->ulid], navigate: true);
    }

    public function tolakPembayaran(int $pembayaranId): void
    {
        if (trim($this->alasanTolak) === '') {
            $this->addError('alasanTolak', __('site.msg.pesanan.alasan_wajib'));

            return;
        }

        $bayar = Payment::query()
            ->where('order_id', $this->cari($this->ulid)->id)
            ->findOrFail($pembayaranId);

        try {
            app(PembayaranManual::class)
                ->tolak($bayar, $this->alasanTolak, auth()->id());

            session()->flash('pesan-admin', 'Proof rejected. The customer can upload again.');
        } catch (Throwable $e) {
            $sebab = $e instanceof RuntimeException && ! $e instanceof QueryException
                ? $e->getMessage()
                : Galat::denganKode($e);

            $this->addError('pembayaran', __('site.msg.pesanan.gagal_tolak', ['pesan' => $sebab]));

            return;
        }

        $this->alasanTolak = '';
        $this->redirectRoute('admin.orders.show', ['ulid' => $this->ulid], navigate: true);
    }

    /**
     * Kirim ulang notifikasi pesanan (checklist F8-10).
     *
     * Dipakai ketika pembeli mengaku tidak menerima pesannya — surel masuk ke
     * folder sampah, atau nomor WhatsApp-nya salah ketika pesanan dibuat.
     * Yang dikirim adalah job yang sama dengan yang berjalan otomatis saat
     * status berubah, bukan pesan baru yang ditulis terpisah: pesan yang
     * berbeda dari aslinya hanya membingungkan pembeli.
     */
    public function kirimUlangNotifikasi(): void
    {
        $sah = [
            KirimNotifikasiPesanan::KEJADIAN_DIBUAT,
            KirimNotifikasiPesanan::KEJADIAN_DIBAYAR,
            KirimNotifikasiPesanan::KEJADIAN_DIKIRIM,
        ];

        if (! in_array($this->kejadianKirimUlang, $sah, true)) {
            session()->flash('galat-admin', __('site.msg.pesanan.notif_tidak_dikenal'));

            return;
        }

        $order = $this->cari($this->ulid);

        // Pembeli tanpa surel dan tanpa telepon tidak dapat dikirimi apa pun.
        // Lebih baik dikatakan terang-terangan daripada menampilkan "terkirim".
        if ($order->emailPenerima() === null && $order->teleponPenerima() === null) {
            session()->flash('galat-admin', __('site.msg.pesanan.tanpa_kontak'));

            return;
        }

        KirimNotifikasiPesanan::dispatch($order->id, $this->kejadianKirimUlang);

        session()->flash('pesan-admin', 'Notification "'.$this->kejadianKirimUlang.'" for '.$order->order_number.' queued for delivery.');
    }

    public function render(): View
    {
        $order = $this->cari($this->ulid);

        return view('livewire.admin.pesanan-detail', [
            'judul' => 'Order '.$order->order_number,
            'bagian' => 'Sales',
            'order' => $order,
            'alamat' => $order->alamat(),
            'tujuanSah' => $order->status->transisiSah(),
            'daftarKurir' => Courier::aktifNama(),
        ])->layout('layouts.admin', ['judul' => 'Order '.$order->order_number, 'bagian' => 'Sales']);
    }
}
