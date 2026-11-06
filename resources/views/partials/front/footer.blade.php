@php
    /** @var \App\Support\Pengaturan $toko */
    $tahun = now()->year;

    // Diukur pada 390x844: enam kategori berarti enam baris di kolom yang
    // sempit. Empat sudah cukup untuk mengenalkan struktur katalog.
    //
    // Di-cache dengan pola yang sama seperti navbar: footer ada di SETIAP
    // halaman, dan sebelumnya daftar ini dibaca ulang untuk setiap pengunjung.
    // Kunci memuat versi katalog, jadi admin mengubah kategori → versi naik →
    // cache lama tidak terpakai lagi.
    $batasFooter = max(2, (int) config('iksan.home.footer_categories', 4));

    $kategoriFooter = \App\Support\CacheKatalog::ingat(
        'footer.kategori',
        ['limit' => $batasFooter, 'bahasa' => app()->getLocale()],
        600,
        fn () => \App\Models\Category::query()
            ->aktif()
            ->terurut()
            ->take($batasFooter)
            ->get(),
    );
@endphp

{{-- Footer.
     Diukur pada viewport 390x844, footer lama setinggi 1007 px — 1,19 layar
     penuh, lebih tinggi daripada hero. Penyebabnya: empat blok ditumpuk
     vertikal dengan jarak 40 px, padding 48 px atas dan bawah, margin atas
     64 px, dan dua kolom tautan yang sebagian besar mengulang bar navigasi
     bawah (Home, Produk, dan Kontak sudah ada di sana).

     Di ponsel, susunannya sekarang: identitas dan kontak masing-masing
     selebar penuh, lalu kategori dan navigasi berdampingan dalam dua kolom.
     Tautan yang sudah ada di bar bawah dibuang dari sini, kecuali "Lacak
     pesanan" yang tidak ada di sana dan dipindah ke baris hak cipta. --}}
<footer class="relative mt-16 bg-[#08121b] text-white/90 border-t border-accent/20 lg:mt-28">
    <div class="container-page py-10 lg:py-16">

        {{-- Satu kolom di ponsel, empat di desktop.

             Versi sebelumnya `grid-cols-2` dengan kolom 1 dan 3 `col-span-2`.
             Akibatnya kolom Kategori duduk SENDIRI di baris kedua dengan lebar
             167px (separuh), sementara kontak 358px (penuh) — terukur pada
             390px. Footer jadi bergerigi: satu kolom sempit diapit dua kolom
             penuh, dan sisi kanannya kosong.

             Di ponsel semua kolom kini selebar penuh dan bertumpuk rapi; di
             layar besar keempatnya berdampingan dengan proporsi tetap. --}}
        <div class="grid grid-cols-1 gap-y-7 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:gap-10">

            <div>
                <p class="font-display text-2xl font-semibold">
                    {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                </p>
                <p class="mt-3 max-w-xs text-sm text-[#a5a5a8]">
                    {{ $toko->teks('site_tagline', __('site.brand.tagline')) }}
                </p>
                <p class="mt-3 text-sm text-[#a5a5a8]">
                    {{ $toko->teks('address_full') }}
                </p>
            </div>

            {{-- Navigasi: hanya desktop. Di ponsel, Home, Produk, dan Kontak
                 sudah tersedia di bar navigasi bawah sehingga mengulanginya di
                 sini hanya menambah tinggi tanpa menambah jalan. --}}
            {{-- Di desktop: blok terbuka seperti kolom biasa. Di mobile:
                 <details> yang menumpuk dan baru terbuka saat disentuh, agar
                 footer tidak mendorong halaman menjadi dua layar panjang.
                 Atribut `open` dipasang pada lebar besar lewat CSS, bukan
                 JavaScript, supaya tetap benar walau skrip gagal dimuat. --}}
            <details class="footer-akordeon hidden lg:block" open>
                <summary class="part-no cursor-pointer list-none lg:cursor-default">{{ __('site.footer.navigation') }}</summary>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('front.home') }}" class="footer-tautan hover:text-white">{{ __('site.nav.home') }}</a></li>
                    <li><a href="{{ route('front.products.index') }}" class="footer-tautan hover:text-white">{{ __('site.nav.products') }}</a></li>
                    <li><a href="{{ route('front.about') }}" class="footer-tautan hover:text-white">{{ __('site.nav.about') }}</a></li>
                    <li><a href="{{ route('front.contact') }}" class="footer-tautan hover:text-white">{{ __('site.nav.contact') }}</a></li>
                    <li><a href="{{ route('front.order.track') }}" class="footer-tautan hover:text-white">{{ __('site.order.track') }}</a></li>
                </ul>
            </details>

            <details class="footer-akordeon" open>
                <summary class="part-no cursor-pointer list-none lg:cursor-default">{{ __('site.footer.categories') }}</summary>
                <ul class="mt-3 space-y-2 text-sm lg:mt-3">
                    @foreach ($kategoriFooter as $kat)
                        <li>
                            <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                               class="footer-tautan hover:text-white">{{ $kat->nama() }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>

            {{-- Kontak: di mobile juga bisa ditutup, karena isinya panjang
                 (WhatsApp, surel, jam, ikon sosial). --}}
            <details class="footer-akordeon" open>
                <summary class="part-no cursor-pointer list-none lg:cursor-default">{{ __('site.footer.contact') }}</summary>
                {{-- `space-y-0` pada versi lama, sementara dua kolom lain memakai
                     `space-y-2`. Di ponsel ketiga kolom bertumpuk vertikal, jadi
                     jarak baris yang berbeda membuat irama footer patah di
                     sepertiga terakhir. Diseragamkan: mt-3 + space-y-2, sama
                     dengan kolom Kategori. --}}
                <ul class="mt-3 space-y-2 text-sm">
                    @if ($wa = $toko->teks('whatsapp_number'))
                        <li class="flex items-center gap-2">
                            <x-ikon name="whatsapp" :size="16" class="flex-none" />
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" rel="noopener"
                               class="footer-tautan tech hover:text-white">+{{ preg_replace('/\D/', '', $wa) }}</a>
                        </li>
                    @endif

                    @if ($email = $toko->teks('contact_email'))
                        <li class="flex items-center gap-2">
                            <x-ikon name="mail" :size="16" class="flex-none" />
                            <!--email_off--><a href="mailto:{{ $email }}" class="footer-tautan truncate hover:text-white">{{ $email }}</a><!--/email_off-->
                        </li>
                    @endif

                    @if ($jam = $toko->teks('operational_hours'))
                        <li class="flex items-start gap-2">
                            <x-ikon name="clock" :size="16" class="mt-0.5 flex-none" />
                            <span class="text-[#a5a5a8]">{{ $jam }}</span>
                        </li>
                    @endif
                </ul>

                @if ($toko->mediaSosial()->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($toko->mediaSosial() as $sosial)
                            <a href="{{ $sosial['url'] }}" target="_blank" rel="noopener noreferrer"
                               class="btn-icon !text-[#a5a5a8] hover:!text-white"
                               title="{{ ucfirst($sosial['platform']) }}">
                                <x-ikon :name="$sosial['platform']" />
                                <span class="visually-hidden">{{ ucfirst($sosial['platform']) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </details>
        </div>

        {{-- Baris pembayaran & ekspedisi.

             Isinya diambil dari pengaturan yang SUDAH ada
             (`manual_bank_accounts`, `shipping_couriers`), bukan daftar karangan:
             menuliskan "VISA / Mastercard" padahal toko hanya menerima transfer
             manual adalah janji yang tidak bisa dipenuhi. Bila pengaturannya
             kosong, baris ini tidak dirender sama sekali. --}}
        @php
            $daftarEkspedisi = array_values(array_filter(
                (array) json_decode((string) $toko->teks('shipping_couriers'), true),
                fn ($v) => is_string($v) && trim($v) !== ''
            ));

            // Rekening manual disimpan sebagai JSON berisi objek {bank, nomor, atas_nama}.
            $daftarBank = [];
            foreach ((array) json_decode((string) $toko->teks('manual_bank_accounts'), true) as $b) {
                if (is_array($b) && filled($b['bank'] ?? null)) {
                    $daftarBank[] = (string) $b['bank'];
                }
            }
            $daftarBank = array_values(array_unique($daftarBank));
        @endphp

        @if ($daftarBank !== [] || $daftarEkspedisi !== [])
            <div class="mt-8 flex flex-col gap-3 text-[length:var(--t-xs)] uppercase tracking-[0.12em] text-[#a5a5a8]
                        sm:flex-row sm:items-center sm:gap-6 lg:mt-12">
                @if ($daftarBank !== [])
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[#6f6f74]">{{ __('site.footer.payment_methods') }}</span>
                        @foreach ($daftarBank as $bank)
                            <span class="border border-white/15 px-2 py-1 text-[#d8d8d6]">{{ $bank }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($daftarEkspedisi !== [])
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[#6f6f74]">{{ __('site.footer.couriers') }}</span>
                        @foreach ($daftarEkspedisi as $kurir)
                            <span class="border border-white/15 px-2 py-1 text-[#d8d8d6]">{{ $kurir }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="measure-line mt-6 mb-5 lg:mt-8 lg:mb-6" style="background: rgba(255,255,255,.14)"></div>

        <div class="flex flex-col gap-2 text-xs text-[#a5a5a8] sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ $tahun }} {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}. {{ __('site.footer.rights') }}</p>
            <div class="flex flex-wrap gap-4">
                {{-- Lacak pesanan tidak ada di bar navigasi bawah, jadi
                     dipindahkan ke sini agar tetap terjangkau dari ponsel. --}}
                <a href="{{ route('front.order.track') }}" class="hover:text-white lg:hidden">{{ __('site.order.track') }}</a>
                <a href="{{ route('front.about') }}" class="hover:text-white lg:hidden">{{ __('site.nav.about') }}</a>
                <a href="{{ route('front.page', 'kebijakan-privasi') }}" class="footer-tautan hover:text-white">{{ __('site.footer.privacy') }}</a>
                <a href="{{ route('front.page', 'syarat-ketentuan') }}" class="footer-tautan hover:text-white">{{ __('site.footer.terms') }}</a>
            </div>
        </div>

        {{--
            Ruang bawah di halaman detail produk, ponsel dan tablet.

            Halaman itu punya dua lapisan melayang: bar aksi lengket (69 px, duduk
            60 px di atas dasar layar) dan bar navigasi bawah (60 px terbawah).
            Keduanya keluar dari aliran dokumen, jadi seluruh isi yang berada di
            dasar dokumen tertutup — dan pada gulir maksimum, yang berada di dasar
            dokumen adalah baris terakhir footer ini sendiri.

            Terukur pada 375x667 px, digulir sampai dasar (3003 px), sebelum ruang
            ini ada: baris "Syarat dan Ketentuan" berakhir di 627 px sementara
            puncak bar lengket di 538 px — 89 px isinya memang tidak terlihat.

            Dua hal yang menentukan letaknya, dan keduanya pernah salah:

            1. Ruang ini harus berada SETELAH isi terakhir. Ditaruh sebelum baris
               terbawah footer, ia justru mendorong baris itu keluar layar; ditaruh
               di dalam <main>, ia hanya menggeser footer tanpa mengubah apa pun,
               karena footer tetap menjadi elemen terakhir dokumen.
            2. Tingginya harus melebihi seluruh blok melayang (129 px), bukan hanya
               bar lengketnya (69 px).

            Disimpan di dalam footer, bukan setelahnya, supaya tidak muncul celah
            berwarna latar halaman di bawah footer.
        --}}
        @if (request()->routeIs('front.products.show'))
            {{-- Ruang cadangan di dasar halaman supaya baris terakhir footer
                 tidak tertutup dua lapisan melayang (bar aksi + bar navigasi).

                 Tingginya diturunkan dari variabel, bukan angka mati: dulu
                 h-[136px] ditulis tangan, sehingga setiap kali nav bawah
                 berubah tinggi, ruang ini ikut meleset. Rumusnya:
                 --h-navbawah (100px, sudah termasuk offset & padding)
                 + 4px celah
                 + 69px bar aksi (56 isi + 12 padding)
                 + 24px napas = 197px.

                 Dibulatkan ke atas: 200px. --}}
            <div class="h-[200px] lg:hidden" aria-hidden="true"></div>
        @endif
    </div>
</footer>

