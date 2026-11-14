<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data terstruktur (JSON-LD) tingkat situs untuk SEO dan GEO.
 *
 * SEO: memberi mesin pencari fakta yang dapat ditampilkan langsung — nama toko,
 * alamat, jam buka, nomor telepon, dan daftar halaman utama. Tanpa ini, mesin
 * pencari menebak dari teks bebas, dan tebakan itu sering salah (jam buka
 * tertukar dengan jam pengiriman, nomor telepon terbaca sebagai nomor lain).
 *
 * GEO (Generative Engine Optimization): mesin jawaban — ChatGPT, Perplexity,
 * Gemini, AI Overviews — mengambil kalimat dari halaman yang menjawab pertanyaan
 * secara eksplisit. `@type: Store` + `LocalBusiness` + `FAQPage` memberi mereka
 * potongan yang tinggal dikutip, alih-alih menyusun ulang dari menu navigasi.
 *
 * Yang ditulis di sini HANYA fakta yang benar-benar ada di basis data. Data
 * terstruktur yang tidak cocok dengan isi halaman membuat situs kehilangan
 * kepercayaan mesin pencari, dan itu jauh lebih merugikan daripada tidak punya
 * data terstruktur sama sekali.
 */
class DataTerstruktur
{
    /** Kunci cache; dinaikkan versi katalog akan membatalkannya juga. */
    private const KUNCI = 'seo:data-terstruktur';

    /**
     * Grafik JSON-LD untuk satu halaman.
     *
     * @param  array{judul?: string, deskripsi?: string, kanonikal?: string, tipe?: string}  $meta
     * @return array<string, mixed>|null
     */
    public static function untuk(array $meta = [], ?Product $produk = null): ?array
    {
        $simpul = [];

        // Toko: dipasang di SEMUA halaman. Mesin jawaban sering mengutip satu
        // halaman produk tanpa pernah membuka beranda, jadi identitas toko
        // harus ikut di setiap halaman, bukan hanya di beranda.
        $simpul[] = self::toko();

        if ($produk !== null) {
            // Data produk sudah ditangani partial data-terstruktur (dengan
            // penawaran per varian). Yang ditambahkan di sini hanya jalur
            // navigasi, supaya mesin pencari tahu posisi halaman ini.
            $simpul[] = self::remah($produk);
        }

        if (isset($meta['faq']) && is_array($meta['faq']) && $meta['faq'] !== []) {
            $simpul[] = self::faq($meta['faq']);
        }

        if ($simpul === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $simpul,
        ];
    }

    /**
     * Storefront: daftar produk di SATU halaman katalog (ItemList).
     *
     * Halaman katalog yang memuat 12 produk tetapi tidak menyatakan dirinya
     * sebagai daftar membuat mesin pencari memperlakukannya sebagai halaman
     * biasa — produk-produk di dalamnya tidak terhubung sebagai koleksi, dan
     * mesin jawaban tidak punya bahan untuk menjawab "knalpot apa saja yang
     * dijual di toko ini".
     *
     * Urutannya mengikuti urutan tampil di halaman, dan `numberOfItems` diisi
     * jumlah yang benar-benar dirender, bukan jumlah seluruh katalog.
     *
     * @param  Collection<int, Product>|LengthAwarePaginator  $daftar
     */
    public static function daftarProduk($daftar, string $nama): array
    {
        $butir = [];
        $posisi = 1;

        foreach ($daftar as $produk) {
            $item = [
                '@type' => 'ListItem',
                'position' => $posisi++,
                'url' => url('/produk/'.$produk->slug),
                'name' => $produk->judul(),
            ];

            $item['item'] = [
                '@type' => 'Product',
                'name' => $produk->judul(),
                'url' => url('/produk/'.$produk->slug),
                'sku' => $produk->sku,
            ];

            $butir[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $nama,
            'numberOfItems' => count($butir),
            'itemListElement' => $butir,
        ];
    }

    /** Identitas toko lengkap, dibangun dari pengaturan yang benar-benar terisi. */
    public static function toko(): array
    {
        /* Kunci cache MEMUAT versi katalog.
         *
         * Sebelumnya kuncinya tetap ('seo:data-terstruktur') dengan umur 30
         * menit, sehingga blok ini tidak ikut batal ketika admin menonaktifkan
         * kategori — `knowsAbout` masih menyebut kategori yang sudah hilang
         * sampai setengah jam kemudian. Sekarang versi ikut di kunci, jadi
         * tandaiBerubah() membatalkannya bersama cache katalog lain. */
        return Cache::remember(
            self::KUNCI.':v'.CacheKatalog::versi(),
            now()->addMinutes(30),
            function (): array {
                $p = app(Pengaturan::class);

                $nama = $p->teks('site_name', 'Bites Exhaust');
                $deskripsi = $p->teks('site_description', '');

                $data = [
                    '@type' => ['Store', 'LocalBusiness'],
                    '@id' => url('/').'#toko',
                    'name' => $nama,
                    'url' => url('/'),
                    'description' => $deskripsi,
                ];

                // Logo: mesin pencari menampilkannya di panel pengetahuan, dan
                // hanya menerima URL absolut. Bila panel belum diisi, logo
                // merek bawaan dipakai supaya data terstruktur tidak kosong.
                $logo = $p->teks('site_logo_light', '') ?: $p->teks('site_logo_dark', '');

                $data['logo'] = str_starts_with($logo, 'http')
                    ? $logo
                    : url(\App\Models\ProductMedia::urlMedia($logo) ?: '/brand/logo-gelap.png');
                $data['image'] = $data['logo'];

                $telepon = $p->teks('phone_number', '');

                if (filled($telepon)) {
                    $data['telephone'] = $telepon;
                }

                $surel = $p->teks('contact_email', '');

                if (filled($surel)) {
                    $data['email'] = $surel;
                }

                $alamat = $p->teks('address_full', '');

                if (filled($alamat)) {
                    $data['address'] = [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $alamat,
                        'addressCountry' => 'ID',
                    ];
                }

                // Koordinat: hanya bila benar-benar diisi. Koordinat 0,0 akan
                // menempatkan toko di Teluk Guinea.
                $lat = (float) $p->teks('maps_lat', '0');
                $lng = (float) $p->teks('maps_lng', '0');

                if ($lat !== 0.0 && $lng !== 0.0) {
                    $data['geo'] = [
                        '@type' => 'GeoCoordinates',
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ];
                }

                $jam = self::jamBuka($p->teks('operational_hours', ''));

                if ($jam !== []) {
                    $data['openingHoursSpecification'] = $jam;
                }

                // Profil sosial yang benar-benar diisi — ini tautan identitas yang
                // dipakai mesin pencari untuk memastikan toko ini entitas yang sama
                // dengan akun Instagram/TikTok-nya.
                $sosial = [];

                foreach ([
                    'social_instagram', 'social_tiktok', 'social_facebook',
                    'social_youtube', 'social_shopee', 'social_tokopedia',
                ] as $kunci) {
                    $tautan = $p->teks($kunci, '');

                    if (filled($tautan) && str_starts_with($tautan, 'http')) {
                        $sosial[] = $tautan;
                    }
                }

                if ($sosial !== []) {
                    $data['sameAs'] = $sosial;
                }

                // Wilayah layanan: toko knalpot menerima kiriman nasional lewat
                // kurir, jadi cakupannya dinyatakan, bukan dibiarkan kosong.
                $data['areaServed'] = [
                    '@type' => 'Country',
                    'name' => 'Indonesia',
                ];

                // Dolar, bukan rupiah: harga yang dilihat pembeli di seluruh
                // situs sudah dolar, dan Google memakai `priceRange` ini untuk
                // potongan harga di hasil pencarian. Mencantumkan rupiah di sini
                // memasang angka yang tidak pernah muncul di halaman mana pun.
                $data['currenciesAccepted'] = HargaDolar::MATA;
                $data['paymentAccepted'] = 'Transfer Bank, Tunai';

                /* ── Penanda untuk mesin jawaban (GEO) ────────────────────────
                 *
                 * `knowsAbout` adalah daftar topik yang benar-benar dikuasai
                 * penjual. Mesin jawaban memakainya untuk memutuskan apakah
                 * halaman ini layak dikutip ketika seseorang bertanya "knalpot
                 * racing untuk NMAX". Tanpa daftar ini, mesin hanya punya nama
                 * toko dan harus menebak dari isi halaman.
                 *
                 * Isinya diambil dari kategori katalog yang AKTIF, bukan ditulis
                 * mati di sini: bila pemilik menambah kategori baru, daftarnya ikut
                 * bertambah tanpa ada yang perlu ingat menyunting berkas ini.
                 *
                 * `priceRange` dipakai Google di panel pengetahuan dan sering
                 * muncul di ringkasan jawaban. Dihitung dari harga varian yang
                 * benar-benar dijual, bukan angka karangan.
                 */
                $topik = CacheKatalog::ingat(
                    'struktur.topik',
                    ['bahasa' => app()->getLocale()],
                    600,
                    fn () => Category::query()->aktif()->terurut()->pluck('name_id')->take(8)->all(),
                );

                if ($topik !== []) {
                    $data['knowsAbout'] = array_values($topik);
                }

                $rentang = CacheKatalog::ingat('struktur.harga', [], 600, function (): ?array {
                    $termurah = ProductVariant::query()
                        ->join('products', 'products.id', '=', 'product_variants.product_id')
                        ->where('products.is_published', true)
                        ->where('product_variants.is_active', true)
                        ->min(DB::raw('products.base_price + product_variants.price_adjustment'));

                    $termahal = ProductVariant::query()
                        ->join('products', 'products.id', '=', 'product_variants.product_id')
                        ->where('products.is_published', true)
                        ->where('product_variants.is_active', true)
                        ->max(DB::raw('products.base_price + product_variants.price_adjustment'));

                    if ($termurah === null || $termahal === null) {
                        return null;
                    }

                    return [(float) $termurah, (float) $termahal];
                });

                if ($rentang !== null) {
                    // Bentuk yang diminta schema.org: "$25.00 – $282.25".
                    // Dua angka, bukan satu, karena pembeli membandingkan
                    // rentang. Nilai rupiah dari kueri di atas diterjemahkan
                    // ke dolar lebih dulu — sama dengan harga di kartu produk.
                    $data['priceRange'] = sprintf(
                        '%s – %s',
                        HargaDolar::format(HargaDolar::dariRupiah($rentang[0])),
                        HargaDolar::format(HargaDolar::dariRupiah($rentang[1])),
                    );
                }

                return $data;
            });
    }

    /**
     * Ubah teks jam operasional bebas menjadi openingHoursSpecification.
     *
     * Format yang dikenali: "Senin - Sabtu, 08.00 - 17.00 WIB".
     * Bila teks tidak dikenali, dikembalikan kosong — lebih baik tidak ada
     * jam buka daripada jam buka yang salah.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function jamBuka(string $teks): array
    {
        if (trim($teks) === '') {
            return [];
        }

        $hari = [
            'senin' => 'Monday', 'selasa' => 'Tuesday', 'rabu' => 'Wednesday',
            'kamis' => 'Thursday', 'jumat' => 'Friday', 'sabtu' => 'Saturday',
            'minggu' => 'Sunday',
        ];

        $rendah = strtolower($teks);

        // Rentang hari: "senin - sabtu"
        preg_match('/(senin|selasa|rabu|kamis|jumat|sabtu|minggu)\s*[-–sampai]+\s*(senin|selasa|rabu|kamis|jumat|sabtu|minggu)/u', $rendah, $rentang);

        // Jam: "08.00 - 17.00" atau "08:00 - 17:00"
        preg_match('/(\d{1,2})[.:](\d{2})\s*[-–]\s*(\d{1,2})[.:](\d{2})/u', $rendah, $waktu);

        if ($waktu === []) {
            return [];
        }

        $buka = str_pad($waktu[1], 2, '0', STR_PAD_LEFT).':'.$waktu[2];
        $tutup = str_pad($waktu[3], 2, '0', STR_PAD_LEFT).':'.$waktu[4];

        if ($rentang === []) {
            return [[
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values($hari),
                'opens' => $buka,
                'closes' => $tutup,
            ]];
        }

        $urut = array_keys($hari);
        $dari = array_search($rentang[1], $urut, true);
        $sampai = array_search($rentang[2], $urut, true);

        if ($dari === false || $sampai === false) {
            return [];
        }

        $daftar = [];

        for ($i = $dari; ; $i = ($i + 1) % 7) {
            $daftar[] = $hari[$urut[$i]];

            if ($i === $sampai || count($daftar) > 7) {
                break;
            }
        }

        return [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => $daftar,
            'opens' => $buka,
            'closes' => $tutup,
        ]];
    }

    /** Jalur navigasi (breadcrumb) untuk halaman produk. */
    public static function remah(Product $produk): array
    {
        $item = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Beranda',
                'item' => route('front.home'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Produk',
                'item' => route('front.products.index'),
            ],
        ];

        $kategori = $produk->category;

        if ($kategori !== null) {
            $item[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $kategori->nama(),
                'item' => route('front.products.index', ['kategori' => $kategori->slug]),
            ];
        }

        $item[] = [
            '@type' => 'ListItem',
            'position' => count($item) + 1,
            'name' => $produk->judul(),
            'item' => route('front.products.show', $produk->slug),
        ];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $item,
        ];
    }

    /** FAQPage: potongan tanya-jawab yang paling sering dikutip mesin jawaban. */
    public static function faq(array $tanyaJawab): array
    {
        $daftar = [];

        foreach ($tanyaJawab as $tanya => $jawab) {
            if (! is_string($tanya) || ! is_string($jawab) || trim($tanya) === '' || trim($jawab) === '') {
                continue;
            }

            $daftar[] = [
                '@type' => 'Question',
                'name' => $tanya,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $jawab,
                ],
            ];
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $daftar,
        ];
    }
}
