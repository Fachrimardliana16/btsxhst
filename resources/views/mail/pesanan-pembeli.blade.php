{{--
    Email to the buyer.

    Written with Laravel's Markdown syntax so the resulting HTML renders
    cleanly in every mail client (Gmail, Outlook, Apple Mail) without external
    CSS — many clients strip <style>, so the table layout Laravel generates is
    far safer.

    Layout: one opening line, order details, address, then the link. No large
    banner at the top: the buyer opens this email to learn the status of their
    order, not to admire the brand.
--}}
@component('mail::message')
# {{ $toko->teks('site_name', 'Bites Exhaust') }}

Hello **{{ $nama }}**,

@if ($kejadian === 'dibayar')
We have received payment for order **{{ $pesanan->order_number }}**. We will prepare it shortly.
@elseif ($kejadian === 'dikirim')
Order **{{ $pesanan->order_number }}** has been shipped.
@elseif ($kejadian === 'selesai')
Order **{{ $pesanan->order_number }}** is complete. Thank you for shopping with us.
@else
We have received order **{{ $pesanan->order_number }}** and are reviewing it.
@endif

@component('mail::table')
| Item | Qty | Subtotal |
|:-----|----:|---------:|
@foreach ($pesanan->items as $item)
| {{ $item->product_name }}@if ($item->variant_name) · {{ $item->variant_name }}@endif | {{ $item->quantity }} | {{ dolar($item->subtotal) }} |
@endforeach
@endcomponent

**Total: {{ dolar($pesanan->grand_total) }}**

@if ($kejadian === 'dibuat' && $rekening)
@component('mail::panel')
**Transfer to {{ $rekening['bank'] }} {{ $rekening['nomor'] }}**
a.n. {{ $rekening['atas_nama'] }}

After transferring, upload the receipt from your order page.
@endcomponent
@endif

@if ($kejadian === 'dibuat' && ! $rekening && $pesanan->payment_method?->value === 'paypal')
This order is awaiting payment via PayPal. You can continue from your order page.
@endif

@component('mail::panel')
**Ship to**

{{ $alamat }}
@endcomponent

@component('mail::button', ['url' => $urlPesanan])
View order
@endcomponent

Need help? Contact us at {{ $toko->teks('phone_number', '-') }}.

Regards,
{{ $toko->teks('site_name', 'Bites Exhaust') }}
@endcomponent
