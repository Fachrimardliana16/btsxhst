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
         dari dua blok terang di atasnya.

         Latar TIDAK ditulis dengan utility Tailwind. Sebelumnya kelas
         `bg-gradient-to-b from-surface to-surface-sunken/60` menimpa
         `.galeri-blok` yang berisi latar navy (#0a2540) — utility menang atas
         komponen CSS. Akibatnya blok ini berlatar TERANG sementara CSS-nya
         tetap mengecat judul putih (`.galeri-blok h2 { color:#fff }`), jadi
         judul dan subjudul praktis tidak terbaca. Warna gelapnya sekarang
         datang dari .galeri-blok saja. --}}
    <section class="galeri-blok relative py-16 lg:py-24" data-reveal>
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

        {{-- Grid dengan hierarki: foto pertama memimpin, sisanya mengisi.
             Versi sebelumnya satu grid seragam tiga kolom — rapi, tetapi
             kelima foto berbobot sama sehingga mata tidak punya tempat
             berhenti, dan blok setinggi ini terbaca sebagai daftar thumbnail.

             Yang ditambahkan BUKAN kembali ke "editorial grid" lama (lima
             ukuran berbeda, tiap kartu bertransformasi dan berbayangan
             sendiri). Hierarkinya hanya dua tingkat: satu foto besar, empat
             pendamping. Sisanya tetap diam sampai kursor menyentuhnya. --}}
        <div class="galeri-bersih mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 lg:gap-5">
            @foreach ($tampil as $i => $produk)
                @php
                    $foto = $produk->media->first();
                    $judul = $produk->judul();
                    $kategori = $produk->category?->nama();
                    // Foto pertama memimpin dua kolom di layar lebar.
                    $utama = $i === 0;
                @endphp

                <a href="{{ route('front.products.show', $produk) }}" wire:navigate
                   class="galeri-bersih__item group relative block overflow-hidden rounded-[var(--r-card)] border border-white/10 bg-white/5
                          {{ $utama ? 'col-span-2 lg:row-span-2' : '' }}"
                   data-reveal style="transition-delay: {{ $i * 40 }}ms">
                    <div class="{{ $utama ? 'aspect-square lg:aspect-auto lg:h-full' : 'aspect-[4/3]' }} overflow-hidden">
                        <img src="{{ $foto->url('card') }}"
                             srcset="{{ $foto->url('thumb') }} 320w, {{ $foto->url('card') }} 640w"
                             sizes="{{ $utama ? '(min-width: 1024px) 50vw, 95vw' : '(min-width: 1024px) 25vw, 45vw' }}"
                             width="{{ $foto->width ?: 640 }}" height="{{ $foto->height ?: 480 }}"
                             alt="{{ $foto->alt() ?: $judul }}"
                             loading="lazy" decoding="async"
                             class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]">
                    </div>

                    {{-- Keterangan MENIMPA bagian bawah foto, bukan baris
                         terpisah di bawahnya. Alasannya bukan gaya: kartu
                         gelap tanpa keterangan menyisakan ruang kosong yang
                         membuat grid terlihat renggang, sementara keterangan
                         di bawah foto menambah tinggi total dan memutus
                         gambar dari namanya. Gradiennya hanya selebar tinggi
                         teks, cukup untuk kontras, tidak menutupi produk. --}}
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-3 pt-10">
                        @if ($kategori)
                            <p class="galeri-kategori">{{ $kategori }}</p>
                        @endif
                        <span class="flex items-center justify-between gap-2">
                            <span class="line-clamp-1 {{ $utama ? 'text-sm font-semibold lg:text-base' : 'text-xs font-medium' }} text-white">
                                {{ $judul }}
                            </span>
                            <x-ikon name="arrow-right" :size="14"
                                    class="flex-none text-white/70 transition-transform duration-300 group-hover:translate-x-1 group-hover:text-white" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        </div>
    </section>
@endif
