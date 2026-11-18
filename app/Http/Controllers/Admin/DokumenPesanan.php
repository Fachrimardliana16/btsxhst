<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Support\DokumenPdf;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Dokumen cetak pesanan: resi, invoice, dan surat jalan.
 *
 * Ketiganya dirender sebagai HTML lalu diubah menjadi PDF oleh dompdf
 * (checklist F8-06, F8-07, F8-08) — bukan HTML yang baru menjadi PDF kalau
 * admin menekan "Simpan sebagai PDF" di peramban. Ukuran lembarnya karena itu
 * ditentukan di sini dan dapat diperiksa dari berkasnya.
 *
 * Resi dan surat jalan sengaja TIDAK memuat harga: dokumen itu ikut menempel
 * di paket, dan harga beli tidak perlu diketahui kurir maupun tetangga yang
 * melihat paketnya. Invoice yang memuat harga.
 *
 * Pesanan yang dibatalkan memakai tanda air SALINAN pada ketiga dokumen
 * (checklist F8-09): setelah dibatalkan, dokumen yang beredar tidak boleh lagi
 * terbaca sebagai dokumen sah.
 */
class DokumenPesanan
{
    /** Resi / label pengiriman: A5 (148x210 mm). */
    private const KERTAS_RESI = 'A5';

    private const KERTAS_A4 = 'A4';

    public function resi(string $ulid): Response
    {
        $order = $this->pesanan($ulid);

        return DokumenPdf::dari(
            view('cetak.resi', $this->data($order, denganAlamat: true, pdf: true)),
            self::KERTAS_RESI,
            'resi-'.$order->order_number.'.pdf',
        );
    }

    public function invoice(string $ulid): Response
    {
        $order = $this->pesanan($ulid);

        return DokumenPdf::dari(
            view('cetak.invoice', $this->data($order, denganAlamat: true, pdf: true)),
            self::KERTAS_A4,
            'invoice-'.$order->order_number.'.pdf',
        );
    }

    public function suratJalan(string $ulid): Response
    {
        $order = $this->pesanan($ulid);

        return DokumenPdf::dari(
            view('cetak.surat-jalan', $this->data($order, denganAlamat: true, pdf: true)),
            self::KERTAS_A4,
            'surat-jalan-'.$order->order_number.'.pdf',
        );
    }

    /**
     * Pratinjau HTML di peramban.
     *
     * Dipertahankan karena admin kadang hanya ingin melihat isinya sebelum
     * mencetak, dan karena peramban dapat mencetak ke printer langsung tanpa
     * mengunduh berkas lebih dulu.
     *
     * Urutan parameternya mengikuti urutan pada rute ({ulid} lalu {jenis}):
     * Laravel mengisi parameter skalar secara berurutan, bukan menurut nama,
     * jadi urutan yang tertukar menukar nilainya juga — dan pesanan dicari
     * dengan kata "invoice", yang tidak pernah ketemu.
     */
    public function pratinjau(string $ulid, string $jenis): View
    {
        $order = $this->pesanan($ulid);

        abort_unless(in_array($jenis, ['resi', 'invoice', 'surat-jalan'], true), 404);

        return view('cetak.'.$jenis, $this->data($order, denganAlamat: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Order $order, bool $denganAlamat, bool $pdf = false): array
    {
        return [
            'order' => $order,
            'toko' => Pengaturan::instance(),
            'alamat' => $denganAlamat ? $order->alamat() : [],
            'salinan' => $order->status === StatusPesanan::Cancelled,
            'pdf' => $pdf,
        ];
    }

    private function pesanan(string $ulid): Order
    {
        return Order::query()
            ->with(['items', 'user', 'payments', 'statusHistory'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }
}
