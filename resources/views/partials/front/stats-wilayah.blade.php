{{--
    SECTION 5 — STATISTIK & LAYANAN (struktur acuan: metrik kiri, kartu kanan
    berisi sorotan wilayah + peta).

    Semua angka diambil dari basis data (produk terbit, kategori, pesanan
    selesai). Yang TIDAK dipakai: "10+ Juta" dan "8× lebih" dari acuan —
    itu angka properti yang tidak ada padanannya di toko knalpot, dan
    menampilkannya berarti berbohong ke pembeli.

    Kartu sorotan wilayah memakai peta dari Pengaturan (`maps_embed`) dan
    alamat toko, bukan "Abuja, Nigeria".
--}}
@php
    $angkaProduk = (int) ($angka['produk'] ?? 0);
    $angkaPesanan = (int) ($angka['pesanan'] ?? 0);
    $angkaKategori = (int) ($angka['kategori'] ?? 0);

    $metrik = array_values(array_filter([
        $angkaProduk > 0 ? ['nilai' => number_format($angkaProduk, 0, ',', '.'), 'label' => __('site.home.stats_products')] : null,
        $angkaPesanan > 0 ? ['nilai' => number_format($angkaPesanan, 0, ',', '.'), 'label' => __('site.home.stats_customers')] : null,
        $angkaKategori > 0 ? ['nilai' => number_format($angkaKategori, 0, ',', '.'), 'label' => __('site.footer.categories')] : null,
    ]));

    $peta = $toko->teks('maps_embed');
@endphp

@if ($metrik !== [])
    <section class="stats-blok relative py-16 lg:py-24 bg-surface-sunken/40">
        <div class="container-page">
            <div class="grid gap-10 lg:grid-cols-2 lg:gap-14">

                {{-- Kiri: metrik --}}
                <div>
                    <span class="part-no">04 / {{ __('site.home.stats_judul') }}</span>

                    <dl class="stats-daftar mt-6">
                        @foreach ($metrik as $m)
                            <div class="stats-item">
                                <dt class="stats-item__label">{{ $m['label'] }}</dt>
                                <dd class="stats-item__nilai">{{ $m['nilai'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Kanan: kartu sorotan (alamat + peta) --}}
                <div class="stats-kartu p-6 lg:p-8 rounded-3xl bg-surface border border-line-soft shadow-lg shadow-black/[0.03]">
                    <p class="part-no">{{ __('site.nav.contact') }}</p>
                    <h3 class="mt-2 font-display text-xl font-semibold leading-tight sm:text-2xl">
                        {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                    </h3>
                    <p class="mt-3 text-sm leading-relaxed text-muted">
                        {{ $toko->teks('address_full') }}
                    </p>

                    @if (filled($peta))
                        {{-- Peta disisipkan apa adanya dari Pengaturan. Ia sudah
                             berupa iframe yang diisi admin, jadi tidak perlu
                             dibangun ulang di sini. --}}
                        <div class="stats-kartu__peta">
                            {!! $peta !!}
                        </div>
                    @else
                        <div class="stats-kartu__peta stats-kartu__peta--kosong">
                            <x-ikon name="map" :size="28" />
                            <span>{{ __('site.contact.hours') }}</span>
                        </div>
                    @endif

                    <a href="{{ route('front.contact') }}" class="cta-ukur__tautan mt-5 inline-flex">
                        {{ __('site.nav.contact') }}
                        <x-ikon name="arrow-right" :size="14" />
                    </a>
                </div>
            </div>
        </div>
    </section>
@endif
