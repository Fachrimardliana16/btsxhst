{{--
    HERO BITESExhaust — struktur acuan "split hero" (Corazon), isi knalpot.

    Susunan:
      kiri   eyebrow · judul · subjudul · dua tombol · deret badge fitur
      kanan  kartu gambar besar bersudut membulat (slide aktif dari admin)

    Deret badge di bawah hero adalah bagian dari struktur acuan; isinya
    diambil dari KARAKTERISTIK PRODUK yang benar ada di toko, bukan karangan:
    titanium/stainless, garansi las, dan pengiriman. Bila stok habis, badge
    stok berubah sendiri.

    Judul, subjudul, dan tombol tetap dari slide admin ($slides) — hero tidak
    mematok teks di template.
--}}
@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();
    $jumlahSlide = $slides->count();
    $slideAktif = $slides->first();

    $urlAktif = $slideAktif ? \App\Models\ProductMedia::urlMedia($slideAktif->media_path) : null;
    $urlPonselAktif = $slideAktif && filled($slideAktif->media_path_mobile)
        ? \App\Models\ProductMedia::urlMedia($slideAktif->media_path_mobile)
        : null;
@endphp

<section class="hero-split relative overflow-hidden bg-gradient-to-b from-surface-sunken/40 via-bg to-bg"
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

    <div class="container-page">
        <div class="grid items-center gap-8 py-10 lg:grid-cols-2 lg:gap-14 lg:py-16">

            {{-- ══ Kolom kiri: teks ══ --}}
            <div class="order-2 lg:order-1">
                <p class="hero-split__eyebrow">
                    {{ $toko->teks('hero_eyebrow', __('site.home.hero_eyebrow')) }}
                </p>

                @if ($slides->isEmpty())
                    <h1 class="hero-split__judul">
                        {{ $toko->teks('site_tagline', __('site.brand.tagline')) }}
                    </h1>
                    <p class="hero-split__sub">{{ $toko->teks('site_description') }}</p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ route('front.products.index') }}" class="btn btn-primary hero-split__tombol">
                            {{ __('site.home.hero_cta_catalog') }}
                            <x-ikon name="arrow-right" :size="16" />
                        </a>
                    </div>
                @else
                    {{-- Judul & subjudul per slide, ditumpuk supaya tinggi blok
                         tidak melompat saat slide berganti. --}}
                    @foreach ($slides as $idx => $slide)
                        <div x-show="i === {{ $idx }}" x-cloak
                             @if ($idx === 0) style="display: block" @endif>
                            <h1 class="hero-split__judul">{{ $slide->judul() }}</h1>
                            <p class="hero-split__sub">
                                {{ filled($slide->subjudul()) ? $slide->subjudul() : $toko->teks('site_description') }}
                            </p>
                        </div>
                    @endforeach

                    @foreach ($slides as $idx => $slide)
                        @php
                            $hrefUtama = $slide->hrefTombolUtama();
                            $hrefKedua = $slide->hrefTombolKedua();
                        @endphp
                        <div class="mt-7 flex flex-wrap gap-3"
                             x-show="i === {{ $idx }}" x-cloak
                             @if ($idx === 0) style="display: flex" @endif>
                            @if ($hrefUtama !== null && filled($slide->labelTombolUtama()))
                                <a href="{{ $hrefUtama }}" class="btn btn-primary hero-split__tombol">
                                    {{ $slide->labelTombolUtama() }}
                                    <x-ikon name="arrow-right" :size="16" />
                                </a>
                            @endif

                            @if ($hrefKedua !== null && filled($slide->labelTombolKedua()))
                                <a href="{{ $hrefKedua }}" class="btn btn-outline hero-split__tombol">
                                    {{ $slide->labelTombolKedua() }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                @endif

                {{-- Deret badge fitur (struktur acuan: 3 pil di bawah hero).
                     Isinya fakta produk, bukan slogan. --}}
                <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-surface border border-line-soft/80 shadow-sm transition-all hover:border-accent/40 hover:-translate-y-0.5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <x-ikon name="shield" :size="20" />
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-ink truncate">{{ __('site.home.fitur_1_judul') }}</h4>
                            <p class="text-[length:var(--t-xs)] text-muted truncate mt-0.5">{{ __('site.home.fitur_1_sub') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-surface border border-line-soft/80 shadow-sm transition-all hover:border-accent/40 hover:-translate-y-0.5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <x-ikon name="check" :size="20" />
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-ink truncate">{{ __('site.home.fitur_2_judul') }}</h4>
                            <p class="text-[length:var(--t-xs)] text-muted truncate mt-0.5">{{ __('site.home.fitur_2_sub') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-surface border border-line-soft/80 shadow-sm transition-all hover:border-accent/40 hover:-translate-y-0.5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <x-ikon name="truck" :size="20" />
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-ink truncate">{{ __('site.home.fitur_3_judul') }}</h4>
                            <p class="text-[length:var(--t-xs)] text-muted truncate mt-0.5">{{ __('site.home.fitur_3_sub') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ Kolom kanan: kartu gambar ══
                 Gambar slide aktif di dalam kartu bersudut membulat, dengan
                 kartu kecil melayang berisi status stok/kendali slide. --}}
            <div class="order-1 lg:order-2">
                <div class="hero-split__bingkai">
                    @forelse ($slides as $idx => $slide)
                        @php
                            $urlSlide = \App\Models\ProductMedia::urlMedia($slide->media_path);
                            $urlPonsel = filled($slide->media_path_mobile)
                                ? \App\Models\ProductMedia::urlMedia($slide->media_path_mobile)
                                : null;
                        @endphp

                        <div class="hero-split__gambar" data-aktif="{{ $idx === 0 ? 'true' : 'false' }}"
                             :data-aktif="i === {{ $idx }} ? 'true' : 'false'">
                            <picture class="block h-full w-full">
                                @if ($urlPonsel)
                                    <source media="(max-width: 639px)" srcset="{{ $urlPonsel }}" />
                                @endif
                                <img src="{{ $urlSlide }}" alt="{{ $slide->judul() }}"
                                     style="object-position: {{ $slide->posisiFokus() }}"
                                     width="1200" height="900"
                                     class="block h-full w-full object-cover"
                                     @if ($idx === 0) fetchpriority="high" @else loading="lazy" @endif
                                     decoding="async" />
                            </picture>
                        </div>
                    @empty
                        <div class="hero-split__kosong" aria-hidden="true">
                            <svg viewBox="0 0 400 160" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 120h150l40-40h120l20 20h30" />
                                <circle cx="120" cy="120" r="18" />
                                <circle cx="300" cy="100" r="12" />
                            </svg>
                        </div>
                    @endforelse

                    {{-- Kartu melayang kendali slide: hanya bila ada >1 slide. --}}
                    @if ($jumlahSlide > 1)
                        <div class="hero-split__kendali">
                            <span class="hero-split__hitung" aria-hidden="true">
                                <b x-text="String(i + 1).padStart(2, '0')">01</b>
                                <span>/ {{ str_pad((string) $jumlahSlide, 2, '0', STR_PAD_LEFT) }}</span>
                            </span>

                            <span class="hero-split__geser flex items-center gap-1">
                                @foreach ($slides as $idx => $slide)
                                    <button type="button" @click="i = {{ $idx }}; berhenti()"
                                            :aria-current="i === {{ $idx }} ? 'true' : 'false'"
                                            aria-label="{{ __('site.home.hero_scroll') }} {{ $idx + 1 }}">
                                        <span></span>
                                    </button>
                                @endforeach
                            </span>

                            <button type="button" class="hero-split__jeda"
                                    @click="jeda = !jeda; jeda ? berhenti() : mulai()"
                                    :aria-label="jeda ? 'Jalankan slide' : 'Jeda slide'">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <g x-show="!jeda"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></g>
                                    <path x-show="jeda" x-cloak d="M7 4l13 8-13 8z"/>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
