@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \App\Models\Order $order */

    // Resi tunggal: satu label pengiriman 100x150 mm.
    //
    // Isi label tinggal di cetak/label-pesanan.blade.php, yang juga dipakai
    // cetak resi banyak. Satu sumber berarti perbaikan tata letak tidak perlu
    // dikerjakan dua kali dan tidak mungkin berbeda antara dua jalur cetak.
    //
    // $pdf true saat berkas dibuat untuk diunduh: tombol Cetak tidak ikut
    // dicetak. dompdf tidak menjalankan @media print, jadi menyembunyikan
    // tombol lewat media query saja membuatnya tetap muncul di PDF.
    $pdf = $pdf ?? false;
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resi {{ $order->order_number }}</title>

    {{-- Ukuran kertas dinyatakan di sini supaya peramban tidak menyisipkan
         margin bawaan yang membuat label meleset dari kertas label. --}}
    @include('cetak._gaya', ['margin' => '4mm'])

    <style>
        @media print {
            .tanpa-cetak { display: none !important; }
        }
    </style>
</head>
<body>

@unless ($pdf)
    <div class="tanpa-cetak" style="margin-bottom:4mm;text-align:center">
        <button type="button" onclick="window.print()"
                style="padding:2mm 5mm;font:inherit;cursor:pointer">
            Cetak
        </button>
        <span class="kecil">atau tekan Ctrl+P</span>
    </div>
@endunless

@include('cetak.label-pesanan', [
    'alamat' => $alamat ?? $order->alamat(),
    'salinan' => $salinan ?? false,
    'nomorSeri' => 1,
    'totalLabel' => 1,
    // Label tunggal adalah label terakhir: tanpa ini page-break-after
    // menghasilkan satu halaman kosong di ujung PDF.
    'terakhir' => true,
    'gaya' => false,
])

</body>
</html>
