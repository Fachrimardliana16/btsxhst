<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Mengubah halaman cetak Blade menjadi PDF sungguhan (checklist F8-06, F8-07,
 * F8-08).
 *
 * Selama ini rute dokumen mengembalikan HTML dan baru menjadi PDF kalau admin
 * menekan "Simpan sebagai PDF" di dialog cetak peramban. Itu bukan PDF: berkas
 * hasilnya bergantung pada peramban, ukuran halaman tidak dijamin, dan tidak ada
 * yang dapat diperiksa keberhasilannya dari sisi server. Di sini HTML dirender
 * oleh dompdf, pustaka lokal — tidak ada layanan luar yang dihubungi, sesuai
 * PRD baris 291.
 *
 * Nama berkas memuat nomor pesanan, bukan judul bebas, supaya berkas yang
 * sudah diunduh tetap dapat dicocokkan dengan pesanannya berbulan-bulan
 * kemudian.
 */
class DokumenPdf
{
    /**
     * @param  string  $kertas  ukuran halaman dalam bentuk CSS, misalnya "A4"
     *                          atau "100mm 150mm"
     */
    public static function dari(View $tampilan, string $kertas, string $namaBerkas): Response
    {
        $opsi = new Options;

        // Fonta bawaan dompdf hanya huruf Latin dasar; "é" dan tanda kutip
        // tipografis hilang menjadi kotak kosong. DejaVu sudah ikut di dalam
        // paket, jadi dipakai tanpa unduhan.
        $opsi->setDefaultFont('DejaVu Sans');
        $opsi->setIsRemoteEnabled(false);
        $opsi->setIsHtml5ParserEnabled(true);

        // Gambar dari disk lokal (logo toko) tetap boleh dibaca.
        $opsi->setChroot([public_path(), storage_path(), base_path()]);

        $dompdf = new Dompdf($opsi);
        $dompdf->setPaper(self::ukuranKertas($kertas), 'portrait');
        $dompdf->loadHtml(self::html($tampilan, $kertas), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Ukuran lembar untuk dompdf.
     *
     * dompdf menerima nama kertas ("A4") atau larik empat angka dalam titik
     * [x1, y1, x2, y2] — bukan pasangan lebar-tinggi. Memberinya dua angka
     * membuat dompdf membaca indeks 2 dan 3 yang tidak ada, dan permintaannya
     * berakhir 500 tanpa menyebut ukuran kertas sebagai penyebabnya. Kertas
     * khusus seperti label A5 harus lewat jalur ini, karena namanya
     * tidak ada di daftar kertas bawaan dan akan jatuh ke "letter".
     *
     * @return string|array{0: float, 1: float, 2: float, 3: float}
     */
    private static function ukuranKertas(string $kertas): string|array
    {
        $kertas = trim($kertas);

        if (preg_match('/^([\d.]+)(mm|cm|in|pt|px)\s+([\d.]+)(mm|cm|in|pt|px)$/i', $kertas, $m) === 1) {
            return [
                0.0,
                0.0,
                self::keTitik((float) $m[1], $m[2]),
                self::keTitik((float) $m[3], $m[4]),
            ];
        }

        return strtoupper($kertas);
    }

    private static function keTitik(float $nilai, string $satuan): float
    {
        return match (strtolower($satuan)) {
            'mm' => $nilai * 72 / 25.4,
            'cm' => $nilai * 72 / 2.54,
            'in' => $nilai * 72,
            'px' => $nilai * 72 / 96,
            default => $nilai,
        };
    }

    /** HTML dokumen dengan ukuran @page yang sama dengan ukuran lembarnya. */
    private static function html(View $tampilan, string $kertas): string
    {
        $html = $tampilan->render();

        // @page di dalam Blade ditulis untuk peramban dan dompdf. Yang tidak
        // dijamin: `size: auto` (dompdf mengabaikannya tanpa pesan, hasilnya
        // ukuran bawaan) dan ukuran bernama yang tidak dikenal. Ukuran lembar
        // sudah pasti diberikan lewat setPaper(), jadi deklarasi size di HTML
        // hanya perlu disamakan.
        //
        // Margin TIDAK ikut ditimpa. Versi sebelumnya mengganti seluruh blok
        // @page dengan "margin: 8mm", sehingga margin 4mm pada label — dan
        // 14mm pada invoice — hilang tanpa terlihat. Yang diubah hanya `size`.
        $html = preg_replace_callback(
            '/@page\s*\{([^}]*)\}/i',
            function (array $m) use ($kertas): string {
                $sisa = preg_replace('/size\s*:[^;]*(;|$)/i', '', $m[1]) ?? '';
                $sisa = trim($sisa);

                return '@page { size: '.$kertas.';'.($sisa !== '' ? ' '.$sisa : '').' }';
            },
            $html,
            1,
        );

        return $html ?? '';
    }

    /**
     * Versi untuk HTML yang sudah jadi (bukan View).
     *
     * Dipakai cetak resi banyak: dua puluh label disatukan dalam satu berkas,
     * dan masing-masing dirender terpisah lalu dirangkai. dompdf menerima satu
     * dokumen, jadi dua puluh View tidak bisa dilempar sekaligus.
     */
    public static function dariHtml(string $html, string $kertas, string $namaBerkas): Response
    {
        $opsi = new Options;
        $opsi->setDefaultFont('DejaVu Sans');
        $opsi->setIsRemoteEnabled(false);
        $opsi->setIsHtml5ParserEnabled(true);
        $opsi->setChroot([public_path(), storage_path(), base_path()]);

        $dompdf = new Dompdf($opsi);
        $dompdf->setPaper(self::ukuranKertas($kertas), 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
