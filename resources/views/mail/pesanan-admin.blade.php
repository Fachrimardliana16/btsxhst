{{-- Email to the shop owner. Content is for DECISIONS, not pleasantries:
     what was ordered, how much it is worth, who bought it, and where it ships. --}}
@component('mail::message')
@if ($kejadian === 'selesai')
# Order completed
@else
# New order received
@endif

**{{ $pesanan->order_number }}** · {{ $metode }} · **{{ dolar($pesanan->grand_total) }}**

@component('mail::table')
| Item | Qty | Subtotal |
|:-----|----:|---------:|
@foreach ($pesanan->items as $item)
| {{ $item->product_name }}@if ($item->variant_name) · {{ $item->variant_name }}@endif | {{ $item->quantity }} | {{ dolar($item->subtotal) }} |
@endforeach
@endcomponent

@component('mail::panel')
**Buyer**

{{ $nama }}
{{ $telepon }}
{{ $surel }}

**Ship to**

{{ $alamat }}
@endcomponent

@component('mail::button', ['url' => $urlPanel])
Open in panel
@endcomponent

@if ($kejadian === 'baru')
Verify payment for this order in the panel before processing it.
@endif

{{ $toko->teks('site_name', 'Bites Exhaust') }}
@endcomponent
