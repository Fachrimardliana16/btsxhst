{{--
    Halaman kembali dari PayPal.

    Tiga keadaan, dan hanya tiga. Pembeli tidak boleh melihat layar kosong
    atau pesan teknis: yang ia butuhkan hanya "berhasil" atau "berapa nomor
    pesanan saya untuk menghubungi toko".
--}}
<div class="container-page py-10 lg:py-20">
    <div class="mx-auto max-w-lg">

        @if ($keadaan === 'memproses')
            <div class="plate p-6 text-center" role="status" aria-live="polite">
                <p class="part-no">MENUNGGU PAYPAL</p>
                <h1 class="mt-2 font-display" style="font-size: var(--t-h3)">Memeriksa pembayaran…</h1>
                <p class="mt-2 text-sm text-muted">Jangan tutup halaman ini. Prosesnya hanya beberapa detik.</p>
            </div>
        @endif

        @if ($keadaan === 'lunas')
            <div class="plate p-6" role="status" aria-live="polite">
                <span class="inline-flex items-center gap-2 rounded-full bg-accent/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-accent">
                    <x-ikon name="box" :size="14" /> Lunas
                </span>

                <h1 class="mt-3 font-display" style="font-size: var(--t-h3)">Pembayaran berhasil</h1>
                <p class="mt-2 text-sm text-muted">{{ $pesan }}</p>

                @if ($pesanan)
                    <div class="measure-line my-4"></div>

                    <dl class="flex items-baseline justify-between text-sm">
                        <dt class="text-muted">Nomor pesanan</dt>
                        <dd class="tech">{{ $pesanan->order_number }}</dd>
                    </dl>
                    <dl class="mt-2 flex items-baseline justify-between text-sm">
                        <dt class="text-muted">Total dibayar</dt>
                        <dd class="tech">{{ $pesanan->currency }} {{ rupiah($pesanan->grand_total) }}</dd>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <a href="{{ route('front.order.success', $pesanan) }}" wire:navigate class="btn btn-primary">
                            Lihat pesanan
                        </a>
                        <a href="{{ route('front.products.index') }}" wire:navigate class="btn btn-outline">
                            Lanjut belanja
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @if ($keadaan === 'gagal')
            <div class="plate p-6" role="alert">
                <h1 class="font-display" style="font-size: var(--t-h3)">Pembayaran belum selesai</h1>
                <p class="mt-2 text-sm text-muted">{{ $pesan }}</p>

                @if ($pesanan)
                    <div class="measure-line my-4"></div>
                    <dl class="flex items-baseline justify-between text-sm">
                        <dt class="text-muted">Nomor pesanan</dt>
                        <dd class="tech">{{ $pesanan->order_number }}</dd>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-2">
                        {{-- Kesempatan kedua yang jelas: pesanan sudah ada,
                             yang gagal hanya pembayarannya. Membuat pesanan
                             baru akan menggandakan tahanan stok. --}}
                        <a href="{{ route('front.paypal.mulai', $pesanan) }}" class="btn btn-primary">
                            Coba bayar lagi
                        </a>
                        <a href="{{ route('front.contact') }}" wire:navigate class="btn btn-outline">
                            Hubungi kami
                        </a>
                    </div>
                @endif
            </div>
        @endif

    </div>
</div>
