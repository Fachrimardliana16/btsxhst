{{-- Satu label pengiriman A5 (148x210 mm).

     Bentuknya mengikuti label kurir: satu kotak berbingkai, dipisah garis
     tebal per bagian — kepala (logo toko + kurir), barcode, status bayar,
     berat/tanggal, isi paket, penerima & pengirim berdampingan, catatan,
     lalu kalimat penutup.

     Dipakai dua jalur sekaligus:
       • cetak/resi.blade.php — satu pesanan, dengan tombol Cetak di peramban;
       • ResiBanyak            — banyak label dirangkai dalam SATU PDF.
     Karena itu label ini tidak memuat <html>/<head> maupun tombol: satu
     dokumen hanya boleh punya satu <head>, dan tombol yang tercetak ke PDF
     hanya menghabiskan ruang kertas label.

     Variabel:
       $order        — pesanan
       $toko         — pengaturan situs
       $alamat       — snapshot alamat pengiriman
       $salinan      — true bila pesanan dibatalkan (tanda air, F8-09)
       $nomorSeri    — label ke berapa dari seluruh rangkaian
       $totalLabel   — jumlah label dalam berkas itu
       $terakhir     — true untuk label paling akhir; memutus page-break-nya
                       supaya tidak ada halaman kosong sesudah label terakhir
       $gaya         — false bila pemanggil sudah menyediakan <style> sendiri
                       (dipakai ResiBanyak: satu berkas, banyak label) --}}
@if ($gaya ?? true)
    @include('cetak._gaya', ['margin' => '6mm'])
@endif

@php
    /** @var \App\Models\Order $order */
    /** @var \App\Support\Pengaturan $toko */

    $alamat = $alamat ?? $order->alamat();
    $negaraLuar = ! \App\Support\Internasional::dalamNegeri((string) $order->shipping_country);

    // Nomor yang dipindai kurir. Bila resi belum diisi, nomor pesanan dipakai
    // sebagai ganti: label tetap dapat ditempel dan dilacak di sistem sendiri,
    // dan baris kecil di bawah barcode menyebut bahwa resi resmi belum ada.
    $nomorResi = trim((string) $order->tracking_number);
    $sumberBarcode = $nomorResi !== '' ? $nomorResi : (string) $order->order_number;
    $barcode = \App\Support\Barcode128::dataUri($sumberBarcode, 124, 20);

    $telepon = $alamat['phone_number'] ?? $order->teleponPenerima();
    $kota = trim(collect([
        $alamat['city'] ?? null,
        $alamat['state_province'] ?? null,
        $alamat['postal_code'] ?? null,
    ])->filter()->implode(', '));

    $berat = $order->beratTotal();

    $nomorSeri = $nomorSeri ?? 1;
    $totalLabel = $totalLabel ?? 1;
@endphp

<div class="label{{ ($terakhir ?? false) ? ' label-terakhir' : '' }}">

    @if ($salinan ?? false)
        {{-- Tanda air SALINAN untuk pesanan yang sudah dibatalkan (F8-09).
             Memakai partial yang sama dengan dokumen lain: satu sumber. --}}
        @include('cetak._salinan')
    @endif

    <div class="kotak-label">

        <table class="kepala-label">
            <tr>
                <td>
                    {{-- Logo toko, sumbernya satu dengan invoice dan surat jalan. --}}
                    @include('cetak._logo-toko')
                </td>
                <td class="kanan">
                    <span class="kurir">{{ $order->shipping_courier ?: 'KURIR' }}</span>
                </td>
            </tr>
        </table>

        <div class="bagian barcode">
            @if ($barcode !== null)
                <img src="{{ $barcode }}" alt="Barcode {{ $sumberBarcode }}">
            @endif
            <p class="rapat resi-baris">
                TRACKING : {{ $sumberBarcode }}@if ($totalLabel > 1) <span class="kecil">({{ $nomorSeri }}/{{ $totalLabel }})</span>@endif
            </p>
            @if ($nomorResi === '')
                <p class="rapat kecil">Tracking number not set — using order number.</p>
            @endif
        </div>

        <div class="bagian cod-baris">
            {{-- Toko tidak menyediakan COD: semua pesanan dibayar di muka.
                 Baris besar ini karena itu menyebut NON-COD, bukan nominal
                 yang harus ditagih kurir. --}}
            <p class="rapat cod-besar">NON-COD</p>
            <p class="rapat layanan-baris">
                {{ $order->order_number }}
                &nbsp;|&nbsp; Service: {{ $order->shipping_service ?: '—' }}
            </p>
        </div>

        <table class="dua-kolom" style="border-top:1.5px solid #000">
            <tr>
                <td class="sisi-kiri">
                    <span class="meta-baris">Weight: {{ $berat > 0 ? number_format($berat) : '—' }} g</span>
                </td>
                <td>
                    <span class="meta-baris">Date: {{ $order->created_at?->format('d M Y') }}</span>
                </td>
            </tr>
        </table>

        {{-- Daftar isi dibatasi DUA baris. Terukur pada label A5:
             tiga baris sudah cukup untuk mendorong label meluap menjadi
             halaman kedua, apalagi bila pesanan juga membawa tanda air
             SALINAN dan catatan pembeli. --}}
        <div class="bagian">
            @foreach ($order->items->take(2) as $item)
                <p class="rapat isi-baris">
                    &bull; Qty: {{ $item->quantity }} | {{ $item->product_name }}@if ($item->variant_name) - {{ $item->variant_name }}@endif
                </p>
            @endforeach

            @if ($order->items->count() > 2)
                <p class="rapat isi-baris">&bull; +{{ $order->items->count() - 2 }} more items</p>
            @endif
        </div>

        <table class="dua-kolom" style="border-top:1.5px solid #000">
            <tr>
                <td class="sisi-kiri">
                    <p class="rapat nama-penerima">Recipient:</p>
                    <p class="rapat nama-penerima">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>

                    <p class="alamat-penerima">
                        {{ $alamat['address_line_1'] ?? '' }}
                        @if (filled($alamat['address_line_2'] ?? null))
                            {{ $alamat['address_line_2'] }}
                        @endif
                        @if ($kota !== '')
                            , {{ $kota }}
                        @endif
                        @if ($negaraLuar)
                            , {{ \App\Support\Internasional::namaNegara((string) $order->shipping_country) }}
                        @endif
                    </p>

                    <p class="rapat telp-penerima">Phone:</p>
                    <p class="rapat telp-penerima">{{ $telepon ?: '—' }}</p>
                </td>
                <td>
                    <p class="rapat nama-penerima">Sender:</p>
                    <p class="rapat nama-penerima">{{ $toko->teks('site_name', 'Bites Exhaust') }}</p>

                    <p class="alamat-penerima">{{ $toko->teks('address_full') ?: '—' }}</p>

                    <p class="rapat telp-penerima">Phone:</p>
                    <p class="rapat telp-penerima">{{ $toko->teks('phone_number') ?: '—' }}</p>
                </td>
            </tr>
        </table>

        @if (filled($order->customer_note))
            <div class="bagian">
                <p class="rapat catatan-baris">Note:</p>
                {{-- Dipotong 120 huruf: sekitar dua baris pada 9,5px. Catatan
                     yang lebih panjang mendorong label ke halaman kedua. --}}
                <p class="rapat catatan-baris">{{ \Illuminate\Support\Str::limit($order->customer_note, 120) }}</p>
            </div>
        @endif

        <div class="bagian">
            <p class="rapat kaki-label">*Sender must obtain proof of handover from the courier.</p>
        </div>

    </div>

</div>
