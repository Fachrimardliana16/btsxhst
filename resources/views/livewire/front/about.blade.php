<div>
    <div class="container-page py-8 lg:py-12">
        <div class="mx-auto max-w-3xl">

            <span class="part-no">01 / {{ __('site.nav.about') }}</span>
            <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.home.about_title') }}</h1>

            <div class="mt-6 space-y-4 leading-relaxed text-muted">
                <p>{{ $toko->teks('site_description') }}</p>
                <p>{{ __('site.about.proses') }}</p>
            </div>

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

            <div class="measure-line my-8"></div>

            <ol class="space-y-5">
                <li class="flex gap-4">
                    <span class="tech flex-none text-accent">01</span>
                    <div>
                        <p class="font-semibold">{{ __('site.about.langkah_1_judul') }}</p>
                        <p class="mt-1 text-sm text-muted">
                            {{ __('site.about.langkah_1_isi') }}
                        </p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="tech flex-none text-accent">02</span>
                    <div>
                        <p class="font-semibold">{{ __('site.about.langkah_2_judul') }}</p>
                        <p class="mt-1 text-sm text-muted">
                            {{ __('site.about.langkah_2_isi') }}
                        </p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="tech flex-none text-accent">03</span>
                    <div>
                        <p class="font-semibold">{{ __('site.about.langkah_3_judul') }}</p>
                        <p class="mt-1 text-sm text-muted">
                            {{ __('site.about.langkah_3_isi') }}
                        </p>
                    </div>
                </li>
            </ol>

            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('front.products.index') }}" class="btn btn-primary">
                    {{ __('site.home.view_all_products') }}
                </a>
                <a href="{{ route('front.contact') }}" class="btn btn-outline">{{ __('site.nav.contact') }}</a>
            </div>
        </div>
    </div>
</div>
