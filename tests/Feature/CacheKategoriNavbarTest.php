<?php

namespace Tests\Feature;

use App\Livewire\Admin\Kategori;
use App\Models\Category;
use App\Models\User;
use App\Support\CacheKatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cache kategori navbar.
 *
 * Navbar dirender di SETIAP halaman, dan kategori di dalamnya hanya dipakai
 * menu drawer. Sebelum di-cache, query itu dibayar setiap permintaan walaupun
 * tidak ada yang membuka drawer.
 *
 * Yang diuji bukan "apakah cache dipanggil", melainkan dua janji yang mudah
 * rusak tanpa ketahuan:
 *
 * 1. Permintaan kedua tidak menyentuh tabel categories lagi.
 * 2. Kategori baru tetap muncul — versi katalog naik saat admin menyimpan,
 *    jadi cache lama tidak boleh bertahan dan menyembunyikan kategori baru.
 */
class CacheKategoriNavbarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cache harus dikosongkan sendiri di awal setiap test.
     *
     * RefreshDatabase menyegarkan basis data, tetapi TIDAK menyentuh cache.
     * Tanpa baris ini, entri `Cache::remember` dari test sebelumnya — yang
     * dibuat saat kategori ber-ID lain masih ada — terbaca kembali, dan yang
     * diukur bukan pekerjaan test ini melainkan sisa test lain.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        CacheKatalog::lupakanVersi();
    }

    private function kategori(string $nama, string $slug, int $urutan = 1): Category
    {
        return Category::query()->create([
            'name_id' => $nama,
            'slug' => $slug,
            'display_order' => $urutan,
            'is_active' => true,
        ]);
    }

    /** Kueri yang menyentuh tabel categories selama satu permintaan. */
    private function kueriKategori(string $jalur): int
    {
        $jumlah = 0;

        DB::listen(function ($kueri) use (&$jumlah): void {
            if (str_contains(strtolower($kueri->sql), 'categories')) {
                $jumlah++;
            }
        });

        $this->get($jalur)->assertOk();

        return $jumlah;
    }

    public function test_permintaan_kedua_tidak_membaca_tabel_kategori(): void
    {
        $this->kategori('Knalpot', 'knalpot');

        // Muat pertama mengisi cache (navbar, footer, panel saringan,
        // statistik). Yang diukur adalah biaya yang ditanggung setiap
        // pengunjung berikutnya.
        $this->get('/produk')->assertOk();
        CacheKatalog::lupakanVersi();

        // Muat kedua harus terlayani cache: NOL kueri ke categories.
        $this->assertSame(0, $this->kueriKategori('/produk'));
    }

    public function test_beranda_dan_kontak_juga_nol_kueri_kategori(): void
    {
        $this->kategori('Knalpot', 'knalpot');

        // Panaskan cache untuk kedua halaman lebih dulu.
        foreach (['/', '/kontak', '/tentang'] as $jalur) {
            $this->get($jalur)->assertOk();
            CacheKatalog::lupakanVersi();
        }

        foreach (['/', '/kontak', '/tentang'] as $jalur) {
            $this->assertSame(0, $this->kueriKategori($jalur), "Kueri kategori masih tersisa di {$jalur}");
            CacheKatalog::lupakanVersi();
        }
    }

    public function test_kategori_baru_tetap_muncul_setelah_disimpan_admin(): void
    {
        $this->kategori('Knalpot', 'knalpot');

        // Panaskan cache lebih dulu, supaya kegagalan invalidasi benar-benar
        // terlihat (bukan lolos karena cache belum sempat terisi).
        $this->get('/produk')->assertOk();
        CacheKatalog::lupakanVersi();

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        Livewire::test(Kategori::class)
            ->call('bukaForm')
            ->set('nama_id', 'Header Racing')
            ->set('slug', 'header-racing')
            ->call('simpan')
            ->assertHasNoErrors();

        // Versi katalog sudah naik; render berikutnya harus membaca ulang.
        CacheKatalog::lupakanVersi();

        $this->get('/')->assertOk()->assertSee('Header Racing');
    }

    public function test_kategori_nonaktif_tidak_ikut_tersimpan_di_cache(): void
    {
        $this->kategori('Knalpot', 'knalpot');

        Category::query()->create([
            'name_id' => 'Kategori Rahasia',
            'slug' => 'kategori-rahasia',
            'display_order' => 2,
            'is_active' => false,
        ]);

        $this->get('/produk')->assertOk();
        CacheKatalog::lupakanVersi();

        // Dirender dua kali: yang kedua sepenuhnya dari cache.
        $this->get('/produk')->assertOk()->assertDontSee('Kategori Rahasia');
    }
}
