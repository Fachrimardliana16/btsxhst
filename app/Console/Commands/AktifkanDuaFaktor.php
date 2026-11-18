<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Mengaktifkan atau mematikan dua faktor untuk satu akun panel.
 *
 * Panel tidak punya halaman pendaftaran 2FA, dan seeder tidak lagi mengisi
 * `two_factor_confirmed_at` tanpa secret. Perintah ini satu-satunya jalur yang
 * benar-benar menyimpan secret, sehingga login benar-benar meminta kode.
 *
 * Dijalankan di server, oleh orang yang sudah punya akses shell — sama seperti
 * `iksan:buka-blokir`.
 */
class AktifkanDuaFaktor extends Command
{
    protected $signature = 'iksan:dua-faktor
                            {email : Alamat email akun panel}
                            {--nonaktif : Copot dua faktor dari akun ini}';

    protected $description = 'Aktifkan atau copot dua faktor (TOTP) untuk akun panel admin';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        $user = User::query()
            ->where('email_bidx', BlindIndex::buat($email, 'email_bidx'))
            ->first();

        if ($user === null) {
            $this->error('No account uses that email address.');

            return self::FAILURE;
        }

        if (! $user->admin()) {
            $this->error('That account cannot sign in to the admin panel.');

            return self::FAILURE;
        }

        if ($this->option('nonaktif')) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
            ])->save();

            $this->warn('Two-factor authentication is now OFF for '.$email.'.');

            return self::SUCCESS;
        }

        $google = new Google2FA;
        $secret = $google->generateSecretKey();
        $issuer = (string) (config('app.name') ?: 'Admin');

        $this->line('Account: '.$email);
        $this->line('Secret : '.$secret);
        $this->line('otpauth: '.$google->getQRCodeUrl($issuer, $email, $secret));
        $this->newLine();
        $this->line('Add the secret to an authenticator app, then enter the 6-digit code below.');

        $kode = preg_replace('/\s+/', '', (string) $this->ask('Code')) ?? '';

        if (strlen($kode) !== 6 || ! $google->verifyKey($secret, $kode)) {
            $this->error('That code did not verify. Nothing was saved.');

            return self::FAILURE;
        }

        $cadangan = [];

        for ($i = 0; $i < 10; $i++) {
            $cadangan[] = Str::upper(Str::random(10));
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $cadangan,
        ])->save();

        $this->newLine();
        $this->info('Two-factor authentication is now ON for '.$email.'.');
        $this->warn('Recovery codes — each works once. Store them somewhere safe:');
        $this->line(implode(' ', $cadangan));

        return self::SUCCESS;
    }
}
