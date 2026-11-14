<?php

namespace App\Enums;

enum TipeKejadianKeamanan: string
{
    case LoginGagal = 'login_failed';
    case LoginBerhasil = 'login_success';
    case BruteForceDiblokir = 'brute_force_blocked';
    case IpDiblokir = 'ip_blocked';
    case AksesTidakSah = 'unauthorized_access';
    case BatasLaju = 'rate_limit_exceeded';
    case DuaFaktorGagal = 'two_factor_failed';
    case UnggahanDitolak = 'upload_rejected';
    case AksiAdmin = 'admin_action';

    public function label(): string
    {
        return match ($this) {
            self::LoginGagal => 'Login failed',
            self::LoginBerhasil => 'Login succeeded',
            self::BruteForceDiblokir => 'Brute force blocked',
            self::IpDiblokir => 'IP blocked',
            self::AksesTidakSah => 'Unauthorized access',
            self::BatasLaju => 'Rate limit exceeded',
            self::DuaFaktorGagal => '2FA verification failed',
            self::UnggahanDitolak => 'Upload rejected',
            self::AksiAdmin => 'Admin action',
        };
    }

    public function nada(): string
    {
        return match ($this) {
            self::LoginGagal, self::DuaFaktorGagal => 'warn',
            self::BruteForceDiblokir, self::IpDiblokir, self::AksesTidakSah, self::BatasLaju, self::UnggahanDitolak => 'danger',
            self::LoginBerhasil, self::AksiAdmin => 'neutral',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
