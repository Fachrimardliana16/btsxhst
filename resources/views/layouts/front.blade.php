<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light"
      {{-- Dibaca resources/js/app.js untuk mendaftarkan ulang service worker
           dengan versi baru. Tanpa ini, peramban boleh memakai sw.js lama dan
           cache aset tidak pernah dibuang. --}}
      data-sw-versi="{{ \App\Support\VersiAset::serviceWorker() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        Judul halaman.

        Dibaca dari $title, bukan $judul. Livewire menyerahkan judul lewat
        View::title(), dan makro itu menyimpannya sebagai parameter bernama
        "title" pada layout. Selama nama yang dibaca di sini berbeda, seluruh
        tag <title> situs jatuh ke nama toko saja — judul halaman detail produk
        tidak pernah muncul di tab peramban maupun di hasil pencarian.
    --}}
    <title>{{ isset($title) && filled($title) ? $title : $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>
    <meta name="description" content="{{ $deskripsi ?? $toko->teks('site_description', __('site.brand.tagline')) }}">

    @isset($kanonikal)
        <link rel="canonical" href="{{ $kanonikal }}">
        <meta property="og:url" content="{{ $kanonikal }}">
    @endisset

    {{--
        GEO (Generative Engine Optimization).

        Mesin jawaban — ChatGPT, Perplexity, Gemini, AI Overviews — mengutip
        dari halaman yang jawabannya eksplisit. Tiga penanda di bawah memberi
        mereka izin dan bentuk kutipan yang jelas:

          ai-summary      ringkasan satu paragraf yang boleh dikutip apa adanya;
          ai-content-declaration  menyatakan isi ini untuk dikutip, bukan
                          diabaikan;
          max-snippet:-1  mencabut batas panjang cuplikan (bawaan mesin
                          pencari bisa memotong di tengah kalimat).

        Tanpa ringkasan eksplisit, mesin jawaban menyusun jawabannya sendiri
        dari menu navigasi, dan hasilnya sering salah sebut harga atau layanan.
    --}}
    @isset($ringkasanAi)
        @if (filled($ringkasanAi))
            <meta name="ai-summary" content="{{ $ringkasanAi }}">
            <meta name="ai-content-declaration" content="original">
        @endif
    @endisset

    <meta name="robots" content="{{ isset($tanpaIndeks) ? 'noindex, nofollow' : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1' }}">

    <?php /* Bahasa alternatif: halaman yang sama tersedia dalam dua bahasa
             lewat cookie, bukan lewat URL terpisah. Untuk mesin pencari, satu
             URL dengan dua bahasa dinyatakan sebagai dua hreflang pada URL
             yang sama — itu cara yang sah menyatakan "isi ini juga tersedia
             dalam bahasa lain" tanpa membuat isi ganda. */ ?>
    <link rel="alternate" hreflang="id" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="en" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

    @isset($metaOg)
        <meta property="og:type" content="{{ $metaOg['tipe'] ?? 'website' }}">
        <meta property="og:title" content="{{ $metaOg['judul'] }}">
        <meta property="og:description" content="{{ $metaOg['deskripsi'] }}">
        <meta property="og:site_name" content="{{ $toko->teks('site_name', __('site.brand.fallback_name')) }}">
        <meta name="twitter:card" content="summary_large_image">
        @if (filled($metaOg['gambar'] ?? null))
            <meta property="og:image" content="{{ $metaOg['gambar'] }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="{{ $metaOg['alt'] ?? $metaOg['judul'] }}">
        @endif
    @endisset
    {{-- PWA (Fase 2: P2-03). Manifes memasang nama, ikon, dan tombol pasang;
         service worker menyimpan aset statis dan halaman luring. Keduanya
         diletakkan di <head> supaya peramban menemukannya sebelum aset lain
         dimuat — manifes yang ditemukan terlambat tidak dianggap ada. --}}
    {{-- Slide hero pertama dimuat lebih dulu: peramban mulai mengunduhnya saat
         masih mengurai <head>, bukan setelah CSS dan skrip selesai. Tanpa ini,
         gambar terbesar di beranda baru diminta paling akhir. --}}
    @isset($heroPertama)
        @if (filled($heroPertama))
            <link rel="preload" as="image" href="{{ $heroPertama }}" fetchpriority="high">
        @endif
    @endisset

    {{-- Font di-preload, bukan hanya di-@font-face.
     *
     * Tanpa preload, peramban baru menemukan berkas font SETELAH CSS selesai
     * diurai — dan teks tidak bisa dilukis sampai font itu tiba atau batas
     * `font-display: swap` tercapai. Pada koneksi lambat selisih ini terasa
     * sebagai halaman yang "kosong lalu berkedip".
     *
     * `crossorigin` wajib ada walaupun font berasal dari domain yang sama:
     * tanpa itu peramban mengunduhnya dua kali (sekali untuk preload, sekali
     * lagi untuk pemakaian), dan preload-nya justru merugikan.
     * `type` mencegah peramban yang tidak mendukung woff2 mengunduh berkas
     * yang tidak akan terpakai. --}}
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="{{ asset('fonts/plus-jakarta-sans-var-latin.woff2') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="{{ asset('fonts/sora-var-latin.woff2') }}">

    {{-- ═══ PWA — DIKENDALIKAN SAKELAR `enable_pwa` ══════════════════════
         *
         * Sebelumnya manifest, ikon, dan pendaftaran service worker dipasang
         * TANPA SYARAT, sementara sakelar `enable_pwa` di panel tidak dibaca
         * oleh kode mana pun. Pemilik toko bisa mematikannya dan tidak ada
         * yang berubah — termasuk service worker yang tetap terpasang dan
         * terus menyimpan aset di peramban pengunjung.
         *
         * Dua hal yang perlu diketahui saat mematikan:
         *   1. Service worker yang SUDAH terpasang tidak bisa dicabut dari
         *      sisi server. Pengunjung yang pernah membuka situs masih punya
         *      salinannya. Karena itu, saat sakelar mati, halaman mengirim
         *      perintah `unregister()` — inilah satu-satunya cara mencabutnya.
         *   2. Manifest dan ikon ikut dilepas supaya peramban tidak lagi
         *      menawarkan "pasang aplikasi". --}}
    @php $pwaAktif = $toko->boolean('enable_pwa'); @endphp

    @if ($pwaAktif)
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/ikon-pwa-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ $toko->teks('site_name', __('site.brand.fallback_name')) }}">
    @endif

    <meta name="theme-color" content="#111114">

    <script nonce="{{ csp_nonce() }}">
        var pwaAktif = {{ $pwaAktif ? 'true' : 'false' }};

        if ('serviceWorker' in navigator) {
            if (!pwaAktif) {
                /* PWA dimatikan di panel: cabut service worker yang mungkin
                 * sudah terpasang di perangkat ini, lalu bersihkan cache-nya.
                 * Tanpa langkah ini, pengunjung lama tetap menerima aset dari
                 * cache walau pemilik toko sudah mematikan fiturnya. */
                navigator.serviceWorker.getRegistrations().then(function (daftar) {
                    daftar.forEach(function (sw) { sw.unregister(); });
                }).catch(function () {});

                if (window.caches && caches.keys) {
                    caches.keys().then(function (kunci) {
                        kunci.forEach(function (k) { caches.delete(k); });
                    }).catch(function () {});
                }
            } else {
                window.addEventListener('load', function () {
                    /* Versi DI URL, bukan hanya di dalam berkas.
                     *
                     * Peramban hanya memeriksa ulang sw.js secara berkala, dan
                     * selama alamatnya sama ia boleh memakai salinan lama tanpa
                     * bertanya ke server. Akibatnya service worker versi lama
                     * tetap aktif dan terus menyajikan CSS/JS dari cache-nya
                     * sendiri — tampilan pengunjung tidak berubah walaupun
                     * server sudah diperbaiki.
                     *
                     * Dengan menaruh versi di query string, alamatnya berubah
                     * setiap rilis, sehingga peramban memperlakukannya sebagai
                     * service worker BARU. */
                    var versi = '{{ \App\Support\VersiAset::serviceWorker() }}';

                    navigator.serviceWorker.register('/sw.js?v=' + versi, { scope: '/' }).catch(function () {
                        /* Pendaftaran gagal tidak boleh menghentikan halaman. */
                    });
                });
            }
        }
    </script>

    {{-- ═══ ANALITIK — HANYA BILA ID DIISI ══════════════════════════════
         *
         * `analytics_id` dan `meta_pixel_id` sudah ada di panel sejak awal,
         * tetapi tidak pernah dicetak ke halaman: pemilik toko mengisi ID,
         * menunggu datanya masuk, dan tidak terjadi apa-apa.
         *
         * ID diperiksa ketat (hanya huruf, angka, tanda hubung) sebelum
         * dicetak. Nilai ini masuk ke dalam <script>, jadi satu tanda kutip
         * yang lolos berarti jalan masuk bagi skrip sembarang. --}}
    @php
        $gaId = trim((string) $toko->teks('analytics_id', ''));
        $pixelId = trim((string) $toko->teks('meta_pixel_id', ''));
        $gaSah = preg_match('/^[A-Za-z0-9\-]+$/', $gaId) === 1;
        $pixelSah = preg_match('/^[0-9]+$/', $pixelId) === 1;
    @endphp

    @if ($gaSah)
        <script nonce="{{ csp_nonce() }}" async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script nonce="{{ csp_nonce() }}">
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}');
        </script>
    @endif

    @if ($pixelSah)
        <script nonce="{{ csp_nonce() }}">
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ $pixelId }}');
            fbq('track', 'PageView');
        </script>
    @endif

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/ikon-pwa-192.png">

    @if (filled($toko->teks('site_favicon')))
        <link rel="icon" href="{{ \App\Models\ProductMedia::urlMedia($toko->teks('site_favicon')) }}">
    @endif

    {{-- Selalu tema light --}}
    <script nonce="{{ csp_nonce() }}">
        document.documentElement.setAttribute('data-theme', 'light');
    </script>

    {{-- Dua berkas font pertama dimuat lebih awal (checklist F11-04).
         Yang dimuat lebih awal hanya bobot yang benar-benar dipakai untuk teks
         utama di layar pertama; menyiapkan semua berkas justru menghabiskan
         lebar pita dan menunda hal lain. --}}
    {{-- Font Plus Jakarta Sans & Sora dimuat via CSS @font-face --}}


    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/bar-aksi.js'])

    {{-- Livewire WAJIB dimuat eksplisit di sini.
         ...
         Tanpa kedua direktif ini, markup komponen dirender (wire:id,
         wire:snapshot ada di HTML) tetapi livewire.js tidak pernah dimuat,
         sehingga SEMUA wire:click mati — tombol + dan − pada pemilih jumlah,
         tambah ke keranjang, filter katalog, semuanya tidak bereaksi.
         Itu bukan kegagalan sebagian: seluruh interaksi Livewire berhenti,
         dan halaman tampak "normal" karena CSS-nya tetap termuat.
    --}}
    @livewireStyles
</head>
<body class="situs-depan min-h-screen bg-bg text-ink antialiased">

    <a href="#konten-utama"
       class="visually-hidden focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-surface focus:px-4 focus:py-2 focus:text-ink">
        {{ __('site.common.search') }}
    </a>

    @include('partials.front.navbar')

    {{-- Isi halaman selebar penuh.

         Dulu kelas ini membawa padding kiri 16rem selama sidebar tampil.
         Sidebar sudah dihapus (lihat resources/css/navbar.css), jadi padding
         itu ikut hilang — kalau dibiarkan, layar lebar menyisakan kolom kosong
         di kiri yang tidak lagi ditempati apa pun. --}}
    <main id="konten-utama" class="konten-utama pb-20 lg:pb-0">
        {{ $slot }}
    </main>

    @include('partials.front.footer')
    @include('partials.front.bottom-nav')
    @include('partials.front.whatsapp')
    @include('partials.front.cookie-notice')

    @isset($remah)
        @if (is_array($remah) && count($remah) > 1)
            @php
                $daftarRemah = [];

                foreach (array_values($remah) as $i => $r) {
                    $daftarRemah[] = [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $r['nama'],
                        'item' => $r['tautan'],
                    ];
                }

                // Store ikut di setiap halaman yang mengirim remah. Mesin
                // jawaban sering mengutip halaman kategori tanpa membuka
                // beranda, jadi identitas penjual harus ada di sini juga.
                $jsonRemah = json_encode([
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        \App\Support\DataTerstruktur::toko(),
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => $daftarRemah,
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $jsonRemah = str_replace(['</', '<!--'], ['<\/', '<\!--'], (string) $jsonRemah);
            @endphp
            <script type="application/ld+json">
{!! $jsonRemah !!}
            </script>
        @endif
    @endisset

    @isset($faq)
        @if (is_array($faq) && $faq !== [])
            @php
                $grafik = \App\Support\DataTerstruktur::untuk(['faq' => $faq]);
                $jsonFaq = json_encode($grafik, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $jsonFaq = str_replace(['</', '<!--'], ['<\/', '<\!--'], (string) $jsonFaq);
            @endphp
            <script type="application/ld+json">
{!! $jsonFaq !!}
            </script>
        @endif
    @endisset

    @if (filled($kepala ?? null))
        {{-- Sudah berupa HTML yang dirender view lain (misalnya blok JSON-LD),
             jadi dicetak apa adanya. Isinya dibangun dari json_encode sehingga
             tanda kurung sudut di dalam data sudah dilolosikan lebih dulu. --}}
        {!! $kepala !!}
    @endif

    @livewireScripts
</body>
</html>
