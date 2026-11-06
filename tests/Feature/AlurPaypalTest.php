<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Livewire\Front\PaypalKembali;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Keranjang;
use App\Services\Pembayaran\PaypalGateway;
use App\Services\PesananService;
use App\Support\CacheKatalog;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * RANTAI PayPal dari ujung ke ujung.
 *
 * ── KENAPA TEST INI ADA ────────────────────────────────────────────────
 * Sebelumnya ada 9 test PayPal dan SEMUANYA lolos. Tetapi semuanya menguji
 * `PaypalGateway` secara langsung — tidak satu pun menguji bahwa pembeli
 * benar-benar bisa membayar.
 *
 * Keadaan sebenarnya waktu itu: checkout mencatat `payment_method = paypal`
 * lalu langsung melepas pembeli ke halaman sukses, tanpa satu pun panggilan
 * ke PayPal. Pembeli buntu, uang tidak pernah berpindah, dan tidak ada test
 * yang menangkapnya.
 *
 * Test di bawah menguji RANTAI-nya, bukan potongannya:
 *
 *   checkout → buat pesanan PayPal → pembeli diarahkan ke PayPal
 *            → kembali → dana ditangkap → pesanan lunas → tercatat
 *            → webhook → idempoten
 */
class AlurPaypalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cache harus dikosongkan sendiri di awal setiap test.
     *
     * RefreshDatabase menyegarkan basis data, tetapi TIDAK menyentuh cache
     * maupun memo statis `Pengaturan`. Tanpa baris di bawah, test kedua dan
     * seterusnya membaca pengaturan dari test sebelumnya — termasuk kredensial
     * PayPal yang sudah tidak ada di baris basis data yang baru.
     *
     * Gejalanya sempat membingungkan: test lolos saat dijalankan sendiri,
     * tetapi gagal 500 saat seluruh kelas dijalankan ("Kredensial PayPal belum
     * diisi"). Itu bukan bug kode, melainkan state yang bocor antar-test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        CacheKatalog::lupakanVersi();
        Pengaturan::lupakan();
    }

    private function siapkanToko(): ProductVariant
    {
        Cache::flush();
        CacheKatalog::lupakanVersi();
        Pengaturan::lupakan();

        Setting::query()->updateOrCreate(['key' => 'payment_mode'], ['value' => 'both']);
        Setting::query()->updateOrCreate(['key' => 'paypal_client_id'], ['value' => 'SANDBOX-CLIENT']);
        Setting::query()->updateOrCreate(['key' => 'paypal_client_secret'], ['value' => 'SANDBOX-SECRET']);
        Setting::query()->updateOrCreate(['key' => 'paypal_mode'], ['value' => 'sandbox']);
        Setting::query()->updateOrCreate(['key' => 'paypal_currency'], ['value' => 'IDR']);
        Setting::query()->updateOrCreate(['key' => 'paypal_webhook_id'], ['value' => 'WH-UJI-123']);

        Pengaturan::lupakan();

        $kategori = Category::query()->create([
            'name_id' => 'Knalpot', 'slug' => 'knalpot', 'display_order' => 1, 'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id, 'sku' => 'PP-001', 'title_id' => 'Knalpot Uji PayPal',
            'slug' => 'knalpot-uji-paypal', 'base_price' => 1000000, 'weight_gram' => 2000,
            'is_published' => true, 'display_order' => 1,
        ]);

        return ProductVariant::query()->create([
            'product_id' => $produk->id, 'sku_variant' => 'PP-001-A', 'variant_name_id' => 'Standar',
            'price_adjustment' => 0, 'stock_quantity' => 10, 'reserved_quantity' => 0,
            'is_active' => true, 'display_order' => 1,
        ]);
    }

    /** Jawaban palsu untuk seluruh panggilan PayPal. */
    private function palsukanPaypal(array $tambahan = []): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN-UJI', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1'],
                ],
            ], 201),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-1',
                        'amount' => ['value' => '1000000.00', 'currency_code' => 'IDR'],
                    ]]],
                ]],
            ], 201),
            '*/v1/notifications/verify-webhook-signature' => Http::response(
                ['verification_status' => 'SUCCESS'], 200,
            ),
            ...$tambahan,
        ]);
    }

    private function pesananPaypal(): Order
    {
        $varian = $this->siapkanToko();

        $keranjang = app(Keranjang::class);
        $keranjang->tambah($varian, 1);

        return app(PesananService::class)->buatDariKeranjang(
            $keranjang,
            [
                'nama' => 'Uji PayPal', 'telepon' => '08123456789', 'email' => 'uji@paypal.test',
                'alamat1' => 'Jl. Uji 1', 'alamat2' => null, 'kota' => 'Sleman', 'provinsi' => 'DIY',
                'kodePos' => '55281', 'kurir' => 'JNE', 'negara' => 'ID', 'mataUang' => 'IDR',
                'catatan' => '',
            ],
            MetodePembayaran::Paypal,
        );
    }

    // ── RANTAI UTAMA ───────────────────────────────────────────────────

    public function test_checkout_paypal_benar_benar_memanggil_paypal(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        $hasil = app(PaypalGateway::class)->buatPesanan($pesanan);

        $this->assertSame('PAYPAL-ORDER-1', $hasil['id']);
        $this->assertSame(
            'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1',
            $hasil['setuju_url'],
        );

        // Bukti bahwa permintaan benar-benar dikirim ke PayPal.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v2/checkout/orders')
            && $r['purchase_units'][0]['amount']['value'] === '1000000.00');
    }

    public function test_pembeli_yang_kembali_dari_paypal_menjadi_lunas(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        $this->assertSame(StatusPembayaran::Unpaid, $pesanan->payment_status);

        // Pembeli kembali dengan ?token=<id pesanan PayPal>.
        Livewire::withQueryParams(['token' => 'PAYPAL-ORDER-1'])
            ->test(PaypalKembali::class, ['ulid' => $pesanan->ulid])
            ->assertSet('keadaan', 'lunas');

        $pesanan->refresh();
        $this->assertSame(StatusPembayaran::Paid, $pesanan->payment_status);

        $this->assertSame(1, Payment::query()->where('order_id', $pesanan->id)->count());
        $this->assertSame('CAPTURE-1', Payment::query()->where('order_id', $pesanan->id)->value('provider_transaction_id'));
    }

    public function test_membuka_halaman_kembali_dua_kali_tidak_menangkap_dua_kali(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        foreach ([1, 2] as $_) {
            Livewire::withQueryParams(['token' => 'PAYPAL-ORDER-1'])
                ->test(PaypalKembali::class, ['ulid' => $pesanan->ulid]);
        }

        $this->assertSame(1, Payment::query()->where('order_id', $pesanan->id)->count());
    }

    // ── WEBHOOK ────────────────────────────────────────────────────────

    public function test_webhook_sah_melunasi_pesanan_tanpa_halaman_kembali(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        // Pembeli menutup peramban setelah menyetujui: kasus yang hanya bisa
        // ditutup oleh webhook.
        $balasan = $this->postJson('/paypal/webhook', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-9',
                'amount' => ['value' => '1000000.00', 'currency_code' => 'IDR'],
                'purchase_units' => [['reference_id' => $pesanan->order_number]],
            ],
        ], [
            'PAYPAL-TRANSMISSION-ID' => 'TRANS-1',
            'PAYPAL-TRANSMISSION-TIME' => '2026-10-31T00:00:00Z',
            'PAYPAL-TRANSMISSION-SIG' => 'SIG-1',
            'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert.pem',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
        ]);

        $balasan->assertOk()->assertJson(['tanda_tangan_sah' => true]);

        $this->assertSame(StatusPembayaran::Paid, $pesanan->fresh()->payment_status);
    }

    public function test_webhook_tanpa_header_paypal_ditolak(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        // Tanpa header PAYPAL-TRANSMISSION-*, tidak ada yang bisa diverifikasi.
        $this->postJson('/paypal/webhook', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-PALSU',
                'purchase_units' => [['reference_id' => $pesanan->order_number]],
            ],
        ])->assertOk()->assertJson(['tanda_tangan_sah' => false]);

        $this->assertSame(StatusPembayaran::Unpaid, $pesanan->fresh()->payment_status);
        $this->assertSame(0, Payment::query()->where('order_id', $pesanan->id)->count());
    }

    public function test_webhook_tidak_butuh_csrf(): void
    {
        $this->palsukanPaypal();

        // Tanpa pengecualian CSRF, setiap pemberitahuan PayPal dijawab 419 dan
        // uang yang masuk tidak pernah tercatat.
        $this->postJson('/paypal/webhook', ['event_type' => 'CHECKOUT.ORDER.APPROVED'], [
            'PAYPAL-TRANSMISSION-ID' => 'T',
            'PAYPAL-TRANSMISSION-TIME' => '2026-10-31T00:00:00Z',
            'PAYPAL-TRANSMISSION-SIG' => 'S',
            'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert.pem',
        ])->assertOk();
    }

    // ── TOMBOL BAYAR ULANG ─────────────────────────────────────────────

    public function test_halaman_sukses_menampilkan_tombol_bayar_untuk_pesanan_belum_lunas(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        $this->get(route('front.order.success', $pesanan))
            ->assertOk()
            ->assertSee('Bayar dengan PayPal');
    }

    public function test_memulai_ulang_pembayaran_mengarahkan_ke_paypal(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();

        $this->get(route('front.paypal.mulai', $pesanan))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1');
    }

    public function test_pesanan_lunas_tidak_bisa_dibayar_ulang(): void
    {
        $this->palsukanPaypal();
        $pesanan = $this->pesananPaypal();
        $pesanan->update(['payment_status' => StatusPembayaran::Paid]);

        $this->get(route('front.paypal.mulai', $pesanan))
            ->assertRedirect(route('front.order.success', $pesanan));
    }
}
