{{--
    SECTION 2 — LISTING dengan TAB FILTER (struktur acuan Corazon).

    Tab = kategori yang benar-benar ada di basis data, bukan "Apartment /
    Office / Warehouse" dari acuan. Tab pertama aktif secara default; bila
    jumlah kategori lebih dari lima, barisnya bisa digeser mendatar di
    ponsel (overflow-x-auto) alih-alih membungkus jadi dua baris.

    Produk yang ditampilkan: unggulan. Tidak ada tab yang memuat kueri
    tambahan — tab hanyalah tautan ke halaman kategori, jadi halaman ini tetap
    satu entri cache dan tidak menambah beban.
--}}
@php
    /** @var \Illuminate\Support\Collection $unggulan */
    $unggulan = $unggulan ?? collect();
    $kategoriTab = $kategori ?? collect();
@endphp

<section class="listing-blok relative py-16 lg:py-24 bg-surface" id="listing">
    <div class="container-page">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">01 / {{ __('site.home.products_part') }}</span>
                <h2 class="section-heading mt-3 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                    {{ __('site.home.listing_judul') }}
                </h2>
                <p class="mt-3 max-w-2xl text-base text-muted">{{ __('site.home.listing_sub') }}</p>
            </div>

            <a href="{{ route('front.products.index') }}" class="listing-blok__semua">
                {{ __('site.home.view_all_products') }}
                <x-ikon name="arrow-right" :size="16" />
            </a>
        </div>

        {{-- Tab kategori --}}
        @if ($kategoriTab->isNotEmpty())
            <div class="mt-8 flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none" role="tablist" aria-label="{{ __('site.nav.categories') }}">
                <a href="{{ route('front.products.index') }}" 
                   class="inline-flex shrink-0 items-center justify-center rounded-full px-5 py-2 text-xs font-bold uppercase tracking-wider transition-all duration-200 bg-accent text-white shadow-md shadow-accent/20" 
                   role="tab">
                    {{ __('site.common.all') }}
                </a>

                @foreach ($kategoriTab as $kat)
                    <a href="{{ route('front.category', $kat) }}" wire:navigate 
                       class="inline-flex shrink-0 items-center justify-center rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-wider transition-all duration-200 bg-surface border border-line-soft text-muted hover:border-accent hover:text-accent hover:bg-surface-sunken" 
                       role="tab">
                        {{ $kat->nama() }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Grid kartu produk --}}
        @if ($unggulan->isEmpty())
            <div class="plate mt-8 p-8 text-center">
                <p class="font-semibold">{{ __('site.product.empty') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('site.product.empty_hint') }}</p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                @foreach ($unggulan->take(6) as $produk)
                    <div data-reveal style="transition-delay: {{ $loop->index * 55 }}ms">
                        <x-kartu-produk :produk="$produk" />
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                <a href="{{ route('front.products.index') }}" class="btn btn-primary">
                    {{ __('site.home.view_all_products') }}
                    <x-ikon name="arrow-right" :size="16" />
                </a>
            </div>
        @endif
    </div>
</section>
