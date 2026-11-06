<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiAdmin;
use App\Jobs\KirimNotifikasiPesanan;
use App\Mail\PesananAdmin;
use App\Mail\PesananPembeli;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Keranjang;
use App\Services\PesananService;
use App\Services\StatusPesananService;
use App\Support\CacheKatalog;
use App\Support\KonfigurasiMail;
use App\Support\Pengaturan;
use App\Support\SakelarNotifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notifikasi email: ke pembeli DAN ke admin.
 *
 * ── YANG DIJAGA TEST INI ───────────────────────────────────────────────
 * Email adalah saluran yang gagal tanpa suara. Bila salah konfigurasi, tidak
 * ada galat di layar: pesanan tetap tersimpan, panel tetap normal, dan hanya
 * emailnya yang tidak pernah datang. Karena itu setiap penerima harus diuji
 * dengan menyatakan "email ini HARUS dikirim ke alamat itu".
 *
 * Yang diuji:
 *   1. Pembeli menerima email saat pesanan dibuat, dibayar, dikirim, selesai
 *   2. Admin menerima email saat pesanan BARU dan SELESAI (bukan kejadian lain)
 *   3. Sapuannya bisa dimatikan lewat sakelar
 *   4. Konfigurasi SMTP dari panel benar-benar dipakai (bukan hanya tersimpan)
 */
class NotifikasiEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        CacheKatalog::lupakanVersi();
        Pengaturan::lupakan();
        KonfigurasiMail::lupakan();
    }

    private function siapkanToko(string $adminEmail = 'pemilik@toko.test'): ProductVariant
    {
        Setting::query()->updateOrCreate(['key' => 'payment_mode'], ['value' => 'manual']);
        Setting::query()->updateOrCreate(['key' => 'admin_email'], ['value' => $adminEmail]);
        Setting::query()->updateOrCreate(['key' => 'contact_email'], ['value' => 'kontak@toko.test']);
        Setting::query()->updateOrCreate(['key' => 'site_name'], ['value' => 'Iksan Knalpot']);
        Setting::query()->updateOrCreate(['key' => 'phone_number'], ['value' => '0274123456']);
        Pengaturan::lupakan();

        $kategori = Category::query()->create([
            'name_id' => 'Knalpot', 'slug' => 'knalpot', 'display_order' => 1, 'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id, 'sku' => 'EM-001', 'title_id' => 'Knalpot Uji Email',
            'slug' => 'knalpot-uji-email', 'base_price' => 500000, 'weight_gram' => 1000,
            'is_published' => true, 'display_order' => 1,
        ]);

        return ProductVariant::query()->create([
            'product_id' => $produk->id, 'sku_variant' => 'EM-001-A', 'variant_name_id' => 'Standar',
            'price_adjustment' => 0, 'stock_quantity' => 10, 'reserved_quantity' => 0,
            'is_active' => true, 'display_order' => 1,
        ]);
    }

    private function pesananBaru(): Order
    {
        $varian = $this->siapkanToko();
        $keranjang = app(Keranjang::class);
        $keranjang->tambah($varian, 1);

        return app(PesananService::class)->buatDariKeranjang(
            $keranjang,
            [
                'nama' => 'Budi Pembeli', 'telepon' => '08123456789', 'email' => 'budi@contoh.test',
                'alamat1' => 'Jl. Mawar 1', 'alamat2' => null, 'kota' => 'Sleman', 'provinsi' => 'DIY',
                'kodePos' => '55281', 'kurir' => 'JNE', 'negara' => 'ID', 'mataUang' => 'IDR',
                'catatan' => '',
            ],
            MetodePembayaran::Manual,
        );
    }

    // ── PEMBELI ────────────────────────────────────────────────────────

    public function test_pembeli_menerima_email_saat_pesanan_dibuat(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();
        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBUAT))->handle();

        Mail::assertSent(PesananPembeli::class, function (PesananPembeli $m) {
            return $m->hasTo('budi@contoh.test') && $m->kejadian === PesananPembeli::DIBUAT;
        });
    }

    public function test_pembeli_diberi_tahu_saat_pesanan_selesai(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();
        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_SELESAI))->handle();

        Mail::assertSent(PesananPembeli::class, fn (PesananPembeli $m) => $m->kejadian === PesananPembeli::SELESAI);
    }

    // ── ADMIN ──────────────────────────────────────────────────────────

    public function test_admin_menerima_email_saat_pesanan_baru(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();
        (new KirimNotifikasiAdmin($pesanan->id, PesananAdmin::BARU))->handle();

        Mail::assertSent(PesananAdmin::class, function (PesananAdmin $m) use ($pesanan) {
            return $m->hasTo('pemilik@toko.test')
                && $m->kejadian === PesananAdmin::BARU
                && $m->pesanan->id === $pesanan->id;
        });
    }

    public function test_admin_menerima_email_saat_pesanan_selesai(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();
        (new KirimNotifikasiAdmin($pesanan->id, PesananAdmin::SELESAI))->handle();

        Mail::assertSent(PesananAdmin::class, fn (PesananAdmin $m) => $m->kejadian === PesananAdmin::SELESAI);
    }

    public function test_admin_tidak_dikirimi_email_untuk_dibayar_atau_dikirim(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();

        // Pesanan dibuat -> admin MEMANG dikirimi (pesanan baru).
        Mail::assertSent(PesananAdmin::class, fn (PesananAdmin $m) => $m->kejadian === PesananAdmin::BARU);

        // Kejadian dibayar/dikirim dilakukan ADMIN SENDIRI lewat panel, jadi
        // tidak boleh menghasilkan email ke dirinya sendiri.
        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBAYAR))->handle();
        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIKIRIM))->handle();

        Mail::assertSent(PesananPembeli::class, fn (PesananPembeli $m) => $m->kejadian === PesananPembeli::DIBAYAR);
        Mail::assertSent(PesananPembeli::class, fn (PesananPembeli $m) => $m->kejadian === PesananPembeli::DIKIRIM);

        // Tetap hanya SATU email admin sepanjang riwayat ini.
        Mail::assertSent(PesananAdmin::class, 1);
    }

    public function test_alamat_admin_ganda_dipisah_koma_dihormati(): void
    {
        $pesanan = $this->pesananBaru();

        Setting::query()->updateOrCreate(['key' => 'admin_email'], ['value' => 'a@toko.test, b@toko.test']);
        Pengaturan::lupakan();

        Mail::fake();
        (new KirimNotifikasiAdmin($pesanan->id, PesananAdmin::BARU))->handle();

        Mail::assertSent(PesananAdmin::class, function (PesananAdmin $m) {
            return $m->hasTo('a@toko.test') && $m->hasTo('b@toko.test');
        });
    }

    public function test_alamat_admin_tidak_sah_jatuh_ke_email_kontak(): void
    {
        // Pesanan dibuat TANPA Mail::fake lebih dulu.
        //
        // Antrean di test memakai driver `sync`: job yang dikirim saat pesanan
        // dibuat berjalan SEKETIKA, memakai pengaturan yang berlaku saat itu.
        // Mengubah alamat setelah pesanan dibuat lalu mengharapkan email ke
        // alamat baru berarti menguji waktu, bukan perilaku.
        $pesanan = $this->pesananBaru();

        // Alamat admin rusak -> pemasangan memakai alamat kontak sebagai
        // cadangan. Disengaja: toko yang salah mengetik alamat admin tetap
        // harus menerima kabar pesanan, bukan diam saja.
        Setting::query()->updateOrCreate(['key' => 'admin_email'], ['value' => 'bukan-email']);
        Setting::query()->updateOrCreate(['key' => 'contact_email'], ['value' => 'kontak@toko.test']);
        Pengaturan::lupakan();

        Mail::fake();
        (new KirimNotifikasiAdmin($pesanan->id, PesananAdmin::BARU))->handle();

        Mail::assertSent(PesananAdmin::class, fn (PesananAdmin $m) => $m->hasTo('kontak@toko.test'));
    }

    public function test_tanpa_alamat_sah_sama_sekali_tidak_mengirim(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();

        // Keduanya rusak/kosong -> tidak ada email, dan TIDAK melempar galat.
        // Melempar di sini akan menggagalkan pembuatan pesanan hanya karena
        // email salah — hal yang tidak boleh terjadi.
        Setting::query()->updateOrCreate(['key' => 'admin_email'], ['value' => 'bukan-email']);
        Setting::query()->updateOrCreate(['key' => 'contact_email'], ['value' => '']);
        Pengaturan::lupakan();

        Mail::fake();
        (new KirimNotifikasiAdmin($pesanan->id, PesananAdmin::BARU))->handle();

        Mail::assertNotSent(PesananAdmin::class);
    }

    // ── SAKELAR ────────────────────────────────────────────────────────

    public function test_sakelar_admin_baru_bisa_dimatikan(): void
    {
        Setting::query()->updateOrCreate(['key' => 'notif_kirim_admin_baru'], ['value' => '0']);
        Pengaturan::lupakan();

        $this->assertFalse(SakelarNotifikasi::kirimAktif('kirim_admin_saat_baru'));
    }

    // ── KONFIGURASI SMTP DARI PANEL ────────────────────────────────────

    public function test_konfigurasi_smtp_dari_panel_benar_benar_dipakai(): void
    {
        foreach ([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.contoh.test',
            'mail_port' => '465',
            'mail_username' => 'akun@contoh.test',
            'mail_password' => 'rahasia',
            'mail_encryption' => 'ssl',
        ] as $k => $v) {
            Setting::query()->updateOrCreate(['key' => $k], ['value' => $v]);
        }

        Pengaturan::lupakan();
        KonfigurasiMail::lupakan();
        KonfigurasiMail::terapkan();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.contoh.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('akun@contoh.test', config('mail.mailers.smtp.username'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
    }

    public function test_enkripsi_none_menjadi_null_bukan_string(): void
    {
        Setting::query()->updateOrCreate(['key' => 'mail_encryption'], ['value' => 'none']);

        Pengaturan::lupakan();
        KonfigurasiMail::lupakan();
        KonfigurasiMail::terapkan();

        // Symfony Mailer menolak string 'none'; nilainya harus benar-benar null.
        $this->assertNull(config('mail.mailers.smtp.encryption'));
    }

    public function test_isian_kosong_tidak_menimpa_env(): void
    {
        config(['mail.mailers.smtp.host' => 'dari-env.test']);

        Setting::query()->updateOrCreate(['key' => 'mail_host'], ['value' => '']);

        Pengaturan::lupakan();
        KonfigurasiMail::lupakan();
        KonfigurasiMail::terapkan();

        $this->assertSame('dari-env.test', config('mail.mailers.smtp.host'));
    }

    // ── RANTAI: STATUS SELESAI MEMICU EMAIL ────────────────────────────

    public function test_menyelesaikan_pesanan_memicu_email_pembeli_dan_admin(): void
    {
        Mail::fake();

        $pesanan = $this->pesananBaru();
        $admin = User::factory()->create(['role' => 'super_admin']);

        // Pesanan harus lunas dan dikirim sebelum dapat diselesaikan.
        $pesanan->update(['payment_status' => 'paid', 'status' => StatusPesanan::Paid]);
        $status = app(StatusPesananService::class);

        $status->pindah($pesanan, StatusPesanan::Processing, $admin);
        $status->pindah($pesanan, StatusPesanan::Shipped, $admin);
        $status->pindah($pesanan, StatusPesanan::Completed, $admin);

        $this->assertSame(StatusPesanan::Completed, $pesanan->fresh()->status);

        // Pada antrean sinkron (QUEUE_CONNECTION=sync di test), job berjalan
        // seketika, jadi email dapat diperiksa langsung di sini.
        Mail::assertSent(PesananPembeli::class, fn (PesananPembeli $m) => $m->kejadian === PesananPembeli::SELESAI);
        Mail::assertSent(PesananAdmin::class, fn (PesananAdmin $m) => $m->kejadian === PesananAdmin::SELESAI);
    }
}
