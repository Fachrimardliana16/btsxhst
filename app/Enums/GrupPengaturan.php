<?php

namespace App\Enums;

enum GrupPengaturan: string
{
    case Identitas = 'identity';
    case Konten = 'konten';
    case Kontak = 'contact';
    case Sosial = 'social';
    case Pembayaran = 'payment';
    case Email = 'email';
    case Notifikasi = 'notification';
    case Lanjutan = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Identitas => 'Site Identity',
            self::Konten => 'Page Content',
            self::Kontak => 'Contact & Location',
            self::Sosial => 'Social Media',
            self::Pembayaran => 'Payment',
            self::Email => 'Email (SMTP)',
            self::Notifikasi => 'Notifications',
            self::Lanjutan => 'Advanced',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Identitas => 'badge',
            self::Konten => 'info',
            self::Kontak => 'phone',
            self::Sosial => 'share',
            self::Pembayaran => 'card',
            self::Email => 'mail',
            self::Notifikasi => 'bell',
            self::Lanjutan => 'gear',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
