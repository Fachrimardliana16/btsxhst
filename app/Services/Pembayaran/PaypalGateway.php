<?php

namespace App\Services\Pembayaran;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Services\StatusPesananService;
use App\Support\CatatanAktivitas;
use App\Support\HargaDolar;
use App\Support\KomponenPengaturan;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pesanan PayPal dan penanganan kembaliannya (checklist F9-01, F9-02, F9-03,
 * F9-04).
 *
 * Tiga hal yang menentukan benar atau tidaknya modul pembayaran, dan ketiganya
 * dikerjakan di sini:
 *
 * 1. Nominal TIDAK diambil dari permintaan. Yang dipakai adalah `grand_total`
 *    dari basis data. Kalau nominalnya ikut dari sisi klien, pembeli dapat
 *    membayar Rp 1 untuk pesanan Rp 5 juta dan pesanan tetap menjadi lunas.
 * 2. Webhook diverifikasi tanda tangannya ke PayPal sebelum dipercaya. Tanpa
 *    itu siapa pun yang tahu alamat webhook dapat menandai pesanannya lunas
 *    dengan satu permintaan POST.
 * 3. Webhook yang datang dua kali tidak boleh menghasilkan dua baris
 *    pembayaran. Indeks unik `provider + provider_transaction_id` yang
 *    menjaga; di sini pengecekannya dilakukan lebih dulu supaya balasannya
 *    tetap 200 (PayPal mengulang kiriman bila tidak menerima 200, dan
 *    pengulangan tanpa akhir akan tercatat sebagai gangguan).
 */
class PaypalGateway
{
    private const TIMEOUT = 20;

    /** Cache token selama satu permintaan saja: PayPal memberi 9 jam. */
    private ?string $token = null;

    public function __construct(private readonly ?KlienPaypal $klien = null) {}

    private function klien(): KlienPaypal
    {
        return $this->klien ?? new KlienPaypal;
    }

    /**
     * Membuat pesanan PayPal untuk satu pesanan toko (checklist F9-01).
     *
     * @return array{id: string, status: string, setuju_url: ?string}
     */
    public function buatPesanan(Order $pesanan): array
    {
        // Hanya pesanan yang masih menunggu pembayaran yang boleh membuat
        // pesanan PayPal baru. Pesanan batal sudah melepas stoknya; membiarkan
        // pembeli membayarnya berarti uang masuk untuk barang yang tidak akan
        // dikirim.
        $pesanan->refresh();

        if ($pesanan->status !== StatusPesanan::Pending) {
            throw new RuntimeException('Pesanan ini tidak lagi menunggu pembayaran.');
        }

        $token = $this->aksesToken();

        $muatan = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $pesanan->order_number,
                // Nominal dari basis data, bukan dari permintaan.
                'amount' => [
                    'currency_code' => strtoupper((string) ($pesanan->currency ?: HargaDolar::MATA)),
                    'value' => number_format((float) $pesanan->grand_total, 2, '.', ''),
                ],
                'description' => mb_substr('Pesanan '.$pesanan->order_number, 0, 127),
            ]],
            'application_context' => [
                'brand_name' => mb_substr((string) Pengaturan::instance()->teks('site_name', 'Bites Exhaust'), 0, 127),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                // Alamat kembali WAJIB untuk alur redirect (bukan JS SDK).
                // Tanpa return_url, PayPal tidak punya tujuan setelah pembeli
                // menyetujui pembayaran: halaman PayPal berputar tanpa
                // mengembalikan pembeli, dan penangkapan dana di
                // PaypalKembali tidak pernah dijalankan.
                'return_url' => route('front.paypal.kembali', ['ulid' => $pesanan->ulid]),
                'cancel_url' => route('front.paypal.kembali', ['ulid' => $pesanan->ulid, 'gagal' => 1]),
            ],
        ];

        $respons = Http::withToken($token)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->post($this->klien()->dasar().'/v2/checkout/orders', $muatan);

        if (! $respons->successful()) {
            Log::warning('PayPal menolak pembuatan pesanan', [
                'pesanan' => $pesanan->order_number,
                'status' => $respons->status(),
                'balasan' => mb_substr((string) $respons->body(), 0, 300),
            ]);

            throw new RuntimeException('PayPal menolak pembuatan pesanan (HTTP '.$respons->status().').');
        }

        $setuju = collect((array) $respons->json('links', []))
            ->firstWhere('rel', 'approve');

        return [
            'id' => (string) $respons->json('id'),
            'status' => (string) $respons->json('status'),
            'setuju_url' => is_array($setuju) ? (string) ($setuju['href'] ?? '') : null,
        ];
    }

    /**
     * Menangkap dana setelah pembeli menyetujui pembayaran (checklist F9-02).
     *
     * Status PayPal yang diterima adalah COMPLETED. Yang ditangkap adalah
     * transaction id hasil capture, bukan id pesanan PayPal — id itu yang
     * dipakai PayPal di webhook, sehingga baris pembayaran dan webhook yang
     * datang kemudian merujuk ke transaksi yang sama.
     */
    public function tangkap(Order $pesanan, string $paypalOrderId): Payment
    {
        $pesanan->refresh();

        if ($pesanan->status === StatusPesanan::Cancelled) {
            throw new RuntimeException('Pesanan ini sudah dibatalkan dan tidak dapat dibayar.');
        }

        if ($pesanan->payment_status === StatusPembayaran::Paid) {
            $ada = Payment::query()
                ->where('order_id', $pesanan->id)
                ->where('provider', 'paypal')
                ->first();

            if ($ada !== null) {
                return $ada;
            }
        }

        if ($pesanan->status !== StatusPesanan::Pending) {
            throw new RuntimeException('Pesanan ini tidak lagi menunggu pembayaran.');
        }

        // Sebelum dana ditangkap, pastikan pesanan PayPal yang dibawa klien
        // memang pesanan PayPal yang dibuat untuk pesanan toko ini. Tanpa
        // pemeriksaan ini, id pesanan PayPal milik transaksi murah dapat
        // dipasangkan ke pesanan toko yang mahal: yang ditangkap hanya nilainya
        // yang kecil, tetapi yang ditandai lunas pesanan yang mahal.
        $this->pastikanPesananPaypalCocok(
            $pesanan,
            $this->rincianPesananPaypal($paypalOrderId),
            'pesanan PayPal',
        );

        $token = $this->aksesToken();

        $respons = Http::withToken($token)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($this->klien()->dasar().'/v2/checkout/orders/'.$paypalOrderId.'/capture');

        if (! $respons->successful()) {
            throw new RuntimeException('PayPal gagal menangkap pembayaran (HTTP '.$respons->status().').');
        }

        $status = strtoupper((string) $respons->json('status'));

        if ($status !== 'COMPLETED') {
            throw new RuntimeException('PayPal melaporkan status '.$status.' — pembayaran belum selesai.');
        }

        $transaksiId = (string) (
            $respons->json('purchase_units.0.payments.captures.0.id')
            ?: $paypalOrderId
        );

        // Nominal hasil penangkapan adalah yang benar-benar berpindah, jadi
        // inilah yang dibandingkan — bukan nominal yang diminta saat pesanan
        // PayPal dibuat.
        $nilaiTangkap = $respons->json('purchase_units.0.payments.captures.0.amount.value');
        $mataTangkap = $respons->json('purchase_units.0.payments.captures.0.amount.currency_code');

        $this->pastikanNominal($pesanan, $nilaiTangkap, $mataTangkap, 'payPal capture');

        return $this->catatPembayaran(
            $pesanan,
            $transaksiId,
            round((float) $nilaiTangkap, 2),
            strtoupper((string) $mataTangkap),
            ['sumber' => 'capture', 'status_paypal' => $status],
        );
    }

    /**
     * Memverifikasi tanda tangan webhook ke PayPal (checklist F9-03).
     *
     * Verifikasi dilakukan dengan memanggil endpoint PayPal, bukan dengan
     * menghitung ulang HMAC di sisi kita: kunci yang dipakai PayPal adalah
     * kunci mereka, dan menebaknya berarti menganggap semua kiriman sah.
     *
     * ── BUG YANG DIPERBAIKI DI SINI ─────────────────────────────────────
     * Versi sebelumnya membaca empat nilai dari `getenv()`:
     *
     *     getenv('PAYPAL_TRANSMISI_ID')       // selalu '' di PHP modern
     *
     * Nilai-nilai itu bukan variabel lingkungan — mereka HEADER HTTP yang
     * dikirim PayPal pada setiap permintaan webhook:
     *
     *     PAYPAL-TRANSMISSION-ID, PAYPAL-TRANSMISSION-TIME,
     *     PAYPAL-TRANSMISSION-SIG, PAYPAL-CERT-URL, PAYPAL-AUTH-ALGO
     *
     * `getenv()` tidak pernah melihat header, jadi hasilnya selalu string
     * kosong. Akibatnya seluruh permintaan verifikasi dikirim ke PayPal
     * dengan nilai kosong, PayPal menjawab gagal, dan SEMUA webhook ditolak —
     * pembayaran tidak pernah tercatat walaupun uangnya sudah masuk.
     *
     * Sekarang nilainya dibaca dari $request, dengan nama header yang benar.
     *
     * @param  array<string, mixed>  $peristiwa
     * @param  Request|null  $request  permintaan webhook asal
     * @return bool true bila PayPal menyatakan kiriman ini asli
     */
    public function verifikasiTandaTangan(array $peristiwa, ?Request $request = null): bool
    {
        $request ??= request();

        $idTransmisi = (string) $request->header('PAYPAL-TRANSMISSION-ID', '');
        $tanggal = (string) $request->header('PAYPAL-TRANSMISSION-TIME', '');
        $tandaTangan = (string) $request->header('PAYPAL-TRANSMISSION-SIG', '');
        $sertifikat = (string) $request->header('PAYPAL-CERT-URL', '');
        $algo = (string) $request->header('PAYPAL-AUTH-ALGO', 'SHA256withRSA');
        $idWebhook = (string) Pengaturan::instance()->teks('paypal_webhook_id');

        // Tanpa id webhook, PayPal tidak dapat memastikan kiriman ini milik
        // toko ini. Menolak lebih baik daripada menerima tanpa dasar.
        if ($idWebhook === '') {
            return false;
        }

        // Header wajib: tanpa salah satunya, tidak ada yang bisa diverifikasi.
        // Diperiksa di sini supaya tidak membuang kuota API PayPal untuk
        // permintaan yang jelas tidak lengkap.
        if ($idTransmisi === '' || $tanggal === '' || $tandaTangan === '' || $sertifikat === '') {
            return false;
        }

        $token = $this->aksesToken();

        $respons = Http::withToken($token)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->post($this->klien()->dasar().'/v1/notifications/verify-webhook-signature', [
                'transmission_id' => $idTransmisi,
                'transmission_time' => $tanggal,
                'cert_url' => $sertifikat,
                'auth_algo' => $algo,
                'transmission_sig' => $tandaTangan,
                'webhook_id' => $idWebhook,
                'webhook_event' => $peristiwa,
            ]);

        if (! $respons->successful()) {
            Log::warning('PayPal menolak verifikasi tanda tangan webhook', [
                'status' => $respons->status(),
                'balasan' => mb_substr((string) $respons->body(), 0, 300),
            ]);

            return false;
        }

        return strtoupper((string) $respons->json('verification_status')) === 'SUCCESS';
    }

    /**
     * Menangani satu peristiwa webhook (checklist F9-03, F9-04).
     *
     * @param  array<string, mixed>  $peristiwa
     * @return array{diabaikan: bool, alasan: string}
     */
    public function tanganiWebhook(array $peristiwa, bool $tandaTanganSah): array
    {
        if (! $tandaTanganSah) {
            CatatanAktivitas::catat('paypal.webhook-tanda-tangan-palsu', [
                'jenis' => $peristiwa['event_type'] ?? null,
            ]);

            return ['diabaikan' => true, 'alasan' => 'Tanda tangan tidak sah.'];
        }

        $jenis = (string) ($peristiwa['event_type'] ?? '');

        // APPROVED bukan bukti uang masuk. Peristiwa ini hanya berarti pembeli
        // menekan setuju; dana baru berpindah setelah /capture. Menandainya
        // lunas di sini membuat pesanan tampak terbayar padahal tidak ada
        // transaksi — dan penangkapan di halaman kembali tidak pernah berjalan
        // bila pembeli menutup peramban. Karena itu APPROVED memicu capture
        // dari sisi server, bukan pencatatan pembayaran.
        if ($jenis === 'CHECKOUT.ORDER.APPROVED') {
            $sumber = (array) ($peristiwa['resource'] ?? []);
            $idPaypal = (string) ($sumber['id'] ?? '');
            $nomorPesanan = (string) (data_get($sumber, 'purchase_units.0.reference_id') ?? '');
            $pesanan = $this->cariPesanan($nomorPesanan, $sumber);

            if ($pesanan === null) {
                return ['diabaikan' => true, 'alasan' => 'No order matches this event.'];
            }

            if ($idPaypal === '') {
                return ['diabaikan' => true, 'alasan' => 'Peristiwa tanpa id pesanan PayPal.'];
            }

            if ($this->sudahDibayar($pesanan)) {
                return ['diabaikan' => true, 'alasan' => 'Pembayaran pesanan ini sudah tercatat.'];
            }

            if ($pesanan->status !== StatusPesanan::Pending) {
                CatatanAktivitas::catat('paypal.pesanan-tidak-menunggu-pembayaran', [
                    'order_number' => $pesanan->order_number,
                    'status' => $pesanan->status->value,
                    'paypal_id' => $idPaypal,
                ], null, $pesanan);

                return ['diabaikan' => true, 'alasan' => 'Pesanan tidak lagi menunggu pembayaran.'];
            }

            $this->tangkap($pesanan, $idPaypal);

            return ['diabaikan' => false, 'alasan' => 'Pembayaran ditangkap.'];
        }

        // Dana yang ditarik kembali atau ditolak. Sebelumnya peristiwa ini
        // dijawab "tidak memengaruhi status pesanan", sehingga refund dan
        // chargeback tidak meninggalkan jejak apa pun di basis data.
        if (in_array($jenis, ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED', 'PAYMENT.CAPTURE.DENIED'], true)) {
            return $this->tanganiPembatalanDana($jenis, (array) ($peristiwa['resource'] ?? []));
        }

        // Sengketa dibuka pembeli. Statusnya dicatat supaya admin tahu ada
        // uang yang sedang diperebutkan, bukan menemukannya di email PayPal.
        if (str_starts_with($jenis, 'CUSTOMER.DISPUTE.')) {
            return $this->tanganiSengketa($jenis, (array) ($peristiwa['resource'] ?? []));
        }

        if ($jenis !== 'PAYMENT.CAPTURE.COMPLETED') {
            return ['diabaikan' => true, 'alasan' => 'Peristiwa '.$jenis.' tidak memengaruhi status pesanan.'];
        }

        $sumber = (array) ($peristiwa['resource'] ?? []);

        $transaksiId = (string) (
            $sumber['id']
            ?? data_get($sumber, 'supplementary_data.related_ids.order_id')
            ?? ''
        );

        $nomorPesanan = (string) (
            data_get($sumber, 'purchase_units.0.reference_id')
            ?? data_get($sumber, 'custom_id')
            ?? ''
        );

        $pesanan = $this->cariPesanan($nomorPesanan, $sumber);

        if ($pesanan === null) {
            return ['diabaikan' => true, 'alasan' => 'No order matches this event.'];
        }

        if ($transaksiId === '') {
            return ['diabaikan' => true, 'alasan' => 'Peristiwa tanpa id transaksi.'];
        }

        // Kiriman ganda: PayPal mengulang webhook bila tidak menerima 200.
        // Yang penting bukan menolaknya, tetapi tidak mencatatnya dua kali.
        $sudahAda = Payment::query()
            ->where('provider', 'paypal')
            ->where('provider_transaction_id', $transaksiId)
            ->exists();

        if ($sudahAda) {
            return ['diabaikan' => true, 'alasan' => 'Transaksi '.$transaksiId.' sudah tercatat.'];
        }

        if ($pesanan->status !== StatusPesanan::Pending) {
            CatatanAktivitas::catat('paypal.pesanan-tidak-menunggu-pembayaran', [
                'order_number' => $pesanan->order_number,
                'status' => $pesanan->status->value,
                'transaksi' => $transaksiId,
            ], null, $pesanan);

            return ['diabaikan' => true, 'alasan' => 'Pesanan tidak lagi menunggu pembayaran.'];
        }

        try {
            $this->pastikanNominal(
                $pesanan,
                $sumber['amount']['value'] ?? null,
                $sumber['amount']['currency_code'] ?? null,
                'PayPal webhook',
            );
        } catch (RuntimeException $e) {
            // Uang sudah berpindah tetapi nominalnya tidak cocok dengan pesanan.
            // Jangan tandai lunas; catat supaya admin meninjaunya.
            CatatanAktivitas::catat('paypal.nominal-tidak-cocok', [
                'order_number' => $pesanan->order_number,
                'transaksi' => $transaksiId,
                'nominal' => $sumber['amount']['value'] ?? null,
                'mata_uang' => $sumber['amount']['currency_code'] ?? null,
                'sebab' => $e->getMessage(),
            ], null, $pesanan);

            return ['diabaikan' => true, 'alasan' => 'Nominal pembayaran tidak cocok dengan pesanan.'];
        }

        $this->catatPembayaran(
            $pesanan,
            $transaksiId,
            round((float) $sumber['amount']['value'], 2),
            strtoupper((string) $sumber['amount']['currency_code']),
            ['sumber' => 'webhook', 'jenis' => $jenis],
        );

        return ['diabaikan' => false, 'alasan' => 'Pembayaran dicatat.'];
    }

    /**
     * Mencari baris pembayaran yang dirujuk peristiwa PayPal.
     *
     * Objek refund dan dispute tidak selalu membawa id capture di tempat yang
     * sama: kadang di `id`, kadang di `supplementary_data`, kadang hanya di
     * tautan `up` menuju /captures/{id}. Semua kandidat dicoba, karena salah
     * menebak satu bentuk berarti refund tidak pernah tercatat.
     *
     * @param  array<string, mixed>  $sumber
     */
    private function cariPembayaranPaypal(array $sumber): ?Payment
    {
        $kandidat = [
            (string) ($sumber['id'] ?? ''),
            (string) (data_get($sumber, 'supplementary_data.related_ids.capture_id') ?? ''),
            (string) ($sumber['custom_id'] ?? ''),
        ];

        foreach ((array) ($sumber['links'] ?? []) as $tautan) {
            $href = (string) ($tautan['href'] ?? '');

            if (preg_match('#/captures/([^/?]+)#', $href, $cocok) === 1) {
                $kandidat[] = $cocok[1];
            }
        }

        foreach ((array) ($sumber['disputed_transactions'] ?? []) as $transaksi) {
            $kandidat[] = (string) ($transaksi['seller_transaction_id'] ?? '');
        }

        $kandidat = array_values(array_unique(array_filter($kandidat, fn ($v) => $v !== '')));

        if ($kandidat === []) {
            return null;
        }

        return Payment::query()
            ->where('provider', 'paypal')
            ->whereIn('provider_transaction_id', $kandidat)
            ->first();
    }

    /**
     * Refund, reversal, atau capture yang ditolak.
     *
     * @param  array<string, mixed>  $sumber
     * @return array{diabaikan: bool, alasan: string}
     */
    private function tanganiPembatalanDana(string $jenis, array $sumber): array
    {
        $pembayaran = $this->cariPembayaranPaypal($sumber);

        if ($pembayaran === null) {
            CatatanAktivitas::catat('paypal.pembatalan-tanpa-pembayaran', [
                'jenis' => $jenis,
                'id' => $sumber['id'] ?? null,
            ]);

            return ['diabaikan' => true, 'alasan' => 'Tidak ada pembayaran tercatat yang cocok; perlu ditinjau manual.'];
        }

        $pesanan = $pembayaran->order()->first();

        // Refund sebagian tidak mengubah pesanan menjadi "refunded": uang yang
        // masih dipegang toko tetap ada, dan menandai lunas menjadi refunded
        // akan membuat laporan pendapatan salah.
        $nominal = round((float) (data_get($sumber, 'amount.value') ?? 0), 2);
        $sebagian = $jenis !== 'PAYMENT.CAPTURE.DENIED'
            && $nominal > 0
            && $nominal < round((float) $pembayaran->amount, 2) - 0.01;

        $statusBaru = match (true) {
            $jenis === 'PAYMENT.CAPTURE.DENIED' => 'ditolak',
            $sebagian => 'refund-sebagian',
            default => 'refunded',
        };

        if ($pembayaran->provider_status === $statusBaru) {
            return ['diabaikan' => true, 'alasan' => 'Peristiwa '.$jenis.' sudah tercatat.'];
        }

        $pembayaran->update([
            'provider_status' => $statusBaru,
            'payload_json' => array_merge((array) $pembayaran->payload_json, [
                'pembatalan' => [
                    'jenis' => $jenis,
                    'resource' => $sumber,
                    'diterima_pada' => now()->toIso8601String(),
                ],
            ]),
        ]);

        if ($pesanan !== null && ! $sebagian) {
            $pesanan->forceFill([
                'payment_status' => $jenis === 'PAYMENT.CAPTURE.DENIED'
                    ? StatusPembayaran::Failed
                    : StatusPembayaran::Refunded,
            ])->save();

            CatatanAktivitas::catat('paypal.pembayaran-dibatalkan', [
                'jenis' => $jenis,
                'order_number' => $pesanan->order_number,
                'transaksi' => $pembayaran->provider_transaction_id,
                'nominal' => $nominal,
            ], null, $pesanan);
        }

        Log::warning('PayPal: dana dikembalikan atau ditolak', [
            'jenis' => $jenis,
            'payment_id' => $pembayaran->id,
            'status' => $statusBaru,
        ]);

        return ['diabaikan' => false, 'alasan' => 'Status pembayaran diperbarui menjadi '.$statusBaru.'.'];
    }

    /**
     * Sengketa dari pembeli. Status pesanan TIDAK diubah: hanya admin yang
     * boleh memutuskan refund atau pengiriman ulang.
     *
     * @param  array<string, mixed>  $sumber
     * @return array{diabaikan: bool, alasan: string}
     */
    private function tanganiSengketa(string $jenis, array $sumber): array
    {
        $pembayaran = $this->cariPembayaranPaypal($sumber);

        if ($pembayaran === null) {
            CatatanAktivitas::catat('paypal.sengketa-tanpa-pembayaran', [
                'jenis' => $jenis,
                'dispute_id' => $sumber['dispute_id'] ?? null,
            ]);

            return ['diabaikan' => true, 'alasan' => 'Tidak ada pembayaran tercatat yang cocok; perlu ditinjau manual.'];
        }

        $pembayaran->update([
            'provider_status' => 'dispute',
            'payload_json' => array_merge((array) $pembayaran->payload_json, [
                'sengketa' => [
                    'jenis' => $jenis,
                    'resource' => $sumber,
                    'diterima_pada' => now()->toIso8601String(),
                ],
            ]),
        ]);

        Log::warning('PayPal: sengketa pembeli', [
            'jenis' => $jenis,
            'payment_id' => $pembayaran->id,
        ]);

        return ['diabaikan' => false, 'alasan' => 'Sengketa dicatat; pesanan menunggu keputusan admin.'];
    }

    /**
     * Rincian pesanan PayPal, termasuk `reference_id` dan nominal yang diminta.
     *
     * @return array<string, mixed>
     */
    private function rincianPesananPaypal(string $paypalOrderId): array
    {
        $respons = Http::withToken($this->aksesToken())
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->get($this->klien()->dasar().'/v2/checkout/orders/'.$paypalOrderId);

        if (! $respons->successful()) {
            throw new RuntimeException('PayPal tidak dapat memeriksa pesanan (HTTP '.$respons->status().').');
        }

        return (array) $respons->json();
    }

    /**
     * Memastikan pesanan PayPal memang milik pesanan toko ini.
     *
     * `reference_id` adalah nomor pesanan toko yang kita kirim sendiri saat
     * membuat pesanan PayPal. Bila nilainya berbeda, id PayPal ini berasal dari
     * transaksi lain — biasanya transaksi yang nominalnya lebih kecil.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanPesananPaypalCocok(Order $pesanan, array $data, string $sumber): void
    {
        $referensi = (string) (data_get($data, 'purchase_units.0.reference_id') ?? '');

        if ($referensi !== (string) $pesanan->order_number) {
            throw new RuntimeException(sprintf(
                '%s tidak cocok dengan pesanan %s (reference_id "%s").',
                $sumber,
                $pesanan->order_number,
                $referensi,
            ));
        }

        $this->pastikanNominal(
            $pesanan,
            data_get($data, 'purchase_units.0.amount.value'),
            data_get($data, 'purchase_units.0.amount.currency_code'),
            $sumber,
        );
    }

    /**
     * Nominal dan mata uang harus sama persis dengan yang tercatat di pesanan.
     * Toleransi satu sen hanya untuk pembulatan desimal PayPal.
     */
    private function pastikanNominal(Order $pesanan, mixed $nilai, mixed $mataUang, string $sumber): void
    {
        $diharapkan = round((float) $pesanan->grand_total, 2);
        $diterima = is_numeric($nilai) ? round((float) $nilai, 2) : null;

        if ($diterima === null || abs($diterima - $diharapkan) > 0.009) {
            throw new RuntimeException(sprintf(
                '%s membayar %s, sedangkan pesanan %s menagih %.2f.',
                $sumber,
                $diterima === null ? 'tanpa nominal' : number_format($diterima, 2, '.', ''),
                $pesanan->order_number,
                $diharapkan,
            ));
        }

        $diharapkanMata = strtoupper((string) ($pesanan->currency ?: HargaDolar::MATA));
        $diterimaMata = strtoupper((string) ($mataUang ?? ''));

        if ($diterimaMata !== $diharapkanMata) {
            throw new RuntimeException(sprintf(
                '%s memakai mata uang %s, sedangkan pesanan %s memakai %s.',
                $sumber,
                $diterimaMata === '' ? '(kosong)' : $diterimaMata,
                $pesanan->order_number,
                $diharapkanMata,
            ));
        }
    }

    /**
     * Mencatat pembayaran dan memindahkan pesanan ke lunas.
     *
     * Dipakai jalur capture maupun webhook, supaya keduanya menghasilkan baris
     * yang sama persis. Perpindahan status lewat StatusPesananService agar
     * stok tertahan tetap seimbang.
     */
    private function catatPembayaran(
        Order $pesanan,
        string $transaksiId,
        float $nominal,
        string $mataUang,
        array $muatan,
    ): Payment {
        $pembayaran = DB::transaction(function () use ($pesanan, $transaksiId, $nominal, $mataUang, $muatan): Payment {
            // Kunci baris pesanan supaya capture dari halaman kembali dan
            // webhook yang datang hampir bersamaan tidak membuat dua baris
            // pembayaran. Pembayaran pertama yang menang; yang kedua memakai
            // baris yang sama.
            $terkunci = Order::query()->whereKey($pesanan->getKey())->lockForUpdate()->first() ?? $pesanan;

            // Diperiksa lagi di dalam transaksi: pembatalan otomatis dapat
            // berjalan di antara pemeriksaan di atas dan penulisan di sini.
            if ($terkunci->status === StatusPesanan::Cancelled) {
                throw new RuntimeException('Pesanan sudah dibatalkan; pembayaran tidak dicatat.');
            }

            $ada = Payment::query()
                ->where('order_id', $terkunci->id)
                ->where('provider', 'paypal')
                ->first();

            if ($ada !== null) {
                return $ada;
            }

            $payment = Payment::query()->create([
                'order_id' => $terkunci->id,
                'provider' => 'paypal',
                'provider_transaction_id' => $transaksiId,
                'provider_status' => 'completed',
                'amount' => $nominal,
                'currency' => $mataUang,
                'payload_json' => $muatan,
                'verified_at' => now(),
            ]);

            $terkunci->update(['payment_status' => StatusPembayaran::Paid]);

            return $payment;
        });

        if ($pesanan->fresh()->status === StatusPesanan::Pending) {
            app(StatusPesananService::class)->pindah(
                $pesanan->fresh(),
                StatusPesanan::Paid,
                null,
                'PayPal payment received.',
            );

            KirimNotifikasiPesanan::dispatch($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBAYAR);
        }

        CatatanAktivitas::catat('paypal.pembayaran-diterima', [
            'order_number' => $pesanan->order_number,
            'transaksi' => $transaksiId,
            'nominal' => $nominal,
        ], null, $pesanan);

        return $pembayaran;
    }

    /** Apakah pesanan ini sudah punya pembayaran PayPal yang tercatat. */
    private function sudahDibayar(Order $pesanan): bool
    {
        return Payment::query()
            ->where('order_id', $pesanan->id)
            ->where('provider', 'paypal')
            ->exists();
    }

    /**
     * Pesanan untuk sebuah peristiwa.
     *
     * Urutannya: reference_id (nomor pesanan yang kita kirim sendiri), lalu
     * id PayPal yang pernah tercatat di baris pembayaran.
     *
     * @param  array<string, mixed>  $sumber
     */
    private function cariPesanan(string $nomorPesanan, array $sumber): ?Order
    {
        if ($nomorPesanan !== '') {
            $pesanan = Order::query()->where('order_number', $nomorPesanan)->first();

            if ($pesanan !== null) {
                return $pesanan;
            }
        }

        $idPaypal = (string) (($sumber['id'] ?? '') ?: '');

        if ($idPaypal === '') {
            return null;
        }

        $pembayaran = Payment::query()
            ->where('provider', 'paypal')
            ->where('provider_transaction_id', $idPaypal)
            ->first();

        return $pembayaran?->order()->first();
    }

    /** Token OAuth PayPal, diminta sekali per objek. */
    private function aksesToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $id = (string) (KomponenPengaturan::mentah('paypal_client_id') ?? '');
        $rahasia = (string) (KomponenPengaturan::mentah('paypal_client_secret') ?? '');

        if ($id === '' || $rahasia === '') {
            throw new RuntimeException('Kredensial PayPal belum diisi di pengaturan.');
        }

        try {
            $respons = Http::asForm()
                ->withBasicAuth($id, $rahasia)
                ->timeout(self::TIMEOUT)
                ->post($this->klien()->dasar().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (Throwable $e) {
            throw new RuntimeException('PayPal tidak dapat dihubungi: '.$e->getMessage());
        }

        if (! $respons->successful()) {
            throw new RuntimeException('PayPal menolak kredensial (HTTP '.$respons->status().').');
        }

        $token = (string) $respons->json('access_token');

        if ($token === '') {
            throw new RuntimeException('PayPal tidak mengembalikan access_token.');
        }

        return $this->token = $token;
    }
}
