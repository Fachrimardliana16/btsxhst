<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email ke pemilik toko.
 *
 * Sebelumnya tidak ada sama sekali: pesanan masuk, dan tidak seorang pun di
 * sisi admin diberi tahu kecuali ia membuka panel. Untuk toko yang dijalankan
 * sambil mengerjakan hal lain, itu berarti pesanan bisa menganggur berjam-jam.
 *
 * Dua kejadian yang dikirim, dan hanya dua:
 *
 *   BARU    — ada uang yang perlu diperiksa (atau pesanan yang perlu
 *             disiapkan). Ini yang harus cepat diketahui.
 *   SELESAI — pesanan tuntas. Berguna sebagai rekap, bukan sebagai alarm.
 *
 * Kejadian lain (dibayar, dikirim) TIDAK dikirim ke admin: keduanya dilakukan
 * OLEH admin sendiri lewat panel, jadi mengirim email tentang tindakan yang
 * baru saja ia lakukan hanya menambah kebisingan.
 */
class PesananAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public const BARU = 'baru';

    public const SELESAI = 'selesai';

    public function __construct(
        public readonly Order $pesanan,
        public readonly string $kejadian,
    ) {}

    public function envelope(): Envelope
    {
        $toko = Pengaturan::instance();
        $nama = (string) $toko->teks('site_name', 'Bites Exhaust');

        $judul = $this->kejadian === self::SELESAI
            ? 'Pesanan selesai'
            : 'Pesanan baru';

        // Nomor pesanan dan total ikut di judul: itulah yang dibaca admin di
        // daftar masuk tanpa membuka emailnya.
        return new Envelope(
            subject: sprintf(
                '[%s] %s · %s',
                $judul,
                $this->pesanan->order_number,
                $this->pesanan->currency.' '.dolar($this->pesanan->grand_total),
            ),
        );
    }

    public function content(): Content
    {
        $toko = Pengaturan::instance();
        $snapshot = (array) $this->pesanan->shipping_address_snapshot;

        return new Content(
            markdown: 'mail.pesanan-admin',
            with: [
                'kejadian' => $this->kejadian,
                'pesanan' => $this->pesanan,
                'toko' => $toko,
                'nama' => (string) ($snapshot['recipient_name'] ?? '-'),
                'telepon' => (string) ($snapshot['phone_number'] ?? '-'),
                'surel' => (string) ($this->pesanan->guest_email ?: $this->pesanan->user?->email ?: '-'),
                'alamat' => $this->alamat($snapshot),
                'urlPanel' => route('admin.orders.show', $this->pesanan->ulid),
                'metode' => $this->pesanan->payment_method?->value === 'paypal' ? 'PayPal' : 'Transfer manual',
            ],
        );
    }

    /** @param array<string, mixed> $s */
    private function alamat(array $s): string
    {
        $baris = array_filter([
            $s['address_line_1'] ?? null,
            $s['address_line_2'] ?? null,
            trim(implode(', ', array_filter([
                $s['city'] ?? null,
                $s['state_province'] ?? null,
                $s['postal_code'] ?? null,
            ]))),
            $s['country'] ?? null,
        ]);

        return implode("\n", $baris);
    }
}
