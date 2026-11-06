<?php

namespace App\Livewire\Front;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Order;
use App\Services\Pembayaran\PaypalGateway;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Halaman kembali dari PayPal.
 *
 * Alurnya: checkout → PayPal (pembeli menyetujui) → SINI.
 *
 * ── KENAPA PENANGKAPAN DI SINI, BUKAN DI WEBHOOK ──────────────────────
 * Dua jalur menuju status "lunas", dan keduanya diperlukan:
 *
 *   1. Halaman ini: pembeli kembali dari PayPal, kita menangkap dananya
 *      (`/capture`). Pembeli langsung melihat pesanannya lunas — tidak perlu
 *      menunggu apa pun.
 *   2. Webhook: PayPal memberi tahu kita secara mandiri. Ini yang menutup
 *      kasus pembeli menutup peramban setelah menyetujui, sehingga tidak
 *      pernah kembali ke sini.
 *
 * Keduanya memakai jalur pencatatan yang sama (`catatPembayaran`) yang sudah
 * idempoten — membayar dua kali tidak menghasilkan dua baris pembayaran.
 *
 * ── `token` DI SINI ADALAH ID PESANAN PAYPAL ──────────────────────────
 * PayPal mengembalikan `?token=<id-pesanan-paypal>&PayerID=...`. Token itu
 * BUKAN identitas pesanan kita, dan tidak boleh dipercaya sebagai bukti
 * lunas: isinya hanya menunjuk pesanan PayPal mana yang harus ditanyakan
 * statusnya. Yang menentukan lunas adalah jawaban PayPal atas penangkapan.
 */
#[Layout('layouts.front')]
class PaypalKembali extends Component
{
    /** ULID pesanan toko. */
    public string $ulid = '';

    /** Id pesanan PayPal dari query string (`token`). */
    public string $token = '';

    public string $keadaan = 'memproses'; // memproses | lunas | gagal

    public string $pesan = '';

    public function mount(string $ulid): void
    {
        $this->ulid = $ulid;
        $this->token = (string) request()->query('token', '');

        $pesanan = $this->pesanan();

        if ($pesanan === null) {
            $this->keadaan = 'gagal';
            $this->pesan = 'Pesanan tidak ditemukan.';

            return;
        }

        // Sudah lunas (mis. pembeli menyegarkan halaman): jangan tangkap dua
        // kali. PayPal menolak penangkapan kedua, dan pesannya akan
        // membingungkan pembeli yang sebenarnya sudah berhasil.
        if ($pesanan->payment_status === StatusPembayaran::Paid) {
            $this->keadaan = 'lunas';
            $this->pesan = 'Pembayaran sudah tercatat. Terima kasih.';

            return;
        }

        if ($this->token === '') {
            $this->keadaan = 'gagal';
            $this->pesan = 'PayPal tidak mengirim kode pesanan. Pembayaran tidak dapat dilanjutkan.';

            return;
        }

        try {
            app(PaypalGateway::class)->tangkap($pesanan, $this->token);

            CatatanAktivitas::catat('paypal.ditangkap', [
                'pesanan' => $pesanan->order_number,
                'paypal_id' => $this->token,
            ]);

            $this->keadaan = 'lunas';
            $this->pesan = 'Pembayaran berhasil. Pesanan Anda sedang kami proses.';
        } catch (Throwable $e) {
            /* Gagal menangkap TIDAK berarti pembayaran gagal.
             *
             * Penyebab paling sering: dana sudah ditangkap lebih dulu oleh
             * webhook, atau pembeli menekan "kembali" dua kali. Pesanan tetap
             * diperiksa ulang, dan bila ternyata sudah lunas, pembeli diberi
             * kabar baik — bukan pesan galat yang menakutkan. */
            Log::warning('Penangkapan PayPal gagal', [
                'pesanan' => $pesanan->order_number,
                'galat' => $e->getMessage(),
            ]);

            $pesanan->refresh();

            if ($pesanan->payment_status === StatusPembayaran::Paid) {
                $this->keadaan = 'lunas';
                $this->pesan = 'Pembayaran sudah tercatat. Terima kasih.';
            } else {
                $this->keadaan = 'gagal';
                $this->pesan = 'Pembayaran belum dapat kami pastikan. Bila dana sudah terpotong, '
                    .'hubungi kami dengan nomor pesanan '.$pesanan->order_number.'.';
            }
        }
    }

    private function pesanan(): ?Order
    {
        $pesanan = Order::query()->where('ulid', $this->ulid)->first();

        // Halaman ini hanya untuk pesanan PayPal. Pesanan manual punya
        // alurnya sendiri (unggah bukti transfer).
        if ($pesanan === null || $pesanan->payment_method !== MetodePembayaran::Paypal) {
            return null;
        }

        return $pesanan;
    }

    public function render(): View
    {
        return view('livewire.front.paypal-kembali', [
            'pesanan' => $this->pesanan(),
        ])->title(__('site.checkout.title').' · BITESExhaust');
    }
}
