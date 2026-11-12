{{--
    Email ke pembeli.

    Ditulis dengan sintaks Markdown milik Laravel supaya hasil HTML-nya
    otomatis rapi di semua klien email (Gmail, Outlook, Apple Mail) TANPA
    CSS eksternal — banyak klien email membuang <style>, jadi tata letak
    berbasis tabel yang dihasilkan Laravel jauh lebih aman.

    Susunannya: satu kalimat pembuka, rincian pesanan, alamat, lalu tautan.
    Tidak ada banner besar di atas: pembeli membuka email ini untuk tahu
    status pesanannya, bukan untuk melihat merek.
--}}
@component('mail::message')
# {{ $toko->teks('site_name', 'Bites Exhaust') }}

Halo **{{ $nama }}**,

@if ($kejadian === 'dibayar')
Pembayaran pesanan **{{ $pesanan->order_number }}** sudah kami terima. Pesanan akan segera kami siapkan.
@elseif ($kejadian === 'dikirim')
Pesanan **{{ $pesanan->order_number }}** sudah kami kirimkan.
@elseif ($kejadian === 'selesai')
Pesanan **{{ $pesanan->order_number }}** sudah selesai. Terima kasih sudah berbelanja.
@else
Pesanan **{{ $pesanan->order_number }}** sudah kami terima dan sedang kami periksa.
@endif

@component('mail::table')
| Produk | Jumlah | Subtotal |
|:-------|-------:|---------:|
@foreach ($pesanan->items as $item)
| {{ $item->product_name }}@if ($item->variant_name) · {{ $item->variant_name }}@endif | {{ $item->quantity }} | {{ dolar($item->subtotal) }} |
@endforeach
@endcomponent

**Total: {{ dolar($pesanan->grand_total) }}**

@if ($kejadian === 'dibuat' && $rekening)
@component('mail::panel')
**Transfer ke {{ $rekening['bank'] }} {{ $rekening['nomor'] }}**
a.n. {{ $rekening['atas_nama'] }}

Setelah transfer, unggah bukti lewat halaman pesanan Anda.
@endcomponent
@endif

@if ($kejadian === 'dibuat' && ! $rekening && $pesanan->payment_method?->value === 'paypal')
Pesanan ini menunggu pembayaran lewat PayPal. Anda dapat melanjutkannya dari halaman pesanan.
@endif

@component('mail::panel')
**Dikirim ke**

{{ $alamat }}
@endcomponent

@component('mail::button', ['url' => $urlPesanan])
Lihat pesanan
@endcomponent

Bila butuh bantuan, hubungi kami di {{ $toko->teks('phone_number', '-') }}.

Salam,
{{ $toko->teks('site_name', 'Bites Exhaust') }}
@endcomponent
