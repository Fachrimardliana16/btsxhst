{{--
    HERO BERANDA — memakai DATA SLIDE dari admin, bukan teks tetap di template.

    Sebelumnya headline diambil dari kunci terjemahan (`hero_headline`), jadi
    judul yang ditulis admin di panel Hero TIDAK pernah muncul — yang tampil
    selalu "Knalpot presisi, diukur sebelum dipotong". Sekarang:

      judul      → $slide->judul()
      subjudul   → $slide->subjudul()
      tombol 1   → labelTombolUtama() + hrefTombolUtama()
      tombol 2   → labelTombolKedua() + hrefTombolKedua() (bila diisi admin)

    Bila toko belum punya slide, tagline dari Pengaturan dipakai sebagai judul
    tunggal supaya hero tidak kosong.

    Tombol kedua TIDAK lagi diisi otomatis "Pilih kendaraan": itu mengarah ke
    katalog yang sama dengan tombol pertama dan hanya menambah dua tombol yang
    melakukan hal sama.
--}}
@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();
    $jumlahSlide = $slides->count();
@endphp

<section class="hero-race relative isolate overflow-hidden"
         x-data="{
            i: 0,
            n: {{ max(1, $jumlahSlide) }},
            jeda: false,
            timer: null,
            bolehGerak: function () {
                return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            },
            mulai: function () {
                clearInterval(this.timer);
                if (this.n < 2 || this.jeda || !this.bolehGerak()) return;
                this.timer = setInterval(function () { this.i = (this.i + 1) % this.n; }.bind(this), 7000);
            },
            berhenti: function () { clearInterval(this.timer); this.timer = null; }
         }"
         x-init="mulai()"
         x-on:livewire:navigated.window="i = 0; jeda = false; berhenti(); mulai()">

    {{-- ══ Latar: gambar slide ══ --}}
    <div class="absolute inset-0 -z-10">
        @forelse ($slides as $idx => $slide)
            @php
                $urlSlide = \App\Models\ProductMedia::urlMedia($slide->media_path);
                $urlPonsel = filled($slide->media_path_mobile)
                    ? \App\Models\ProductMedia::urlMedia($slide->media_path_mobile)
                    : null;
            @endphp

            <div class="hero-race__slide absolute inset-0" data-aktif="{{ $idx === 0 ? 'true' : 'false' }}"
                 :data-aktif="i === {{ $idx }} ? 'true' : 'false'">
                <picture class="block h-full w-full">
                    @if ($urlPonsel)
                        <source media="(max-width: 639px)" srcset="{{ $urlPonsel }}" />
                    @endif
                    <img src="{{ $urlSlide }}" alt="{{ $slide->judul() }}"
                         style="object-position: {{ $slide->posisiFokus() }}"
                         width="1600" height="900"
                         class="block h-full w-full object-cover"
                         @if ($idx === 0) fetchpriority="high" @else loading="lazy" @endif
                         decoding="async" />
                </picture>
            </div>
        @empty
            <div class="hero-race__kosong" aria-hidden="true">
                <svg viewBox="0 0 400 160" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 120h150l40-40h120l20 20h30" />
                    <circle cx="120" cy="120" r="18" />
                    <circle cx="300" cy="100" r="12" />
                </svg>
            </div>
        @endforelse

        <div class="hero-race__selubung absolute inset-0" aria-hidden="true"></div>
    </div>

    <div class="hero-race__isi relative flex h-full flex-col justify-between
                px-5 py-8 sm:px-8 md:px-10 md:py-10 lg:px-12 lg:py-12">

        {{-- Baris atas: tombol utilitas ringan, rata kanan. --}}
        <div class="flex flex-none items-center justify-end gap-2">
            {{-- Tombol bahasa dihapus: situs berbahasa Inggris tunggal, jadi
                 pil "EN/ID" tidak punya tujuan lagi. Tombol tema tetap. --}}

            <button type="button" class="hero-race__pill" data-aksi="ganti-tema"
                    title="{{ __('site.nav.theme') }}" aria-label="{{ __('site.nav.theme') }}">
                <span data-tema-ikon="gelap" class="hidden"><x-ikon name="sun" :size="16" /></span>
                <span data-tema-ikon="terang" class="block"><x-ikon name="moon" :size="16" /></span>
            </button>

            <button type="button" class="hero-race__pill" data-aksi="buka-cari"
                    title="{{ __('site.nav.search') }}" aria-label="{{ __('site.nav.search') }}">
                <x-ikon name="search" :size="16" />
            </button>

            <a href="{{ route('front.cart') }}" wire:navigate
               class="hero-race__pill hero-race__pill--badge"
               title="{{ __('site.nav.cart') }}" aria-label="{{ __('site.nav.cart') }}">
                <x-ikon name="cart" :size="16" />
                <span data-hero-keranjang aria-hidden="true">0</span>
            </a>

            <span class="hidden" data-jumlah-keranjang data-jumlah-suka="0" aria-hidden="true">
                <livewire:front.jumlah-keranjang wire:key="badge-hero" />
            </span>
        </div>

        {{-- Tengah: judul & subjudul DARI SLIDE, ditumpuk agar tidak melompat
             saat berganti slide dengan panjang teks berbeda. --}}
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 text-center md:gap-5">
            @if ($slides->isEmpty())
                <h1 class="hero-race__judul">{{ $toko->teks('site_tagline', __('site.brand.tagline')) }}</h1>
                <p class="hero-race__sub">{{ $toko->teks('site_description') }}</p>

                <div class="hero-race__cta">
                    <a href="{{ route('front.products.index') }}" class="btn btn-primary hero-race__tombol-utama">
                        {{ __('site.home.hero_cta_catalog') }}
                        <x-ikon name="arrow-right" :size="16" />
                    </a>
                </div>
            @else
                @foreach ($slides as $idx => $slide)
                    <div x-show="i === {{ $idx }}" x-cloak class="contents"
                         @if ($idx === 0) style="display: contents" @endif>
                        <h1 class="hero-race__judul">{{ $slide->judul() }}</h1>

                        @if (filled($slide->subjudul()))
                            <p class="hero-race__sub">{{ $slide->subjudul() }}</p>
                        @else
                            <p class="hero-race__sub">{{ $toko->teks('site_description') }}</p>
                        @endif
                    </div>
                @endforeach

                {{-- Tombol: per slide, mengikuti tautan yang diisi admin. --}}
                @foreach ($slides as $idx => $slide)
                    @php
                        $hrefUtama = $slide->hrefTombolUtama();
                        $hrefKedua = $slide->hrefTombolKedua();
                    @endphp

                    <div class="hero-race__cta flex flex-wrap items-center justify-center gap-3"
                         x-show="i === {{ $idx }}" x-cloak
                         @if ($idx === 0) style="display: flex" @endif>
                        @if ($hrefUtama !== null && filled($slide->labelTombolUtama()))
                            <a href="{{ $hrefUtama }}" class="btn btn-primary hero-race__tombol-utama">
                                {{ $slide->labelTombolUtama() }}
                                <x-ikon name="arrow-right" :size="16" />
                            </a>
                        @endif

                        @if ($hrefKedua !== null && filled($slide->labelTombolKedua()))
                            <a href="{{ $hrefKedua }}" class="hero-race__tombol-garis">
                                {{ $slide->labelTombolKedua() }}
                            </a>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Kaki: kendali slide, tanpa garis. --}}
        <div class="hero-race__kaki flex flex-none flex-col items-center gap-3">
            @if ($jumlahSlide > 1)
                <div class="hero-race__dasar flex flex-wrap items-center justify-center gap-3">
                    <div class="hero-race__hitung flex items-baseline gap-1" aria-hidden="true">
                        <b x-text="String(i + 1).padStart(2, '0')">01</b>
                        <span>/ {{ str_pad((string) $jumlahSlide, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>

                    <div class="hero-race__geser flex items-center gap-1">
                        @foreach ($slides as $idx => $slide)
                            <button type="button" @click="i = {{ $idx }}; berhenti()"
                                    :aria-current="i === {{ $idx }} ? 'true' : 'false'"
                                    aria-label="{{ __('site.home.hero_scroll') }} {{ $idx + 1 }}">
                                <span></span>
                            </button>
                        @endforeach
                    </div>

                    <button type="button" class="hero-race__pill"
                            @click="jeda = !jeda; jeda ? berhenti() : mulai()"
                            :aria-label="jeda ? 'Jalankan slide' : 'Jeda slide'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <g x-show="!jeda"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></g>
                            <path x-show="jeda" x-cloak d="M7 4l13 8-13 8z"/>
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    </div>
</section>
