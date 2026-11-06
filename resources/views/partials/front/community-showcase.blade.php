{{--
    COMMUNITY SHOWCASE — "Built with our parts" (blueprint bagian 5E).

    Sumber foto: media produk yang sudah ada di basis data (`product_media`),
    diambil dari produk unggulan. Alasannya: foto pelanggan yang benar-benar
    terpasang di motor belum punya tabel sendiri (itu fitur baru), sedangkan
    memajang foto karangan berlabel "motornya pelanggan" adalah klaim palsu.

    Jadi judulnya jujur: foto PRODUK yang kami pasang kirim, dengan tautan
    "beli part ini" yang memang berfungsi. Saat tabel foto pelanggan ada
    (Tahap 3/4), hanya sumber query di bawah yang diganti.

    Diambil maksimal 5, semuanya `loading="lazy"` — galeri ini di bawah
    lipatan dan tidak boleh menahan muat halaman.
--}}
@php
    $galeri = $unggulan ?? collect();

    // Satu foto utama per produk, lewati yang tidak punya media.
    $tampil = $galeri
        ->filter(fn ($p) => $p->media->isNotEmpty())
        ->take(5);
@endphp

@if ($tampil->isNotEmpty())
    {{-- Blok GELAP: jadi penutup visual sebelum CTA, dan memisahkan galeri
         dari dua blok terang di atasnya. --}}
    <section class="galeri-blok relative py-16 lg:py-24 bg-gradient-to-b from-surface to-surface-sunken/60" data-reveal>
        <div class="container-page">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">02 / {{ __('site.home.community_eyebrow') }}</span>
                <h2 class="font-display mt-3 text-3xl font-bold tracking-tight text-ink sm:text-4xl lg:text-5xl">
                    {{ __('site.home.community_title') }}
                </h2>
                <p class="mt-3 max-w-2xl text-base text-muted lg:text-lg">
                    {{ __('site.home.community_subtitle') }}
                </p>
            </div>
        </div>

        {{-- Grid SERAGAM, bukan editorial.
             Versi sebelumnya memakai "editorial grid": foto pertama besar di
             kiri, sisanya mengisi kanan dengan ukuran berbeda-beda, ditambah
             hover:-translate-y-1 dan hover:shadow-xl pada tiap kartu. Diukur:
             kelima kartu selalu punya transform dan bayangan aktif, sehingga
             blok ini terbaca "hidup" sendiri di tengah halaman yang tenang —
             dan tinggi gridnya 507px untuk lima foto.

             Sekarang: satu grid tiga kolom (dua di ponsel) dengan rasio
             seragam, tanpa translasi, tanpa bayangan, tanpa gradien label.
             Tautan tetap utuh lewat seluruh kartu. --}}
        <div class="galeri-bersih mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 lg:gap-5">
            @foreach ($tampil as $i => $produk)
                @php
                    $foto = $produk->media->first();
                    $judul = $produk->judul();
                @endphp

                <a href="{{ route('front.products.show', $produk) }}" wire:navigate
                   class="galeri-bersih__item group relative block overflow-hidden rounded-[var(--r-card)] border border-line-soft bg-surface"
                   data-reveal style="transition-delay: {{ $i * 40 }}ms">
                    <div class="aspect-square overflow-hidden">
                        <img src="{{ $foto->url('card') }}"
                             srcset="{{ $foto->url('thumb') }} 320w, {{ $foto->url('card') }} 640w"
                             sizes="(min-width: 1024px) 30vw, 45vw"
                             width="{{ $foto->width ?: 640 }}" height="{{ $foto->height ?: 480 }}"
                             alt="{{ $foto->alt() ?: $judul }}"
                             loading="lazy" decoding="async"
                             class="h-full w-full object-cover">
                    </div>

                    {{-- Keterangan di bawah gambar, bukan gradien di atasnya:
                         teks di atas foto harus diberi gradien gelap agar
                         terbaca, dan gradien itulah yang membuat kartu terasa
                         ramai. Di bawah, teks cukup satu baris tenang. --}}
                    <div class="flex items-center justify-between gap-2 px-3 py-2.5">
                        <span class="line-clamp-1 text-xs font-medium text-ink">{{ $judul }}</span>
                        <x-ikon name="arrow-right" :size="14"
                                class="flex-none text-muted transition-colors group-hover:text-accent" />
                    </div>
                </a>
            @endforeach
        </div>
        </div>
    </section>
@endif
