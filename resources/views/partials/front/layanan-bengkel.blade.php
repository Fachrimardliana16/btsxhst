{{--
    SECTION 6 — LAYANAN (struktur acuan: tiga kartu, kartu pertama gelap).

    Isinya layanan bengkel yang memang dikerjakan toko: pasang custom,
    perawatan/repack, dan konsultasi ukuran. Kartu pertama dibuat gelap
    (seperti acuan) sebagai penanda "layanan utama".
--}}
@php
    // Isi kartu datang dari Pengaturan → Konten Halaman. Kartu yang judulnya
    // masih kosong tidak ditampilkan; bila ketiganya kosong, seluruh section
    // hilang dari beranda.
    $layanan = collect(range(1, 3))
        ->map(fn (int $i): array => [
            'nomor' => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'judul' => $toko->teks('layanan_'.$i.'_judul'),
            'isi' => $toko->teks('layanan_'.$i.'_isi'),
            'utama' => $i === 1,
        ])
        ->filter(fn (array $l): bool => filled($l['judul']) || filled($l['isi']))
        ->values();

    $judulSection = $toko->teks('layanan_judul');
@endphp

@if ($judulSection !== '' || $layanan->isNotEmpty())
<section class="layanan-blok relative py-16 lg:py-24 bg-surface">
    <div class="container-page">
        <div class="text-center">
            <span class="part-no">05 / {{ $judulSection !== '' ? $judulSection : __('site.home.layanan_judul') }}</span>
            @if ($judulSection !== '')
                <h2 class="section-heading mx-auto mt-3 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                    {{ $judulSection }}
                </h2>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3 lg:gap-6">
            @foreach ($layanan as $l)
                <article class="layanan-kartu {{ $l['utama'] ? 'layanan-kartu--utama' : '' }}">
                    <span class="layanan-kartu__nomor">{{ $l['nomor'] }}</span>
                    @if ($l['judul'] !== '')
                        <h3 class="layanan-kartu__judul">{{ $l['judul'] }}</h3>
                    @endif
                    @if ($l['isi'] !== '')
                        <p class="layanan-kartu__isi">{{ $l['isi'] }}</p>
                    @endif

                    <a href="{{ route('front.contact') }}" class="layanan-kartu__tautan">
                        {{ __('site.nav.contact') }}
                        <x-ikon name="arrow-right" :size="14" />
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
