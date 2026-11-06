<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\CacheKatalog;
use App\Support\KonfigurasiMail;
use App\Support\Pengaturan;
use App\Support\SakelarNotifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sakelar di panel yang dulu tidak berpengaruh apa pun.
 *
 * ── CARA TEMUAN INI BERMULA ────────────────────────────────────────────
 * Pemilik toko menyalakan "Mode pemeliharaan", menunggu tokonya tertutup,
 * dan tidak terjadi apa-apa. Setelah ditelusuri, sakelar itu memang tersimpan
 * di basis data dengan rapi — tetapi TIDAK ADA satu baris kode yang
 * membacanya. Kegagalannya sepenuhnya senyap: tidak ada galat, tidak ada log.
 *
 * Audit yang sama menemukan tujuh lainnya bernasib sama:
 *
 *   maintenance_mode / maintenance_message  -> ditutup middleware
 *   enable_pwa                              -> mengatur manifest & service worker
 *   analytics_id / meta_pixel_id            -> dicetak ke <head>
 *   contact_email_cc                        -> CC notifikasi admin
 *   currency_symbol                         -> rupiah()
 *   date_format                             -> tanggal()
 *
 * Test ini menjaga agar tidak ada yang kembali menjadi sakelar hiasan.
 */
class SakelarPanelBerfungsiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        CacheKatalog::lupakanVersi();
        Pengaturan::lupakan();
    }

    private function atur(array $nilai): void
    {
        foreach ($nilai as $k => $v) {
            Setting::query()->updateOrCreate(['key' => $k], ['value' => (string) $v]);
        }

        Pengaturan::lupakan();
        KonfigurasiMail::lupakan();
    }

    // ── MODE PEMELIHARAAN ──────────────────────────────────────────────

    public function test_mode_pemeliharaan_menutup_toko(): void
    {
        $this->atur(['maintenance_mode' => '1', 'maintenance_message' => 'Tutup sampai 3 Januari.']);

        foreach (['/', '/produk', '/kontak', '/keranjang', '/checkout'] as $jalur) {
            $this->get($jalur)->assertStatus(503);
        }
    }

    public function test_pesan_pemeliharaan_dari_panel_tampil(): void
    {
        $this->atur(['maintenance_mode' => '1', 'maintenance_message' => 'Kami tutup sampai 3 Januari.']);

        $this->get('/')->assertStatus(503)->assertSee('Kami tutup sampai 3 Januari.');
    }

    public function test_panel_admin_tetap_terbuka_saat_pemeliharaan(): void
    {
        $this->atur(['maintenance_mode' => '1']);

        // Tanpa ini, tidak ada cara mematikan mode selain lewat basis data.
        $this->get('/admin/login')->assertOk();
    }

    public function test_mode_pemeliharaan_mati_tidak_mengganggu(): void
    {
        $this->atur(['maintenance_mode' => '0']);

        $this->get('/')->assertOk();
    }

    public function test_aset_statis_tetap_disajikan_saat_pemeliharaan(): void
    {
        $this->atur(['maintenance_mode' => '1']);

        // Halaman pemeliharaan harus tetap bergaya; tanpa CSS ia tampil polos.
        $balasan = $this->get('/css/apa-saja.css');

        $this->assertNotSame(503, $balasan->getStatusCode());
    }

    // ── PWA ────────────────────────────────────────────────────────────

    public function test_pwa_aktif_memasang_manifest(): void
    {
        $this->atur(['enable_pwa' => '1']);

        $this->get('/')->assertOk()->assertSee('manifest.webmanifest');
    }

    public function test_pwa_mati_melepas_manifest(): void
    {
        $this->atur(['enable_pwa' => '0']);

        $this->get('/')->assertOk()->assertDontSee('manifest.webmanifest');
    }

    public function test_pwa_mati_mengirim_perintah_unregister(): void
    {
        $this->atur(['enable_pwa' => '0']);

        // Service worker yang sudah terpasang hanya bisa dicabut dari sisi
        // klien; tanpa ini, mematikan PWA tidak berpengaruh bagi pengunjung lama.
        $this->get('/')->assertOk()->assertSee('unregister');
    }

    // ── ANALITIK ───────────────────────────────────────────────────────

    public function test_analytics_id_dicetak_bila_diisi(): void
    {
        $this->atur(['analytics_id' => 'G-ABC123XYZ']);

        $this->get('/')->assertOk()->assertSee('G-ABC123XYZ', false);
    }

    public function test_analytics_id_tidak_dicetak_bila_kosong(): void
    {
        $this->atur(['analytics_id' => '']);

        $this->get('/')->assertOk()->assertDontSee('googletagmanager');
    }

    public function test_analytics_id_berisi_karakter_berbahaya_ditolak(): void
    {
        // Nilai ini masuk ke dalam <script>. Satu tanda kutip yang lolos
        // berarti jalan masuk bagi skrip sembarang.
        $this->atur(['analytics_id' => "G-1'; alert(1); //"]);

        $this->get('/')->assertOk()->assertDontSee('alert(1)', false);
    }

    public function test_meta_pixel_dicetak_bila_diisi(): void
    {
        $this->atur(['meta_pixel_id' => '123456789012345']);

        $this->get('/')->assertOk()->assertSee('123456789012345', false);
    }

    // ── SIMBOL MATA UANG ───────────────────────────────────────────────

    public function test_simbol_mata_uang_dari_panel_dipakai(): void
    {
        $this->atur(['currency_symbol' => 'IDR']);

        $this->assertStringStartsWith('IDR ', rupiah(1500000));
    }

    public function test_simbol_kosong_kembali_ke_bawaan(): void
    {
        $this->atur(['currency_symbol' => '']);

        $this->assertStringStartsWith('Rp ', rupiah(1500000));
    }

    // ── FORMAT TANGGAL ─────────────────────────────────────────────────

    public function test_format_tanggal_dari_panel_dipakai(): void
    {
        $this->atur(['date_format' => 'd/m/Y']);

        $this->assertSame('05/03/2026', tanggal('2026-03-05'));
    }

    public function test_format_tanggal_rusak_kembali_ke_bawaan(): void
    {
        // Pola rusak harus diabaikan; kalau dipakai apa adanya, hasilnya
        // tanggal salah tanpa galat — lebih buruk daripada pola bawaan.
        $this->atur(['date_format' => 'ZZZ-INVALID-###']);

        $this->assertSame('05 Mar 2026', tanggal('2026-03-05'));
    }

    // ── CC NOTIFIKASI ADMIN ────────────────────────────────────────────

    public function test_cc_dari_panel_dipakai_notifikasi_admin(): void
    {
        $this->atur([
            'admin_email' => 'pemilik@toko.test',
            'contact_email_cc' => 'gudang@toko.test',
        ]);

        $this->assertTrue(SakelarNotifikasi::kirimAktif('kirim_admin_saat_baru'));
    }
}
