{{--
    HERO BITESExhaust — 1 Gambar Full Screen (Viewport) dengan Teks di Dalam Gambar.

    Fitur:
      1. Viewport 100vh / 100svh penuh.
      2. Gambar latar slide mengisi seluruh layar (full cover).
      3. Teks headline, subjudul, dan CTA berada di tengah gambar dengan overlay gelap & cahaya aksen.
      4. Tombol navigasi slide di sisi KANAN dan KIRI gambar (chevron left & chevron right).
      5. Indikator slide kecil di bagian bawah.
--}}
@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();
    $jumlahSlide = $slides->count();
@endphp

<section class="hero-full relative isolate flex h-[100svh] min-h-[580px] w-full flex-col justify-between overflow-hidden text-white"
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
            berhenti: function () { clearInterval(this.timer); this.timer = null; },
            sebelumnya: function () {
                this.i = (this.i - 1 + this.n) % this.n;
                this.berhenti();
            },
            berikutnya: function () {
                this.i = (this.i + 1) % this.n;
                this.berhenti();
            }
         }"
         x-init="mulai()"
         x-on:livewire:navigated.window="i = 0; jeda = false; berhenti(); mulai()">

    {{-- ══ Latar Gambar Full Screen ══ --}}
    <div class="absolute inset-0 -z-10 bg-[#08121b]">
        @forelse ($slides as $idx => $slide)
            @php
                $urlSlide = \App\Models\ProductMedia::urlMedia($slide->media_path);
                $urlPonsel = filled($slide->media_path_mobile)
                    ? \App\Models\ProductMedia::urlMedia($slide->media_path_mobile)
                    : null;
            @endphp

            <div class="absolute inset-0 transition-opacity duration-1000 ease-out"
                 x-show="i === {{ $idx }}"
                 x-transition:enter="transition-opacity duration-1000 ease-out"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-700 ease-in absolute inset-0"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @if ($idx === 0) style="opacity: 1;" @endif>
                <picture class="block h-full w-full">
                    @if ($urlPonsel)
                        <source media="(max-width: 639px)" srcset="{{ $urlPonsel }}" />
                    @endif
                    <img src="{{ $urlSlide }}" alt="{{ $slide->judul() }}"
                         style="object-position: {{ $slide->posisiFokus() }}"
                         width="1920" height="1080"
                         class="h-full w-full object-cover"
                         @if ($idx === 0) fetchpriority="high" @else loading="lazy" @endif
                         decoding="async" />
                </picture>
            </div>
        @empty
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#0a2540] to-[#041220]">
                <span class="font-display text-4xl font-extrabold uppercase tracking-widest text-white/30">BITESExhaust</span>
            </div>
        @endforelse

        {{-- Overlay gradient dramatis: gelap di atas (agar navbar terbaca), gelap di bawah, aksen biru/kuning lembut --}}
        <div class="absolute inset-0 bg-gradient-to-t from-[#08121b] via-black/45 to-black/75"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-transparent to-black/60"></div>
    </div>

    {{-- Spacer atas untuk memberi ruang navbar transparan --}}
    <div class="h-16 w-full shrink-0 lg:h-20"></div>

    {{-- ══ Tombol Navigasi Kiri & Kanan (Slide Controls) ══ --}}
    @if ($jumlahSlide > 1)
        {{-- Tombol Kiri --}}
        <button type="button"
                @click="sebelumnya()"
                class="group absolute left-3 top-1/2 z-20 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-black/35 text-white backdrop-blur-md transition-all duration-200 hover:scale-110 hover:border-promo hover:bg-black/60 hover:text-promo md:left-6 md:h-14 md:w-14"
                aria-label="Slide sebelumnya">
            <x-ikon name="chevron-left" :size="24" class="transition-transform group-hover:-translate-x-0.5" />
        </button>

        {{-- Tombol Kanan --}}
        <button type="button"
                @click="berikutnya()"
                class="group absolute right-3 top-1/2 z-20 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-black/35 text-white backdrop-blur-md transition-all duration-200 hover:scale-110 hover:border-promo hover:bg-black/60 hover:text-promo md:right-6 md:h-14 md:w-14"
                aria-label="Slide berikutnya">
            <x-ikon name="chevron-right" :size="24" class="transition-transform group-hover:translate-x-0.5" />
        </button>
    @endif

    {{-- ══ Teks & Konten di Dalam Gambar (Center Overlay) ══ --}}
    <div class="container-page relative z-10 my-auto flex max-w-4xl flex-col items-center px-6 text-center">
        @if ($slides->isEmpty())
            <span class="inline-flex items-center gap-2 rounded-full border border-promo/40 bg-promo/15 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-promo backdrop-blur-sm">
                High Performance Exhaust
            </span>

            <h1 class="mt-5 font-display text-4xl font-extrabold uppercase leading-[1.05] tracking-tight text-white drop-shadow-lg sm:text-6xl lg:text-7xl">
                {{ $toko->teks('site_tagline', __('site.brand.tagline')) }}
            </h1>

            <p class="mt-5 max-w-2xl text-base text-white/85 drop-shadow sm:text-lg lg:text-xl">
                {{ $toko->teks('site_description') }}
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('front.products.index') }}" class="btn btn-primary btn-lg min-h-[48px] px-8 text-sm font-bold uppercase tracking-wider shadow-lg shadow-accent/40">
                    {{ __('site.home.view_all_products') }}
                    <x-ikon name="arrow-right" :size="16" />
                </a>
            </div>
        @else
            @foreach ($slides as $idx => $slide)
                <div x-show="i === {{ $idx }}"
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="flex flex-col items-center"
                     @if ($idx === 0) style="display: flex;" @endif>

                    <span class="inline-flex items-center gap-2 rounded-full border border-promo/40 bg-promo/15 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-promo backdrop-blur-sm">
                        <span class="h-1.5 w-1.5 rounded-full bg-promo animate-pulse"></span>
                        {{ $toko->teks('site_name', 'BITESExhaust') }} Racing Spec
                    </span>

                    <h1 class="mt-5 font-display text-4xl font-extrabold uppercase leading-[1.05] tracking-tight text-white drop-shadow-xl sm:text-6xl lg:text-7xl">
                        {{ $slide->judul() }}
                    </h1>

                    @if (filled($slide->subjudul()))
                        <p class="mt-5 max-w-2xl text-base text-white/85 drop-shadow sm:text-lg lg:text-xl">
                            {{ $slide->subjudul() }}
                        </p>
                    @else
                        <p class="mt-5 max-w-2xl text-base text-white/85 drop-shadow sm:text-lg lg:text-xl">
                            {{ $toko->teks('site_description') }}
                        </p>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        @php
                            $hrefUtama = $slide->hrefTombolUtama();
                            $hrefKedua = $slide->hrefTombolKedua();
                        @endphp

                        @if ($hrefUtama !== null && filled($slide->labelTombolUtama()))
                            <a href="{{ $hrefUtama }}" class="btn btn-primary btn-lg min-h-[48px] px-8 text-sm font-bold uppercase tracking-wider shadow-lg shadow-accent/40">
                                {{ $slide->labelTombolUtama() }}
                                <x-ikon name="arrow-right" :size="16" />
                            </a>
                        @else
                            <a href="{{ route('front.products.index') }}" class="btn btn-primary btn-lg min-h-[48px] px-8 text-sm font-bold uppercase tracking-wider shadow-lg shadow-accent/40">
                                {{ __('site.home.view_all_products') }}
                                <x-ikon name="arrow-right" :size="16" />
                            </a>
                        @endif

                        @if ($hrefKedua !== null && filled($slide->labelTombolKedua()))
                            <a href="{{ $hrefKedua }}" class="inline-flex min-h-[48px] items-center justify-center rounded-full border-2 border-white/60 bg-black/20 px-8 text-sm font-bold uppercase tracking-wider text-white backdrop-blur-sm transition-all hover:border-white hover:bg-white hover:text-black">
                                {{ $slide->labelTombolKedua() }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- ══ Kaki Hero: Indikator Dots & Pause Control ══ --}}
    <div class="relative z-10 flex w-full flex-col items-center pb-6 sm:pb-8">
        @if ($jumlahSlide > 1)
            <div class="flex items-center gap-2 rounded-full border border-white/15 bg-black/35 px-4 py-2 backdrop-blur-md">
                <span class="font-mono text-xs font-semibold text-white/70">
                    <span x-text="String(i + 1).padStart(2, '0')">01</span> / {{ str_pad((string) $jumlahSlide, 2, '0', STR_PAD_LEFT) }}
                </span>

                <div class="mx-1 h-3 w-px bg-white/20"></div>

                <div class="flex items-center gap-1.5">
                    @foreach ($slides as $idx => $slide)
                        {{-- Sasaran sentuh: pil ini tingginya 8px (h-2), gagal audit
                             target-size — ambangnya 24x24 px plus jarak aman 24 px
                             ke tetangga. Kini tombolnya kotak 24x24 transparan dan
                             PIL-nya pindah ke <span> di dalam, jadi tampilan tetap
                             sama persis tetapi area kliknya memenuhi ambang. --}}
                        <button type="button"
                                @click="i = {{ $idx }}; berhenti()"
                                class="flex h-6 w-6 items-center justify-center rounded-full"
                                aria-label="Pilih slide {{ $idx + 1 }}">
                            <span class="block h-2 rounded-full transition-all duration-300"
                                  :class="i === {{ $idx }} ? 'w-6 bg-promo' : 'w-2 bg-white/40 group-hover:bg-white/70'"></span>
                        </button>
                    @endforeach
                </div>

                <button type="button"
                        @click="jeda = !jeda; jeda ? berhenti() : mulai()"
                        class="ml-1 flex h-6 w-6 items-center justify-center text-white/60 hover:text-white"
                        aria-label="Jeda atau putar slide otomatis"
                        :title="jeda ? 'Putar slide' : 'Jeda slide'">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <g x-show="!jeda"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></g>
                        <path x-show="jeda" x-cloak d="M7 4l13 8-13 8z"/>
                    </svg>
                </button>
            </div>
        @endif
    </div>
</section>
