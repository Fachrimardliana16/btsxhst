{{--
    SECTION 6 — LAYANAN (struktur acuan: tiga kartu, kartu pertama gelap).

    Isinya layanan bengkel yang memang dikerjakan toko: pasang custom,
    perawatan/repack, dan konsultasi ukuran. Kartu pertama dibuat gelap
    (seperti acuan) sebagai penanda "layanan utama".
--}}
@php
    $layanan = [
        ['nomor' => '01', 'judul' => __('site.home.layanan_1'), 'isi' => __('site.home.layanan_1_isi'), 'utama' => true],
        ['nomor' => '02', 'judul' => __('site.home.layanan_2'), 'isi' => __('site.home.layanan_2_isi'), 'utama' => false],
        ['nomor' => '03', 'judul' => __('site.home.layanan_3'), 'isi' => __('site.home.layanan_3_isi'), 'utama' => false],
    ];
@endphp

<section class="layanan-blok relative py-16 lg:py-24 bg-surface">
    <div class="container-page">
        <div class="text-center">
            <span class="part-no">05 / {{ __('site.home.layanan_judul') }}</span>
            <h2 class="section-heading mx-auto mt-3 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                {{ __('site.home.layanan_judul') }}
            </h2>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3 lg:gap-6">
            @foreach ($layanan as $l)
                <article class="layanan-kartu {{ $l['utama'] ? 'layanan-kartu--utama' : '' }}">
                    <span class="layanan-kartu__nomor">{{ $l['nomor'] }}</span>
                    <h3 class="layanan-kartu__judul">{{ $l['judul'] }}</h3>
                    <p class="layanan-kartu__isi">{{ $l['isi'] }}</p>

                    <a href="{{ route('front.contact') }}" class="layanan-kartu__tautan">
                        {{ __('site.nav.contact') }}
                        <x-ikon name="arrow-right" :size="14" />
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
