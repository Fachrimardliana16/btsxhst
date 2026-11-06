@php
    $aktif = fn (string $pola) => request()->routeIs($pola);

    // 5 slot navigasi mobile.
    //
    // Catatan: slot `star` di sini berisi tautan BANTUAN, bukan "disukai".
    // Versi sebelumnya memakai pola `front.products.index` — sama dengan slot
    // katalog — sehingga dua slot menyala bersamaan di halaman katalog, dan
    // tautannya juga menuju tempat yang sama. Tidak ada rute "produk disukai"
    // di proyek ini, jadi slot ini diarahkan ke halaman yang benar-benar ada.
    $slotNav = [
        ['nama' => 'home', 'pola' => 'front.home', 'rute' => route('front.home'), 'label' => __('site.nav.home')],
        ['nama' => 'grid', 'pola' => 'front.products.*', 'rute' => route('front.products.index'), 'label' => __('site.nav.products')],
        ['nama' => 'star', 'pola' => 'front.contact', 'rute' => route('front.contact'), 'label' => __('site.nav.contact')],
        ['nama' => 'search', 'pola' => null, 'rute' => null, 'label' => __('site.nav.search')],
        ['nama' => 'cart', 'pola' => 'front.cart*', 'rute' => route('front.cart'), 'label' => __('site.nav.cart')],
    ];
@endphp

{{-- Navigasi bawah bergaya aplikasi (ponsel & tablet).

     Latar, blur, tepi, dan bayangan diatur resources/css/bottom-nav.css —
     bukan utility Tailwind. Utility `bg-surface/95` yang lama menghasilkan
     latar 95% buram yang membunuh efek kaca, dan nama warnanya bergantung
     pada token tema yang sempat hilang dari build. --}}
<div class="nav-bawah lg:hidden">
    <nav class="nav-bawah__kaca" aria-label="{{ __('site.nav.menu') }}">
        <div class="nav-bawah__grid">
            @foreach ($slotNav as $slot)
                @php $isAktif = $slot['pola'] !== null && $aktif($slot['pola']); @endphp

                @if ($slot['rute'] === null)
                    <button type="button"
                            data-aksi="buka-cari"
                            class="nav-bawah__slot">
                        <span class="nav-bawah__ikon">
                            <x-ikon :name="$slot['nama']" :size="20" />
                        </span>
                        <span class="nav-bawah__label">{{ $slot['label'] }}</span>
                    </button>
                    @continue
                @endif

                <a href="{{ $slot['rute'] }}"
                   wire:navigate
                   aria-current="{{ $isAktif ? 'page' : 'false' }}"
                   class="nav-bawah__slot {{ $isAktif ? 'is-aktif' : '' }}">
                    <span class="nav-bawah__ikon">
                        <x-ikon :name="$slot['nama']" :size="20" />

                        @if ($slot['nama'] === 'cart')
                            <livewire:front.jumlah-keranjang :key="'badge-bawah-'.$slot['nama']" />
                        @endif
                    </span>

                    <span class="nav-bawah__label">{{ $slot['label'] }}</span>

                    @if ($isAktif)
                        <span class="nav-bawah__titik" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
</div>
