@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();
@endphp

<div class="beranda-home">

    {{-- Hero split: teks kiri, kartu gambar kanan, deret badge fitur.
         Struktur mengikuti acuan wireframe; isi dari data slide admin. --}}
    @include('partials.front.hero-full', ['slides' => $slides, 'toko' => $toko])


    {{-- 02 / GALERI — section mandiri, tepat di bawah about.

         Satu klik menuju etalase — jalan yang dulu harus lewat navbar lalu
         memilih filter di halaman produk.

         Ikon, bukan gambar: kategori tidak punya media sendiri, dan ikon
         membuat kartu tetap ringan serta seukuran. Angka produk dihitung satu
         kueri untuk SEMUA lewat withCount, bukan satu per kartu.

         Nomor bagian 03, bukan 02a: ia punya kepala sendiri, dan menomorinya
         sebagai sub-bagian tentang membuat hierarki judul menyimpang dari
         nomornya. --}}
    {{-- 01 / KATALOG dengan tab kategori (struktur acuan). --}}
    @include('partials.front.listing-tab', ['unggulan' => $unggulan, 'kategori' => $kategori])

    {{-- Galeri "Built with our parts" (blueprint 5E). Sumber foto: media
         produk unggulan yang sudah ada — bukan foto karangan. --}}
    @include('partials.front.community-showcase', ['unggulan' => $unggulan])

    {{-- 03 / LANGKAH PEMESANAN --}}
    @include('partials.front.langkah-pesan')

    {{-- 04 / STATISTIK + SOROTAN LOKASI --}}
    @include('partials.front.stats-wilayah', ['angka' => $angka])

    {{-- 05 / LAYANAN BENGKEL --}}
    @include('partials.front.layanan-bengkel')

    {{-- 06 / CTA "BELUM YAKIN UKURAN?" — panel terang, bukan pita karbon.
         Dulu: latar gelap + judul kiri + dua tombol setara kanan, sehingga
         terbaca seperti banner promosi. Sekarang panel berbingkai dengan
         nomor besar sebagai latar, satu pertanyaan, dan satu aksi utama;
         tautan kontak jadi tautan bergaris bawah, bukan tombol kedua. --}}
    <section class="container-page pb-14 pt-4 lg:pb-20 lg:pt-6">
        <div class="cta-ukur px-8 py-12 sm:px-12 lg:px-16 lg:py-16 rounded-3xl bg-gradient-to-r from-surface via-surface to-accent/5 border border-line-soft shadow-xl shadow-black/[0.02]">
            <span class="cta-ukur__nomor" aria-hidden="true">?</span>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-xl">
                    <span class="part-no">06 / {{ __('site.home.cta_part') }}</span>
                    <h2 class="mt-3 font-display text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-5xl">
                        {{ __('site.home.cta_title') }}
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-muted lg:text-base">
                        {{ __('site.home.cta_body') }}
                    </p>
                </div>

                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                    @if ($wa = $toko->teks('whatsapp_number'))
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}?text={{ rawurlencode($toko->teks('whatsapp_message_template')) }}"
                           target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                            <x-ikon name="whatsapp" :size="18" />
                            {{ __('site.home.cta_primary') }}
                        </a>
                    @endif

                    <a href="{{ route('front.contact') }}" class="cta-ukur__tautan">
                        {{ __('site.home.cta_secondary') }}
                        <x-ikon name="arrow-right" :size="14" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- 07 / FAQ, tepat di bawah CTA. --}}
    @include('partials.front.faq-beranda', ['faq' => $faq])

</div>
