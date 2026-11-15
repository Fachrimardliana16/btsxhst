@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \App\Models\Order $order */
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>

    {{-- Gaya dasar yang sama dengan label resi: huruf, warna, dan ketebalan
         garis. Dokumen-dokumen cetak harus terbaca sebagai satu keluarga. --}}
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

        .cap {
            display: inline-block;
            margin-top: 2mm;
            padding: 0.8mm 3mm;
            border: 1.5px solid;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .cap-lunas { color: #2f6b3f; border-color: #2f6b3f; }
        .cap-belum { color: #a51c2c; border-color: #a51c2c; }

        .label-kecil {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .isi-kolom { font-size: 11px; line-height: 1.4; margin: 1mm 0 0; }
        .tegas { font-weight: 700; }

        .ringkasan { margin-left: auto; width: 64mm; }
        .ringkasan td { padding: 1mm 3mm; border: 0; font-size: 11.5px; }
        .ringkasan .total td {
            border-top: 1.5px solid #000;
            font-size: 14px;
            font-weight: 700;
            padding-top: 2mm;
        }

        .catatan-teks { font-size: 11px; line-height: 1.4; }

        @media print {
            .tanpa-cetak { display: none !important; }
        }
    </style>
</head>
<body>

{{-- dompdf tidak menjalankan @media print, jadi tombol ini harus tidak
     dirender sama sekali saat berkas dibuat untuk diunduh. --}}
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
                <p class="judul">INVOICE</p>
                <p class="nomor">{{ $order->order_number }}</p>
                <p class="tanggal">Issued {{ $order->created_at->format('d F Y') }}</p>
                <p>
                    @if ($order->payment_status === \App\Enums\StatusPembayaran::Paid)
                        <span class="cap cap-lunas">PAID</span>
                    @else
                        <span class="cap cap-belum">UNPAID</span>
                    @endif
                </p>
            </td>
        </tr>
    </table>

    <table class="tiga-kolom" style="border-top:1.5px solid #000">
        <tr>
            <td class="sisi-kiri">
                <p class="rapat label-kecil">Billed to</p>
                <p class="isi-kolom tegas">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>
                <p class="isi-kolom">
                    {{ $order->emailPenerima() ?? $order->user?->email ?? '' }}<br>
                    {{ $alamat['phone_number'] ?? $order->teleponPenerima() }}
                </p>
            </td>

            <td class="sisi-kiri">
                <p class="rapat label-kecil">Shipping address</p>
                <p class="isi-kolom">
                    {{ $alamat['address_line_1'] ?? '' }}
                    @if (filled($alamat['address_line_2'] ?? null))<br>{{ $alamat['address_line_2'] }}@endif
                    @if (filled($alamat['city'] ?? null))<br>{{ $alamat['city'] }}, {{ $alamat['state_province'] }} {{ $alamat['postal_code'] }}@endif
                </p>
            </td>

            <td>
                <p class="rapat label-kecil">Shipping</p>
                <p class="isi-kolom">
                    Courier: {{ $order->shipping_courier ?: '—' }}<br>
                    Service: {{ $order->shipping_service ?: '—' }}<br>
                    Tracking: {{ $order->tracking_number ?: '—' }}
                </p>
            </td>
        </tr>
    </table>

    <table class="tabel-isi" style="border-top:1.5px solid #000">
        <thead>
            <tr>
                <th style="width:8mm">#</th>
                <th>Item</th>
                <th style="width:24mm">SKU</th>
                <th class="kanan" style="width:14mm">Qty</th>
                <th class="kanan" style="width:26mm">Price</th>
                <th class="kanan" style="width:28mm">Amount</th>
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
                    <td class="kanan">{{ dolar($item->unit_price) }}</td>
                    <td class="kanan">{{ dolar($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bagian">
        <table class="ringkasan">
            <tr>
                <td>Subtotal</td>
                <td class="kanan">{{ dolar($order->subtotal) }}</td>
            </tr>

            @if ((float) $order->discount_amount > 0)
                <tr>
                    <td>
                        Discount @if ($order->coupon) ({{ $order->coupon->code }}) @endif
                    </td>
                    <td class="kanan">−{{ dolar($order->discount_amount) }}</td>
                </tr>
            @endif

            @if ((float) $order->shipping_cost > 0)
                <tr>
                    <td>Shipping</td>
                    <td class="kanan">{{ dolar($order->shipping_cost) }}</td>
                </tr>
            @endif

            @if ((float) $order->tax_amount > 0)
                <tr>
                    <td>Tax</td>
                    <td class="kanan">{{ dolar($order->tax_amount) }}</td>
                </tr>
            @endif

            <tr class="total">
                <td>Total</td>
                <td class="kanan">{{ dolar($order->grand_total) }}</td>
            </tr>
        </table>
    </div>

    @if ((float) $order->shipping_cost <= 0)
        <div class="bagian">
            <p class="rapat catatan-teks">
                Shipping cost has not been set for this order. If shipping is charged,
                the amount will appear on the next invoice.
            </p>
        </div>
    @endif

    <div class="bagian">
        <p class="rapat catatan-teks">
            <strong>Terms.</strong>
            An order is valid once payment is received. Orders unpaid within 24 hours are
            cancelled automatically and stock is returned. Warranty covers manufacturing
            defects, not damage caused by incorrect installation.
        </p>
    </div>

    <div class="bagian">
        <p class="rapat kaki-label">
            This invoice is generated automatically by the system. No wet signature is required.
        </p>
    </div>

</div>

</body>
</html>
