{{--
    SECTION 3 — LANGKAH PEMESANAN (struktur acuan: judul kiri, timeline kanan).

    Empat langkah diambil dari alur yang BENAR ada di toko:
    pilih produk → cek varian → bayar (transfer manual + unggah bukti) →
    terima (resi via WhatsApp). Tidak ada langkah karangan seperti "virtual
    tour" atau "hubungi agen".
--}}
@php
    $langkah = ['langkah_1', 'langkah_2', 'langkah_3', 'langkah_4'];
@endphp

<section class="langkah-blok relative py-16 lg:py-24 bg-surface border-y border-line-soft/60">
    <div class="container-page">
        <div class="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">

            <div class="lg:sticky lg:top-28 lg:self-start">
                <span class="part-no">03 / {{ __('site.home.about_part') }}</span>
                <h2 class="section-heading mt-3 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                    {{ __('site.home.langkah_judul') }}
                </h2>

                <a href="{{ route('front.order.track') }}" class="cta-ukur__tautan mt-6 inline-flex">
                    {{ __('site.order.track') }}
                    <x-ikon name="arrow-right" :size="14" />
                </a>
            </div>

            <ol class="langkah-daftar">
                @foreach ($langkah as $i => $kunci)
                    <li class="langkah-item group p-5 rounded-2xl bg-bg border border-line-soft transition-all duration-300 hover:border-accent/40 hover:shadow-md" data-reveal style="transition-delay: {{ $i * 60 }}ms">
                        <span class="langkah-item__nomor flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent text-white font-display text-base font-bold shadow-md shadow-accent/20 group-hover:scale-110 transition-transform">{{ $i + 1 }}</span>

                        <div class="min-w-0">
                            <p class="langkah-item__judul">
                                {{ __('site.home.langkah_judul_singkat') }} {{ $i + 1 }}
                            </p>
                            <p class="langkah-item__isi">{{ __("site.home.{$kunci}") }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
