{{--
    Data terstruktur produk (checklist F6-07).

    Diletakkan di dalam <body> dan diteruskan lewat layoutData, bukan lewat
    @push/@stack: Livewire merender komponen halaman lebih dulu, baru
    menyuntikkan hasilnya ke layout, sehingga isi @push dari komponen tidak
    pernah sampai ke @stack milik layout — blok ini akan hilang tanpa galat.

    Blok JSON-LD di dalam <body> sah menurut spesifikasi skema dan tetap dibaca
    mesin pencari. Yang perlu dijaga hanyalah urutan tag penutup: setiap "</"
    di dalam data dilolosikan supaya tidak ada nilai yang dapat menutup tag
    script lebih awal.
--}}
@php
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $produk->judul(),
        'sku' => $produk->sku,
        'description' => $deskripsi,
        'category' => $kategori,
        'image' => array_values(array_filter([$gambar])),
        'brand' => [
            '@type' => 'Brand',
            'name' => $toko,
        ],
    ];

    // Tawaran hanya dicantumkan bila ada varian yang benar-benar dapat dibeli.
    // Mencantumkan penawaran tanpa harga atau tanpa ketersediaan justru
    // menandai halaman sebagai data tidak lengkap di mata mesin pencari.
    $tersedia = array_values(array_filter($sku, fn (array $v) => $v['stok'] > 0));

    if ($tersedia !== []) {
        // Harga di data terstruktur WAJIB sama dengan yang dilihat pembeli.
        // Mencantumkan rupiah sementara halaman menulis dolar membuat Google
        // menandai halaman sebagai harga tidak cocok, dan pembeli dari hasil
        // pencarian datang dengan angka yang salah di kepalanya.
        $hargaDolar = array_map(
            fn (array $v) => \App\Support\HargaDolar::dariRupiah($v['harga']),
            $tersedia,
        );

        $data['offers'] = [
            '@type' => 'AggregateOffer',
            'priceCurrency' => \App\Support\HargaDolar::MATA,
            'offerCount' => count($tersedia),
            'lowPrice' => (string) min($hargaDolar),
            'highPrice' => (string) max($hargaDolar),
            'availability' => 'https://schema.org/InStock',
            'url' => url()->current(),
        ];
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $json = str_replace(['</', '<!--'], ['<\/', '<\!--'], (string) $json);
@endphp

<script type="application/ld+json">
{!! $json !!}
</script>
