{{-- Email ke pemilik toko. Isinya untuk KEPUTUSAN, bukan basa-basi:
     apa yang dipesan, berapa nilainya, siapa pembelinya, dan ke mana dikirim. --}}
@component('mail::message')
@if ($kejadian === 'selesai')
# Pesanan selesai
@else
# Pesanan baru masuk
@endif

**{{ $pesanan->order_number }}** · {{ $metode }} · {{ $pesanan->currency }} **{{ rupiah($pesanan->grand_total) }}**

@component('mail::table')
| Produk | Jumlah | Subtotal |
|:-------|-------:|---------:|
@foreach ($pesanan->items as $item)
| {{ $item->product_name }}@if ($item->variant_name) · {{ $item->variant_name }}@endif | {{ $item->quantity }} | {{ $pesanan->currency }} {{ rupiah($item->subtotal) }} |
@endforeach
@endcomponent

@component('mail::panel')
**Pembeli**

{{ $nama }}
{{ $telepon }}
{{ $surel }}

**Kirim ke**

{{ $alamat }}
@endcomponent

@component('mail::button', ['url' => $urlPanel])
Buka di panel
@endcomponent

@if ($kejadian === 'baru')
Periksa pembayaran pesanan ini di panel sebelum diproses.
@endif

{{ $toko->teks('site_name', 'Bites Exhaust') }}
@endcomponent
