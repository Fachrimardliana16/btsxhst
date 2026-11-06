<?php

namespace App\Mail;

use App\Enums\MetodePembayaran;
use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email ke pembeli untuk setiap tahap pesanan.
 *
 * Satu kelas untuk empat kejadian, bukan empat kelas terpisah: isinya berbeda
 * hanya pada judul, kalimat pembuka, dan satu-dua baris tambahan. Memisahkannya
 * menjadi empat kelas berarti empat berkas yang 90% isinya sama, dan setiap
 * perubahan tata letak harus disalin keempat-empatnya.
 *
 * Yang tetap SAMA di semua kejadian: rincian pesanan, alamat pengiriman, dan
 * nomor pesanan. Itulah yang dicari pembeli ketika membuka email seperti ini —
 * bukan kata sambutan.
 */
class PesananPembeli extends Mailable
{
    use Queueable, SerializesModels;

    public const DIBUAT = 'dibuat';

    public const DIBAYAR = 'dibayar';

    public const DIKIRIM = 'dikirim';

    public const SELESAI = 'selesai';

    public function __construct(
        public readonly Order $pesanan,
        public readonly string $kejadian,
    ) {}

    public function envelope(): Envelope
    {
        $toko = Pengaturan::instance();
        $nama = (string) $toko->teks('site_name', 'Bites Exhaust');

        $judul = match ($this->kejadian) {
            self::DIBAYAR => 'Pembayaran diterima',
            self::DIKIRIM => 'Pesanan dikirim',
            self::SELESAI => 'Pesanan selesai',
            default => 'Pesanan diterima',
        };

        return new Envelope(
            subject: $judul.' · '.$this->pesanan->order_number.' · '.$nama,
        );
    }

    public function content(): Content
    {
        $toko = Pengaturan::instance();
        $snapshot = (array) $this->pesanan->shipping_address_snapshot;

        return new Content(
            markdown: 'mail.pesanan-pembeli',
            with: [
                'kejadian' => $this->kejadian,
                'pesanan' => $this->pesanan,
                'nama' => (string) ($snapshot['recipient_name'] ?? 'Pelanggan'),
                'toko' => $toko,
                'urlLacak' => route('front.order.track', ['kode' => $this->pesanan->order_number]),
                'urlPesanan' => route('front.order.success', $this->pesanan),
                'rekening' => $this->rekening(),
                'alamat' => $this->alamat($snapshot),
            ],
        );
    }

    /**
     * Rekening tujuan, hanya untuk pesanan manual yang belum dibayar.
     *
     * Email pesanan baru untuk pembayaran manual memang harus memuat ini:
     * tanpa nomor rekening, pembeli tidak punya cara membayar selain membuka
     * kembali situs.
     */
    private function rekening(): ?array
    {
        if ($this->kejadian !== self::DIBUAT) {
            return null;
        }

        if ($this->pesanan->payment_method !== MetodePembayaran::Manual) {
            return null;
        }

        $toko = Pengaturan::instance();
        $nomor = (string) $toko->teks('bank_account_number', '');

        if ($nomor === '') {
            return null;
        }

        return [
            'bank' => (string) $toko->teks('bank_name', ''),
            'nomor' => $nomor,
            'atas_nama' => (string) $toko->teks('bank_account_name', ''),
        ];
    }

    /** @param array<string, mixed> $s */
    private function alamat(array $s): string
    {
        $baris = array_filter([
            $s['recipient_name'] ?? null,
            $s['phone_number'] ?? null,
            $s['address_line_1'] ?? null,
            $s['address_line_2'] ?? null,
            trim(implode(', ', array_filter([
                $s['city'] ?? null,
                $s['state_province'] ?? null,
                $s['postal_code'] ?? null,
            ]))),
        ]);

        return implode("\n", $baris);
    }
}
