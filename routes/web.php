<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Http\Controllers\WebhookPaypal;
use App\Http\Middleware\BlokirIp;
use App\Http\Middleware\SetLocale;
use App\Livewire\Front\About;
use App\Livewire\Front\Beranda;
use App\Livewire\Front\Checkout;
use App\Livewire\Front\DetailProduk;
use App\Livewire\Front\HalamanStatis;
use App\Livewire\Front\Kategori;
use App\Livewire\Front\KeranjangHalaman;
use App\Livewire\Front\Kontak;
use App\Livewire\Front\LacakPesanan;
use App\Livewire\Front\PaypalKembali;
use App\Livewire\Front\PesananSukses;
use App\Livewire\Front\ProdukDaftar;
use App\Livewire\Front\UnggahBukti;
use App\Models\Order;
use App\Services\Pembayaran\PaypalGateway;
use App\Support\PetaSitus;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
| Rute sisi depan. Seluruhnya publik. Keranjang berbasis sesi, jadi pembelian
| tidak menuntut pendaftaran akun.
|
| Penggantian bahasa lewat /lang/{locale} yang menulis cookie, bukan lewat
| parameter pada setiap rute, supaya URL tetap bersih.
*/

Route::middleware([SetLocale::class, BlokirIp::class])->group(function (): void {
    Route::get('/', Beranda::class)->name('front.home');

    Route::get('/produk', ProdukDaftar::class)
        ->middleware('laju:search,q')
        ->name('front.products.index');
    Route::get('/produk/{product:slug}', DetailProduk::class)->name('front.products.show');

    /*
    | Halaman kategori.
    |
    | Didaftarkan SESUDAH /produk/{product:slug} agar tidak tertukar: keduanya
    | memakai satu segmen, dan urutan pendaftaran menentukan siapa yang menang.
    | Awalan /kategori/ dipakai supaya tidak ada kemungkinan bentrok sama sekali.
    */
    Route::get('/kategori/{slug}', Kategori::class)->name('front.category');

    /*
    | Halaman unggah bukti transfer.
    |
    | Dibuka tanpa akun karena checkout toko ini tidak mewajibkan akun. Yang
    | menjaga aksesnya adalah ULID pesanan: 26 karakter acak yang tidak dapat
    | ditebak, dan hanya diketahui pembeli dari halaman sukses pesanannya.
    */
    Route::get('/pesanan/{ulid}/bayar', UnggahBukti::class)
        ->middleware('laju:payment_proof')
        ->name('front.payment.upload');

    Route::get('/tentang', About::class)->name('front.about');

    Route::get('/kontak', Kontak::class)->name('front.contact');

    Route::get('/keranjang', KeranjangHalaman::class)->name('front.cart');
    Route::get('/checkout', Checkout::class)->name('front.checkout');

    Route::get('/pesanan/sukses/{order:ulid}', PesananSukses::class)->name('front.order.success');

    /* Kembali dari PayPal.
     *
     * PayPal mengirim pembeli ke sini dengan `?token=<id-pesanan-paypal>`.
     * Halaman ini menangkap dananya, lalu menampilkan hasilnya. Rute terpisah
     * dari halaman sukses supaya pembeli yang membayar lewat PayPal tidak
     * mendarat di halaman yang berbicara tentang transfer bank manual. */
    Route::get('/pesanan/{ulid}/paypal/kembali', PaypalKembali::class)
        ->name('front.paypal.kembali');

    /* Memulai ulang pembayaran PayPal untuk pesanan yang sudah ada.
     *
     * Dipakai tombol "Coba bayar lagi". Yang dibuat ulang hanya pesanan
     * PayPal-nya, BUKAN pesanan toko — membuat pesanan toko baru akan
     * menahan stok dua kali untuk satu pembelian.
     */
    Route::get('/pesanan/{ulid}/paypal/mulai', function (string $ulid) {
        $pesanan = Order::query()
            ->where('ulid', $ulid)
            ->where('payment_method', MetodePembayaran::Paypal)
            ->firstOrFail();

        if ($pesanan->payment_status === StatusPembayaran::Paid) {
            return redirect()->route('front.order.success', $pesanan);
        }

        try {
            $hasil = app(PaypalGateway::class)->buatPesanan($pesanan);

            if (filled($hasil['setuju_url'] ?? null)) {
                return redirect()->away($hasil['setuju_url']);
            }
        } catch (Throwable $e) {
            Log::warning('PayPal gagal memulai ulang', [
                'pesanan' => $pesanan->order_number,
                'galat' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('front.paypal.kembali', ['ulid' => $pesanan->ulid, 'gagal' => 1])
            ->with('galat', 'PayPal belum dapat dihubungi. Coba beberapa saat lagi.');
    })->name('front.paypal.mulai');
    Route::get('/lacak', LacakPesanan::class)->middleware('laju:track_order')->name('front.order.track');

    Route::get('/halaman/{slug}', HalamanStatis::class)->name('front.page');

    /* Webhook PayPal.
     *
     * Didaftarkan DI LUAR grup middleware SetLocale + BlokirIp, dan itu
     * disengaja:
     *
     *   - SetLocale menulis cookie bahasa pada respons. Webhook bukan
     *     kunjungan peramban; cookie yang ditulis di sini tidak berguna.
     *   - BlokirIp menolak alamat yang sedang diblokir. Kalau suatu hari IP
     *     PayPal ikut terblokir, seluruh pemberitahuan pembayaran berhenti
     *     dan pesanan yang sudah dibayar tidak pernah lunas. Webhook
     *     mengamankan dirinya sendiri lewat verifikasi tanda tangan.
     *
     * CSRF dikecualikan di bootstrap/app.php: PayPal tidak mengirim token,
     * dan tanda tangan kriptografis sudah menjadi pengamannya. */
    Route::post('/paypal/webhook', WebhookPaypal::class)
        ->middleware('laju:webhook_paypal')
        ->name('front.paypal.webhook');

    /*
     * Pengganti bahasa DIMATIKAN.
     *
     * Situs ini berbahasa Inggris tunggal: APP_LOCALE=en dan middleware
     * SetLocale tidak lagi membaca cookie. Rute ini dulu menulis cookie
     * `iksan_locale` yang membuat peramban bertahan pada versi Indonesia
     * meski konfigurasi sudah diubah — sumber kebingungan saat rebranding.
     *
     * Dipertahankan sebagai rute yang mengarah ke beranda (bukan 404) supaya
     * tautan lama atau bookmark /lang/id tidak berakhir di halaman galat.
     */
    Route::get('/lang/{locale}', function (): \Illuminate\Http\RedirectResponse {
        return redirect()->route('front.home');
    })->name('front.lang');

    /*
    | SEO/GEO: peta situs dan robots.txt dilayani dari rute, bukan berkas statis.
    |
    | Alasannya bukan selera: daftar produk berubah setiap hari, dan robots.txt
    | yang berupa berkas statis akan berbeda dari sitemap.xml begitu ada produk
    | baru. Keduanya dibangun dari SATU sumber (App\Support\PetaSitus), jadi
    | mustahil keduanya saling bertentangan.
    |
    | Diletakkan di dalam grup yang sama supaya pemblokiran IP tetap berlaku.
    */
    Route::get('/sitemap.xml', function (): Response {
        return response(PetaSitus::xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    })->name('front.sitemap');

    Route::get('/robots.txt', function (): Response {
        return response(PetaSitus::robots(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    })->name('front.robots');

    /* llms.txt — ringkasan situs untuk mesin jawaban (standar GEO).
       Didaftarkan sebagai rute, bukan berkas statis di public/, karena isinya
       mengikuti pengaturan toko dan kategori yang sedang aktif. Berkas statis
       akan basi setiap kali pemilik toko mengubah deskripsi atau menambah
       kategori. */
    Route::get('/llms.txt', function (): Response {
        return response(PetaSitus::llms(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    })->name('front.llms');
});
