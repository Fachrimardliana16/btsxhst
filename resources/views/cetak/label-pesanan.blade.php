{{-- Satu label pengiriman 100x150 mm.

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
    @include('cetak._gaya', ['margin' => '4mm'])
@endif

@php
    /** @var \App\Models\Order $order */
    /** @var \App\Support\Pengaturan $toko */

    // Kenapa $gaya ada: lihat catatan di ResiBanyak. Ringkasnya — <style> di
    // dalam <body> yang diulang untuk setiap label ikut dihitung sebagai isi
    // halaman, dan satu label pun menjadi dua halaman.
    $alamat = $alamat ?? $order->alamat();
    $negaraLuar = ! \App\Support\Internasional::dalamNegeri((string) $order->shipping_country);

    // Nomor yang dipindai kurir. Bila resi belum diisi, nomor pesanan dipakai
    // sebagai ganti: label tetap dapat ditempel dan dilacak di sistem sendiri,
    // dan baris kecil di bawah barcode menyebut bahwa resi resmi belum ada.
    $nomorResi = trim((string) $order->tracking_number);
    $sumberBarcode = $nomorResi !== '' ? $nomorResi : (string) $order->order_number;
    $barcode = \App\Support\Barcode128::dataUri($sumberBarcode, 84, 12);

    $telepon = $alamat['phone_number'] ?? $order->teleponPenerima();
    $kota = trim((string) ($alamat['city'] ?? ''));
    $provinsi = trim((string) ($alamat['state_province'] ?? ''));
    $kodePos = trim((string) ($alamat['postal_code'] ?? ''));

    $berat = $order->beratTotal();

    // Label tunggal (cetak/resi.blade.php) tidak mengirim dua variabel ini.
    $nomorSeri = $nomorSeri ?? 1;
    $totalLabel = $totalLabel ?? 1;
@endphp

<div class="label{{ ($terakhir ?? false) ? ' label-terakhir' : '' }}">

    @if ($salinan ?? false)
        {{-- Tanda air SALINAN untuk pesanan yang sudah dibatalkan (F8-09).
             Memakai partial yang sama dengan dokumen lain: satu sumber. --}}
        @include('cetak._salinan')
    @endif

    <table class="kepala-label">
        <tr>
            <td>
                <span class="kurir">{{ $order->shipping_courier ?: 'KURIR' }}</span>
            </td>
            <td class="kanan">
                @if (filled($order->shipping_service))
                    <span class="layanan">{{ $order->shipping_service }}</span>
                @endif
                <br>
                <span class="cod">NON-COD</span>
                @if ($totalLabel > 1)
                    <br><span class="kecil">{{ $nomorSeri }}/{{ $totalLabel }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="barcode">
        @if ($barcode !== null)
            <img src="{{ $barcode }}" alt="Barcode {{ $sumberBarcode }}">
        @endif
        <div class="resi-teks">{{ $sumberBarcode }}</div>
        @if ($nomorResi === '')
            <div class="kecil">Nomor resi belum diisi — ini nomor pesanan.</div>
        @endif
    </div>

    {{-- Penerima: blok terbesar, karena inilah yang dibaca kurir di depan
         pintu. Nama, telepon, dan kota diberi bobot paling kuat. --}}
    <div class="blok">
        <p class="judul-kecil rapat">Penerima</p>
        <p class="nama-penerima rapat">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>
        @if (filled($telepon))
            <p class="telp-penerima rapat">{{ $telepon }}</p>
        @endif
        <p class="alamat-penerima rapat">
            {{ $alamat['address_line_1'] ?? '' }}
            @if (filled($alamat['address_line_2'] ?? null))
                <br>{{ $alamat['address_line_2'] }}
            @endif
        </p>
        <p class="kota-penerima rapat">
            {{ collect([$kota, $provinsi])->filter()->implode(', ') }}{{ $kodePos !== '' ? ' '.$kodePos : '' }}
        </p>
        @if ($negaraLuar)
            <p class="kota-penerima rapat">{{ \App\Support\Internasional::namaNegara((string) $order->shipping_country) }}</p>
        @endif
    </div>

    <div class="blok">
        <p class="judul-kecil rapat">Pengirim</p>
        <p class="pengirim-label rapat">
            <b>{{ $toko->teks('site_name', 'Bites Exhaust') }}</b>
            @if (filled($toko->teks('phone_number')))
                &middot; {{ $toko->teks('phone_number') }}
            @endif
        </p>
        @if (filled($toko->teks('address_full')))
            <p class="pengirim-label rapat">{{ $toko->teks('address_full') }}</p>
        @endif
    </div>

    <div class="blok">
        <table class="meta-label">
            <tr>
                <td class="judul-kecil" style="width:24mm">No. Pesanan</td>
                <td><b>{{ $order->order_number }}</b></td>
                <td class="kanan">{{ $order->created_at?->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="judul-kecil">Isi Paket</td>
                <td colspan="2">
                    {{ $order->jumlahItem() }} pcs
                    <span class="kecil">&middot;</span>
                    {{ $berat > 0 ? number_format($berat / 1000, 2, ',', '.').' kg' : 'berat belum diisi' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Daftar isi dibatasi DUA baris. Terukur pada label 100x150 mm: tiga
         baris sudah cukup untuk mendorong label meluap menjadi halaman kedua,
         apalagi bila pesanan juga membawa tanda air SALINAN dan catatan
         pembeli. Ringkasan "+N lainnya" menjaga informasi tetap lengkap.

         Ditulis sebagai paragraf, bukan tabel: dompdf memberi kolom pertama
         tabel otomatis lebar yang jauh lebih besar dari isinya, sehingga nama
         barang terdorong ke tengah label. --}}
    <div class="blok">
        @foreach ($order->items->take(2) as $item)
            <p class="rapat isi-baris">
                <span class="isi-qty">{{ $item->quantity }}&times;</span>{{ $item->product_name }}@if ($item->variant_name)
                    <span class="kecil">&middot; {{ $item->variant_name }}</span>
                @endif
            </p>
        @endforeach

        @if ($order->items->count() > 2)
            <p class="rapat isi-baris kecil">+{{ $order->items->count() - 2 }} barang lainnya</p>
        @endif
    </div>

    @if (filled($order->customer_note))
        <div class="blok">
            <p class="judul-kecil rapat">Catatan Pembeli</p>
            {{-- Dipotong 120 huruf: sekitar dua baris pada 9,5px. Catatan yang
                 lebih panjang mendorong label ke halaman kedua, dan label
                 terbelah menempel di paket yang salah. --}}
            <p class="alamat-penerima rapat" style="margin-top:.6mm">{{ \Illuminate\Support\Str::limit($order->customer_note, 120) }}</p>
        </div>
    @endif

    @if ($negaraLuar)
        <p class="kaki-label"><b>PENGIRIMAN LUAR NEGERI — CARGO INTERNASIONAL</b></p>
    @endif

    <p class="kaki-label">
        Barang wajib diperiksa saat diterima. Klaim maksimal 3 hari sejak paket diterima.
    </p>

</div>
