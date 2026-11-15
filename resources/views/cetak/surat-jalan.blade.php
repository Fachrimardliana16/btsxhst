@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \App\Models\Order $order */

    // Surat jalan ikut menempel di paket dan ditandatangani penerima.
    // Harga TIDAK dicetak di sini.
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Surat Jalan {{ $order->order_number }}</title>

    {{-- Gaya dasar yang sama dengan label resi dan invoice: huruf, warna,
         dan ketebalan garis. --}}
    @include('cetak._gaya', ['lebar' => '210mm', 'tinggi' => '297mm', 'margin' => '14mm'])

    <style>
        body { font-size: 12px; line-height: 1.4; }

        .kepala { padding: 3mm 4mm; }
        .kepala td { padding: 0; vertical-align: middle; }

        .judul-dokumen { text-align: right; }
        .judul-dokumen p { margin: 0; }
        .judul-dokumen .judul {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.08em;
            line-height: 1;
        }
        .judul-dokumen .nomor { font-size: 13px; font-weight: 700; margin-top: 1.5mm; }
        .judul-dokumen .tanggal { font-size: 11px; margin-top: 0.8mm; }

        .label-kecil {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .isi-kolom { font-size: 11px; line-height: 1.4; margin: 1mm 0 0; }
        .tegas { font-weight: 700; }
        .angka { font-size: 13px; font-weight: 700; }

        .kotak-resi {
            display: inline-block;
            margin-top: 1mm;
            padding: 1.5mm 3mm;
            border: 1.5px solid #000;
            font-weight: 700;
        }

        .kaki-isi { font-size: 11px; line-height: 1.4; }

        .tanda-tangan { margin-top: 8mm; }
        .tanda-tangan td { width: 50%; padding: 0 6mm; vertical-align: bottom; }
        .tanda-tangan td.sisi-kiri { border-right: 1.5px solid #000; }
        .tanda-tangan .peran { font-size: 11px; font-weight: 700; margin: 0 0 2mm; }
        .garis-ttd {
            border-top: 1px solid #000;
            margin-top: 22mm;
            padding-top: 1.5mm;
            font-size: 10px;
        }

        @media print {
            .tanpa-cetak { display: none !important; }
        }
    </style>
</head>
<body>

@unless ($pdf ?? false)
    <div class="tanpa-cetak" style="margin-bottom:4mm;text-align:center">
        <button type="button" onclick="window.print()" style="padding:2mm 5mm;font:inherit;cursor:pointer">Cetak</button>
        <span class="kecil">atau tekan Ctrl+P</span>
    </div>
@endunless

@include('cetak._salinan')

<div class="kotak">

    <table class="kepala" style="width:100%;border-collapse:collapse">
        <tr>
            <td>
                @include('cetak._logo-toko')
            </td>
            <td class="judul-dokumen">
                <p class="judul">DELIVERY NOTE</p>
                <p class="nomor">{{ $order->order_number }}</p>
                <p class="tanggal">{{ $order->created_at->format('d F Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="tiga-kolom" style="border-top:1.5px solid #000">
        <tr>
            <td class="sisi-kiri" style="width:52%">
                <p class="rapat label-kecil">Ship to</p>
                <p class="isi-kolom tegas">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>
                <p class="isi-kolom">
                    {{ $alamat['phone_number'] ?? $order->teleponPenerima() }}<br>
                    {{ $alamat['address_line_1'] ?? '' }}
                    @if (filled($alamat['address_line_2'] ?? null))<br>{{ $alamat['address_line_2'] }}@endif
                    @if (filled($alamat['city'] ?? null))<br>{{ $alamat['city'] }}, {{ $alamat['state_province'] }} {{ $alamat['postal_code'] }}@endif
                </p>
            </td>

            <td class="sisi-kiri" style="width:26%">
                <p class="rapat label-kecil">Courier</p>
                <p class="isi-kolom tegas">
                    {{ $order->shipping_courier ?: '—' }} {{ $order->shipping_service ?: '' }}
                </p>

                <p class="rapat label-kecil" style="margin-top:3mm">Tracking number</p>
                <div class="kotak-resi">{{ $order->tracking_number ?: '— not set —' }}</div>
            </td>

            <td style="width:22%">
                <p class="rapat label-kecil">Total weight</p>
                <p class="rapat angka">{{ number_format($order->beratTotal() / 1000, 2) }} kg</p>

                <p class="rapat label-kecil" style="margin-top:3mm">Total items</p>
                <p class="rapat angka">{{ $order->jumlahItem() }} pcs</p>
            </td>
        </tr>
    </table>

    <table class="tabel-isi" style="border-top:1.5px solid #000">
        <thead>
            <tr>
                <th style="width:8mm">#</th>
                <th>Item name</th>
                <th style="width:30mm">SKU</th>
                <th class="kanan" style="width:16mm">Qty</th>
                <th class="kanan" style="width:22mm">Weight</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($order->items as $i => $item)
                <tr class="{{ $loop->last ? 'baris-akhir' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->product_name }}
                        @if ($item->variant_name)
                            <br><span class="kecil">{{ $item->variant_name }}</span>
                        @endif
                    </td>
                    <td>{{ $item->sku }}</td>
                    <td class="kanan">{{ $item->quantity }}</td>
                    <td class="kanan">
                        {{ number_format(($item->weight_gram * $item->quantity) / 1000, 2) }} kg
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (filled($order->customer_note))
        <div class="bagian">
            <p class="rapat kaki-isi">
                <strong>Buyer note:</strong> {{ $order->customer_note }}
            </p>
        </div>
    @endif

    <div class="bagian">
        <table class="tanda-tangan" style="width:100%;border-collapse:collapse">
            <tr>
                <td class="sisi-kiri">
                    <p class="peran">Handed over by</p>
                    <div class="garis-ttd">Name and signature</div>
                </td>
                <td>
                    <p class="peran">Received by</p>
                    <div class="garis-ttd">Recipient name and signature</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="bagian">
        <p class="rapat kaki-label">
            Goods must be inspected upon receipt. Shipping damage must be reported
            on the same day with photo evidence.
        </p>
    </div>

</div>

</body>
</html>
