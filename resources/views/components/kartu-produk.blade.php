{{--
    Kartu produk.

    Seluruh badan kartu dapat diklik, bukan hanya gambar dan judul. Satu
    lapisan tautan absolut menutupi kartu, lalu elemen yang punya tindakan
    sendiri (tombol keranjang) dinaikkan ke atas lapisan itu. Pola ini menjaga
    kartu tetap satu tautan bagi pembaca layar — tanpa tautan bersarang, tanpa
    JavaScript.

    PROPORSI. Versi sebelumnya menumpuk enam lapis teks (kategori, judul, SKU,
    "mulai dari", harga, dolar, jumlah varian) di bawah gambar 4:3. Di grid dua
    kolom pada ponsel hasilnya kartu kurus dan memanjang — terlihat tidak
    seimbang. Perbaikannya bukan memperkecil font, melainkan membuang yang tidak
    perlu dan memindahkan yang bisa dipindah:

      1. SKU dihapus dari kartu. Nomor bagian itu berguna di halaman produk,
         tidak di daftar — pembeli memilih dari gambar dan harga.
      2. Jumlah varian pindah ke atas gambar sebagai lencana, bukan baris teks
         tersendiri. Ia informasi sekunder dan tidak boleh memakan tinggi.
      3. Harga ditulis SEKALI dalam dolar. Versi paling awal menumpuk rupiah
         besar + "≈ $..." kecil di sebelahnya; begitu dolar menjadi harga
         yang benar-benar ditagih, pasangan itu hanya membuat pembeli
         bertanya mana yang harus dipercaya.
      4. Judul diberi tinggi tetap dua baris (min-h). Tanpa itu, kartu dengan
         judul satu baris dan dua baris berdampingan jadi berbeda tinggi, dan
         baris harganya tidak sejajar — itulah yang paling terasa "tidak rapi".
      5. Umpan balik tombol tidak lagi memesan ruang kosong. Ia hanya muncul
         saat ada pesan, jadi kartu tenang tidak menyisakan celah.
--}}
@php
    /** @var \App\Models\Product $produk */
    $media = $produk->gambarUtama();
    $adaStok = $produk->adaStok();
    $harga = \App\Support\HargaDolar::mulai($produk);
    $jumlahVarian = $produk->variants->where('is_active', true)->count();
    $tujuan = route('front.products.show', $produk);
    $varianPertama = $produk->variants->where('is_active', true)->first();

    // Status stok bertingkat. Ambang "terbatas" memakai kunci Pengaturan yang
    // sudah ada (low_stock_threshold, bawaan 3) — sumber yang sama dengan
    // notifikasi stok menipis di panel admin, jadi keduanya tidak bisa berbeda.
    // Dihitung di server sekali per kartu; tidak ada kueri tambahan karena
    // totalStok() memakai koleksi varian yang sudah dimuat halaman.
    // `$toko` tidak diwarisi otomatis oleh komponen Blade, jadi dibaca di sini.
    // Pengaturan::instance() menyimpan hasilnya di memori, sehingga 24 kartu di
    // satu halaman tetap satu pembacaan.
    $ambangTipis = max(1, (int) \App\Support\Pengaturan::instance()->angka('low_stock_threshold', 3));
    $stokTotal = $adaStok ? $produk->totalStok() : 0;

    $statusStok = ! $adaStok
        ? 'habis'
        : ($stokTotal <= $ambangTipis ? 'tipis' : 'ada');
@endphp

<article class="plate plate-interaktif group relative flex h-full flex-col overflow-hidden">

    {{-- Lapisan tautan: menutupi seluruh kartu. z-0, di bawah isi. --}}
    <a href="{{ $tujuan }}" wire:navigate
       class="absolute inset-0 z-0"
       aria-label="{{ $produk->judul() }}">
        <span class="visually-hidden">{{ $produk->judul() }}</span>
    </a>

    <div class="relative z-0 aspect-[4/3] overflow-hidden bg-sunken">
        @if ($media)
            <img src="{{ $media->url('card') }}"
                 srcset="{{ $media->url('thumb') }} 320w, {{ $media->url('card') }} 640w, {{ $media->url('full') }} 1600w"
                 sizes="(min-width: 1024px) 25vw, 50vw"
                 width="{{ $media->width ?: 640 }}" height="{{ $media->height ?: 480 }}"
                 alt="{{ $media->alt() }}"
                 loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.05]">
        @else
            <div class="flex h-full w-full items-center justify-center text-dim">
                <x-ikon name="image" :size="32" />
            </div>
        @endif

        {{-- Lencana di atas gambar: informasi sekunder yang tidak memakan
             tinggi kartu.

             Tata letak dipisah dua sudut supaya tidak bertumpuk:
             kiri atas  = status stok / jumlah varian (tidak dapat diklik)
             kanan atas = tombol suka (dapat diklik, z-20)

             Dulu keduanya berbagai satu baris flex kanan, sehingga pada kartu
             sempit lencana "X VARIAN" menimpa ikon hati. --}}
        <div class="pointer-events-none absolute left-2 top-2 z-10 flex flex-col items-start gap-1">
            @if ($statusStok === 'habis')
                <span class="badge badge-danger bg-surface">
                    {{ __('site.product.out_of_stock') }}
                </span>
            @elseif ($statusStok === 'tipis')
                {{-- Sisa stok disebut angkanya: "stok terbatas" tanpa angka
                     tidak memberi tahu apakah tinggal 1 atau 3. --}}
                <span class="badge badge-warn bg-surface">
                    {{ __('site.product.low_stock') }} · {{ $stokTotal }}
                </span>
            @endif

            @if ($jumlahVarian > 1)
                <span class="badge bg-surface/90 text-[length:var(--t-2xs)] backdrop-blur">
                    {{ $jumlahVarian }} {{ __('site.product.variant') }}
                </span>
            @endif
        </div>

        <div class="absolute right-2 top-2 z-20">
            {{-- Jumlah dan status "sudah disukai" datang dari halaman pemanggil,
                 bukan dihitung komponen ini. Satu kueri untuk seluruh halaman,
                 bukan dua per kartu — lihat ProductLike::propsUntuk(). --}}
            <livewire:front.suka-produk
                :produk-id="$produk->id"
                :disukai="isset($suka['disukai'][$produk->id])"
                :jumlah="(int) ($suka['hitungan'][$produk->id] ?? 0)"
                :key="'suka-'.$produk->id" />
        </div>
    </div>

    <div class="flex flex-1 flex-col p-3 lg:p-4">
        <p class="part-no truncate">{{ $produk->category?->nama() }}</p>

        {{-- Tinggi tetap dua baris: kartu yang berdampingan tetap sejajar, dan
             baris harga selalu jatuh di garis yang sama. --}}
        {{-- h2, bukan h3. Di /produk dan /kategori tidak ada h2 di antara h1
             halaman dan grid ini, sehingga h3 melompati satu tingkat dan audit
             heading-order gagal. Di beranda kartu tetap berada di bawah h2
             section, dan h2 setelah h2 tidak melompati apa pun. --}}
        <h2 class="mt-1 line-clamp-2 min-h-[2.6em] text-[length:var(--t-sm)] font-semibold leading-snug lg:text-base">
            {{-- Teks judul tanpa tautan sendiri: lapisan di atas sudah menjadi
                 tautannya, dan dua tautan bernama sama membingungkan pembaca
                 layar. pointer-events-none agar klik jatuh ke lapisan. --}}
            <span class="pointer-events-none">{{ $produk->judul() }}</span>
        </h2>

        <div class="mt-auto pt-3">
            {{-- Harga dolar, satu angka. Dulu ada dua: rupiah besar dan
                 "≈ $..." kecil di sebelahnya. Sekarang pasarnya luar negeri
                 dan harga yang ditagih memang dolar, jadi pasangan itu cuma
                 membuat pembeli bertanya mana yang harus dipercaya.
                 tabular-nums menjaga lebarnya tetap antar kartu. --}}
            <p class="pointer-events-none flex items-baseline gap-1.5">
                @if ($jumlahVarian > 1)
                    <span class="text-[length:var(--t-2xs)] text-dim">{{ \App\Support\HargaDolar::labelDari() }}</span>
                @endif
                <span class="tech text-base font-semibold tabular-nums lg:text-lg">{{ dolar($harga) }}</span>
            </p>

            {{-- Status stok SUDAH dinyatakan lencana di atas gambar. Tidak
                 diulang di sini: versi sebelumnya menulis "Habis" dua kali
                 dalam satu kartu (lencana + teks di bawah harga), dan pada
                 grid dua kolom ponsel itu terbaca sebagai dua produk berbeda
                 yang sama-sama habis. Satu kartu, satu pernyataan. --}}

            {{-- Tombol keranjang: barisnya sendiri di bawah harga, lebar penuh.
                 Ia dinaikkan di atas lapisan tautan (z-10) supaya menambah
                 barang, bukan membuka halaman produk. Produk tanpa stok tidak
                 mendapat tombol. --}}
            @if ($adaStok && $varianPertama !== null)
                <div class="relative z-10 mt-2.5">
                    <livewire:front.tambah-keranjang-cepat
                        :varian-id="$varianPertama->id"
                        :key="'cepat-'.$produk->id" />
                </div>
            @endif
        </div>
    </div>
</article>
