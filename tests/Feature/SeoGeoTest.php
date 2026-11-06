<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SEO & GEO — penanda yang membuat halaman terbaca oleh mesin pencari dan
 * mesin jawaban (ChatGPT, Perplexity, AI Overviews).
 *
 * Yang diuji adalah janji yang rusak tanpa suara: halaman tetap tampil normal
 * di peramban walaupun canonical hilang atau JSON-LD tidak ada. Tidak ada
 * pengunjung yang menyadarinya — hanya peringkat yang turun pelan-pelan.
 *
 * Sebelum test ini ada, empat halaman ini memang tidak punya penanda:
 *   - beranda tanpa canonical
 *   - /produk tanpa ItemList
 *   - /tentang dan /kontak tanpa canonical dan tanpa JSON-LD sama sekali
 */
class SeoGeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Data terstruktur toko di-cache 30 menit. Tanpa dibersihkan, test
        // membaca hasil test lain dan mengukur hal yang salah.
        Cache::flush();
    }

    private function isiKatalog(): Product
    {
        $kategori = Category::query()->create([
            'name_id' => 'Full System',
            'slug' => 'full-system',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'SEO-001',
            'title_id' => 'Knalpot Full System NMAX',
            'slug' => 'knalpot-full-system-nmax',
            'base_price' => 4250000,
            'weight_gram' => 3000,
            'is_published' => true,
            'display_order' => 1,
        ]);

        ProductVariant::query()->create([
            'product_id' => $produk->id,
            'sku_variant' => 'SEO-001-A',
            'variant_name_id' => 'Titanium',
            'price_adjustment' => 0,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);

        return $produk;
    }

    /** Seluruh blok JSON-LD pada sebuah halaman, sudah diurai. */
    private function grafik(string $html): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            $cocok,
        );

        $hasil = [];

        foreach ($cocok[1] as $blok) {
            $data = json_decode($blok, true);
            $this->assertNotNull($data, 'JSON-LD tidak dapat diurai: '.substr($blok, 0, 120));
            $hasil[] = $data;
        }

        return $hasil;
    }

    /** Semua @type yang muncul, apa pun bentuk grafiknya. */
    private function tipe(array $grafik): array
    {
        $tipe = [];

        foreach ($grafik as $blok) {
            foreach ($blok['@graph'] ?? [$blok] as $simpul) {
                $t = $simpul['@type'] ?? null;
                $tipe[] = is_array($t) ? implode('/', $t) : (string) $t;
            }
        }

        return $tipe;
    }

    public function test_beranda_punya_canonical(): void
    {
        $this->isiKatalog();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('rel="canonical"', $html);
    }

    public function test_katalog_menyatakan_dirinya_sebagai_daftar_produk(): void
    {
        $this->isiKatalog();

        $html = $this->get('/produk')->assertOk()->getContent();
        $tipe = $this->tipe($this->grafik($html));

        $this->assertContains('ItemList', $tipe, 'Katalog tidak memuat ItemList');

        // Isi daftarnya harus menyebut produk yang benar-benar tampil, bukan
        // daftar kosong yang lolos validasi skema.
        $this->assertStringContainsString('Knalpot Full System NMAX', $html);
    }

    public function test_tentang_dan_kontak_punya_canonical_dan_entitas_toko(): void
    {
        $this->isiKatalog();

        foreach (['/tentang' => 'front.about', '/kontak' => 'front.contact'] as $jalur => $rute) {
            $html = $this->get($jalur)->assertOk()->getContent();

            $this->assertStringContainsString(
                'rel="canonical"',
                $html,
                "{$jalur} tidak punya canonical",
            );

            $this->assertContains(
                'Store/LocalBusiness',
                $this->tipe($this->grafik($html)),
                "{$jalur} tidak memuat entitas toko",
            );

            $this->assertStringContainsString('name="ai-summary"', $html, "{$jalur} tanpa ai-summary");
        }
    }

    public function test_toko_menyatakan_topik_dan_rentang_harga(): void
    {
        $this->isiKatalog();

        $grafik = $this->grafik($this->get('/tentang')->assertOk()->getContent());

        $toko = null;

        foreach ($grafik as $blok) {
            foreach ($blok['@graph'] ?? [] as $simpul) {
                if (in_array('Store', (array) ($simpul['@type'] ?? []), true)) {
                    $toko = $simpul;
                }
            }
        }

        $this->assertNotNull($toko, 'Entitas toko tidak ditemukan');
        $this->assertSame(['Full System'], $toko['knowsAbout'] ?? null);
        $this->assertStringContainsString('Rp', (string) ($toko['priceRange'] ?? ''));
    }

    public function test_llms_txt_menyebut_toko_dan_kategori(): void
    {
        $this->isiKatalog();

        $isi = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringContainsString('# ', $isi);
        $this->assertStringContainsString('Full System', $isi);
        $this->assertStringContainsString('/produk', $isi);
    }

    public function test_robots_menunjuk_sitemap_dan_llms(): void
    {
        $isi = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Sitemap:', $isi);
        $this->assertStringContainsString('llms.txt', $isi);
    }

    public function test_judul_produk_berisi_karakter_penutup_tag_tidak_merusak_halaman(): void
    {
        $produk = $this->isiKatalog();

        // Judul yang memuat "</script>" adalah vektor paling sederhana untuk
        // mematahkan blok JSON-LD dan menyuntikkan markup ke halaman.
        $produk->update(['title_id' => 'Knalpot </script><img src=x onerror=alert(1)>']);

        $html = $this->get('/produk')->assertOk()->getContent();

        /* Yang diuji BUKAN "string itu tidak muncul" — di dalam JSON-LD ia
           memang muncul, dan itu sah. Yang menentukan adalah apakah tag
           <script> bisa ditutup lebih awal olehnya.

           Karena itu: setiap blok JSON-LD harus tetap utuh (dapat diurai), dan
           kemunculan "</script" mentah di dalam data harus sudah dilolosikan
           menjadi "<\/script". */
        $grafik = $this->grafik($html);
        $this->assertNotEmpty($grafik, 'JSON-LD rusak oleh karakter penutup tag');

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $cocok);

        foreach ($cocok[1] as $blok) {
            $this->assertStringNotContainsString(
                '</script',
                $blok,
                'Karakter penutup tag tidak dilolosikan di dalam JSON-LD',
            );
        }

        // Tag disuntikkan lewat judul tidak boleh menjadi markup hidup di
        // bagian halaman yang dibaca peramban — hanya boleh muncul sebagai
        // teks di dalam JSON.
        $tanpaJson = preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $tanpaJson);
    }
}
