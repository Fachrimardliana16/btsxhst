@php
    /** @var \App\Support\Pengaturan $toko */
    $logo = $toko->teks('site_logo_light');
    $logoTerang = $toko->teks('site_logo_dark');

    // Beranda memakai bilah yang sama dengan halaman lain, hanya transparan
    // saat masih di atas hero (lihat .navbar-hero di resources/css/navbar.css).
    // Wujud sidebar dihapus.
    $diBeranda = request()->routeIs('front.home');

    // Kategori dipakai menu drawer (ponsel).
    //
    // Dulu query ini jalan pada SETIAP render navbar — artinya setiap halaman,
    // untuk setiap pengunjung, walaupun drawernya tidak pernah dibuka. Hasilnya
    // sekarang diambil lewat CacheKatalog seperti daftar produk: kuncinya
    // memuat nomor versi katalog, dan panel admin sudah menaikkan versi itu
    // setiap kali kategori dibuat, diubah, atau dihapus. Jadi invalidasi
    // otomatis, tanpa kunci yang perlu diingat satu per satu.
    $kategoriNav = \App\Support\CacheKatalog::ingat(
        'navbar.kategori',
        ['limit' => 8, 'bahasa' => app()->getLocale()],
        600,
        fn () => \App\Models\Category::query()->aktif()->terurut()->take(8)->get(),
    );

    // Menu utama ditulis sekali, dipakai di kedua wujud. `pola` menentukan
    // penanda aktif; dua label terakhir sengaja memakai route yang sama
    // dengan katalog karena memang menuju ke sana.
    $menuUtama = [
        ['pola' => 'front.home', 'label' => __('site.nav.home'), 'rute' => route('front.home')],
        ['pola' => 'front.products.index', 'label' => __('site.nav.products'), 'rute' => route('front.products.index')],
        ['pola' => 'front.about', 'label' => __('site.nav.about'), 'rute' => route('front.about')],
        ['pola' => 'front.contact', 'label' => __('site.nav.contact'), 'rute' => route('front.contact')],
        ['pola' => 'front.order.track', 'label' => __('site.order.track'), 'rute' => route('front.order.track')],
    ];

    $aktif = fn (string $pola) => request()->routeIs($pola);

@endphp

{{-- Bar atas. Dua wujud, satu markup:

     1. SIDEBAR (hanya beranda, di atas hero, ≥1280px)
        logo di atas, menu vertikal berjarak, sosial di kaki. Kolom ini yang
        membuat hero terasa seperti halaman spesifikasi, bukan banner toko.

     2. BILAH (semua halaman, dan beranda setelah digulir)
        logo kiri, menu di TENGAH, utilitas kanan.

     Perpindahannya ditulis app.js ke `data-nav-ramping` pada <html>; CSS di
     bawah membaca atribut itu. Tanpa JS, beranda tetap memakai wujud bilah —
     navigasi tidak pernah hilang, hanya tidak berganti wujud. --}}
<div x-data="{ menu: false, cari: false, kategori: false }">

{{-- Latar navbar DIATUR CSS (.race-navbar / .navbar-hero / .navbar-melayang),
     bukan utility. Sebelumnya di sini ada `bg-surface/95`, dan kelas itu tidak
     pernah ter-generate Tailwind (build CSS tidak memuatnya) — akibatnya
     `background` jatuh ke transparan dan navbar tak terlihat di bagian
     halaman yang berlatar terang. --}}
<header class="race-navbar {{ $diBeranda ? 'navbar-hero' : '' }}">
    <div class="race-navbar__signal" aria-hidden="true"><span></span><span></span><span></span></div>

    <div class="container-page navbar-bilah h-14 lg:h-[72px]">

        {{-- Brand. Dirender SEKALI di sini, di kedua wujud navbar.

             Sebelumnya nama toko juga muncul di tengah hero (`.hero-race__merek`)
             dan sebagai huruf raksasa di latar (`.hero-race__outline`), jadi
             satu layar memuat tiga kali nama yang sama. Keduanya sudah
             dihapus; brand resmi hanya di navbar, dan hero memakai judul
             slide sebagai teks utamanya.

             Bila logo gambar diisi di panel, logo itu yang dipakai; bila
             kosong, nama toko sebagai teks. Dua-duanya tidak pernah tampil
             bersamaan. --}}
        <div class="navbar-kiri flex min-w-0 items-center gap-2">
            {{-- Mobile toggle menu --}}
            <button type="button" class="btn-icon lg:hidden shrink-0" @click="menu = true"
                    :aria-expanded="menu" aria-controls="drawer-menu">
                <x-ikon name="menu" />
                <span class="visually-hidden">{{ __('site.nav.open_menu') }}</span>
            </button>

            <a href="{{ route('front.home') }}" class="navbar-logo flex min-w-0 items-center gap-1.5">
            @if ($logo || $logoTerang)
                <img src="{{ \App\Models\ProductMedia::urlMedia($logo ?: $logoTerang) }}"
                     alt="{{ $toko->teks('site_name') }}"
                     width="140" height="40" class="h-7 w-auto max-w-full object-contain lg:h-9">
            @else
                {{-- Brand: huruf display dengan jarak huruf lebar — kesan presisi tanpa
                     harus memakai logo gambar. `uppercase` hanya di sini, bukan di
                     seluruh situs, supaya tidak terasa berteriak. --}}
                <span class="navbar-merek font-display font-extrabold uppercase leading-none flex min-w-0 items-center gap-1.5" style="--brand-fs-dasar: 24px; font-size: var(--brand-fs, var(--brand-fs-dasar)); letter-spacing: calc(var(--brand-fs, 24px) * 0.15);">
                    <span class="truncate">{{ $toko->teks('site_name', __('site.brand.fallback_name')) }}</span><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent inline-block"></span>
                </span>
            @endif
        </a>
        </div>

        {{-- Menu utama.

             Di bilah: di TENGAH (baris ini memakai `flex-1` dan
             `justify-center`, jadi menu duduk di tengah sisa ruang antara
             logo dan utilitas — bukan menempel ke kiri).

             Di sidebar: kolom vertikal, jadi markupnya dipakai ulang dengan
             kelas yang sama dan hanya arah flex yang ditukar CSS. --}}
        <nav class="navbar-menu hidden lg:flex"
             aria-label="{{ __('site.nav.menu') }}">
            @foreach ($menuUtama as $menu)
                <a href="{{ $menu['rute'] }}" wire:navigate
                   class="navbar-tautan text-xs font-semibold uppercase tracking-wider px-3.5 py-1.5 rounded-full transition-all duration-200 {{ $aktif($menu['pola']) ? 'is-aktif' : '' }}">
                    {{ $menu['label'] }}
                </a>
            @endforeach

        </nav>

        {{-- Utilitas.

             ISI MENYESUAIKAN WUJUD. Di bilah (halaman lain, dan beranda
             setelah digulir) isinya bahasa, tema, cari, keranjang — semuanya
             jalan pintas yang dibutuhkan pembeli. Di wujud sidebar kolom ini
             DIGANTI ikon media sosial, karena di sebelah kiri hero keempat
             tombol itu sudah ada di baris atas hero (bahasa, suka, cari,
             keranjang) plus tema, jadi menaruhnya lagi di sidebar membuat
             satu layar memuat dua salinan tombol yang sama.

             Penukarannya lewat CSS (.navbar-utilitas di navbar.css), bukan
             Blade: markupnya satu, dan aturan `:not(.navbar-melayang)` sudah
             menandai wujud sidebar. --}}
        <div class="navbar-utilitas flex shrink-0 items-center gap-1 sm:gap-2">
            {{-- Tombol bahasa DIHAPUS.
                 Situs kini berbahasa Inggris tunggal (APP_LOCALE=en), jadi
                 tombol yang hanya bisa berpindah ke Indonesia tidak punya
                 tujuan yang benar. Menyisakannya malah menjanjikan kemampuan
                 yang sudah tidak ada. Ini juga mengembalikan ruang bilah di
                 ponsel untuk pencarian dan keranjang. --}}

            {{-- Tombol tema. Memakai penanda data-aksi yang ditangani app.js,
                 bukan onclick: CSP proyek ini tidak memuat 'unsafe-inline'. --}}
            

            {{-- Pencarian --}}
            <button type="button" class="btn-icon" @click="cari = true"
                    title="{{ __('site.nav.search') }}">
                <x-ikon name="search" />
                <span class="visually-hidden">{{ __('site.nav.open_search') }}</span>
            </button>

            {{-- Keranjang --}}
            <a href="{{ route('front.cart') }}" class="btn-icon" title="{{ __('site.nav.cart') }}"
               wire:navigate>
                <x-ikon name="cart" />
                <livewire:front.jumlah-keranjang wire:key="badge-atas" />
                <span class="visually-hidden">{{ __('site.nav.open_cart') }}</span>
            </a>
        </div>
    </div>

</header>


    {{-- Drawer menu: tablet dan mobile --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="menu = false" x-transition.opacity></div>

        <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-sm flex-col bg-surface"
             x-transition:enter="transition-transform duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:leave="transition-transform duration-150"
             x-transition:leave-end="-translate-x-full">

            <div class="flex h-14 flex-none items-center justify-between border-b border-line-soft px-3">
                {{-- Tanpa nomor: drawer adalah panel navigasi, bukan bagian isi
                             halaman. Nomor "01" di sini bertabrakan dengan
                             nomor section beranda (01 / Katalog). --}}
                <span class="part-no">{{ __('site.nav.menu') }}</span>
                <button type="button" class="btn-icon" @click="menu = false">
                    <x-ikon name="close" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto p-3" aria-label="{{ __('site.nav.menu') }}">
                <a href="{{ route('front.home') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="home" /> {{ __('site.nav.home') }}
                </a>
                <a href="{{ route('front.products.index') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="grid" /> {{ __('site.nav.products') }}
                </a>

                <div class="measure-line my-3"></div>
                <p class="part-no mb-1 px-2">{{ __('site.nav.categories') }}</p>

                @foreach ($kategoriNav as $kat)
                    <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                       class="flex min-h-[44px] items-center gap-2 px-2 text-sm text-muted">
                        <x-ikon name="tag" :size="16" /> {{ $kat->nama() }}
                    </a>
                @endforeach

                <div class="measure-line my-3"></div>

                <a href="{{ route('front.about') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="info" /> {{ __('site.nav.about') }}
                </a>
                <a href="{{ route('front.contact') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="phone" /> {{ __('site.nav.contact') }}
                </a>
                <a href="{{ route('front.order.track') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="box" /> {{ __('site.order.track') }}
                </a>
            </nav>
        </div>
    </div>

    {{-- Overlay pencarian --}}
    <div x-show="cari" x-cloak class="fixed inset-0 z-50 bg-bg" role="dialog" aria-modal="true"
         @keydown.escape.window="cari = false">
        <div class="container-page py-4">
            <div class="flex items-center gap-2">
                <span class="part-no">{{ __('site.nav.search') }}</span>
                <button type="button" class="btn-icon ml-auto" @click="cari = false">
                    <x-ikon name="close" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            <livewire:front.pencarian />
        </div>
    </div>

{{-- Penutup pembungkus x-data.

     Pembungkus ini ada karena drawer dan panel pencarian HARUS berada di luar
     <header>. Header memakai backdrop-blur, dan backdrop-filter membuat
     containing block baru untuk position:fixed. Akibatnya drawer
     "fixed inset-0" tidak menutupi layar, melainkan terkurung di dalam kotak
     header setinggi 56px — yang terlihat sebagai lapisan transparan tanpa isi.

     Variabel Alpine (menu, cari, kategori) tetap satu lingkup karena keduanya
     berada di dalam pembungkus ini. --}}
</div>
