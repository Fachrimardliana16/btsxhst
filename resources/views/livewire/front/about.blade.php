<div>
    @php
        // Isi halaman ini datang dari tab Pengaturan → Konten Halaman, bukan
        // dari berkas terjemahan. Baris yang masih kosong tidak dirender,
        // sehingga setelah basis data dikosongkan halaman ini benar-benar
        // kosong sampai admin mengisinya.
        $langkah = collect(range(1, 3))
            ->map(fn (int $i): array => [
                'judul' => $toko->teks('about_langkah_'.$i.'_judul'),
                'isi' => $toko->teks('about_langkah_'.$i.'_isi'),
            ])
            ->filter(fn (array $l): bool => filled($l['judul']) || filled($l['isi']))
            ->values();

        $judul = $toko->teks('about_judul');
        $proses = $toko->teks('about_proses');
        $deskripsi = $toko->teks('site_description');
    @endphp

    <div class="container-page py-8 lg:py-12">
        <div class="mx-auto max-w-3xl">

            <span class="part-no">01 / {{ __('site.nav.about') }}</span>
            @if ($judul !== '')
                <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ $judul }}</h1>
            @endif

            @if ($deskripsi !== '' || $proses !== '')
                <div class="mt-6 space-y-4 leading-relaxed text-muted">
                    @if ($deskripsi !== '')
                        <p>{{ $deskripsi }}</p>
                    @endif
                    @if ($proses !== '')
                        <p>{{ $proses }}</p>
                    @endif
                </div>
            @endif

            <div class="measure-line my-8"></div>

            @if (collect($angka)->filter()->isNotEmpty())
                <dl class="grid grid-cols-2 gap-6 sm:grid-cols-3">
                    @if (($angka['produk'] ?? 0) > 0)
                        <div>
                            <dt class="part-no">{{ __('site.home.stats_products') }}</dt>
                            <dd class="tech mt-1 text-3xl">{{ number_format($angka['produk'], 0, ',', '.') }}</dd>
                        </div>
                    @endif

                    @if (($angka['pesanan'] ?? 0) > 0)
                        <div>
                            <dt class="part-no">{{ __('site.home.stats_customers') }}</dt>
                            <dd class="tech mt-1 text-3xl">{{ number_format($angka['pesanan'], 0, ',', '.') }}</dd>
                        </div>
                    @endif

                    @if (($angka['kategori'] ?? 0) > 0)
                        <div>
                            <dt class="part-no">{{ __('site.footer.categories') }}</dt>
                            <dd class="tech mt-1 text-3xl">{{ number_format($angka['kategori'], 0, ',', '.') }}</dd>
                        </div>
                    @endif
                </dl>
            @endif

            @if ($langkah->isNotEmpty())
                <div class="measure-line my-8"></div>

                <ol class="space-y-5">
                    @foreach ($langkah as $i => $l)
                        <li class="flex gap-4">
                            <span class="tech flex-none text-accent">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <div>
                                @if ($l['judul'] !== '')
                                    <p class="font-semibold">{{ $l['judul'] }}</p>
                                @endif
                                @if ($l['isi'] !== '')
                                    <p class="mt-1 text-sm text-muted">{{ $l['isi'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('front.products.index') }}" class="btn btn-primary">
                    {{ __('site.home.view_all_products') }}
                </a>
                <a href="{{ route('front.contact') }}" class="btn btn-outline">{{ __('site.nav.contact') }}</a>
            </div>
        </div>
    </div>
</div>
