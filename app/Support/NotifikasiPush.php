<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Pengirim notifikasi ke admin: web push, dengan surel sebagai cadangan.
 *
 * Dua jalur dipakai bersama, dan urutannya bukan hiasan:
 *
 *   WEB PUSH  — muncul seperti pesan WhatsApp, muncul di layar walau panel
 *               tidak terbuka. Inilah yang diminta. Butuh izin peramban, dan
 *               iOS Safari baru mendukungnya dari 16.4.
 *   SUREL     — selalu ada, selalu terkirim. Dipakai BILA push tidak ada
 *               antena (belum ada langganan), atau izin belum diberikan.
 *
 * Yang penting: kegagalan push TIDAK menggagalkan apa pun. Notifikasi adalah
 * kabar, bukan pekerjaan; pesanan yang gagal terkirim kabarnya tetap sah.
 * Kegagalan hanya dicatat, dan langganan yang sudah mati dibuang supaya
 * pengiriman berikutnya tidak menyia-nyiakan waktu ke alamat kosong.
 */
class NotifikasiPush
{
    /** Butir yang dikirim: judul + isi. */
    public const KUNCI_BATAS_PER_KIRIM = 20;

    /**
     * Apakah alamat push menunjuk layanan push yang dikenal.
     *
     * Endpoint datang dari peramban, tetapi server yang melakukan POST ke
     * alamat itu. Tanpa batasan ini, admin yang jahat (atau akunnya diambil
     * alih) dapat mendaftarkan alamat internal dan memakai server toko untuk
     * memanggilnya.
     */
    public static function endpointAman(string $endpoint): bool
    {
        $bagian = parse_url($endpoint);

        if (! is_array($bagian)
            || strtolower((string) ($bagian['scheme'] ?? '')) !== 'https'
            || filled($bagian['user'] ?? null)
            || filled($bagian['pass'] ?? null)) {
            return false;
        }

        $port = $bagian['port'] ?? null;

        if ($port !== null && (int) $port !== 443) {
            return false;
        }

        $host = strtolower((string) ($bagian['host'] ?? ''));

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $persis = [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            'push.services.mozilla.com',
        ];

        if (in_array($host, $persis, true)) {
            return true;
        }

        foreach (['.notify.windows.com', '.push.apple.com'] as $akhiran) {
            if (str_ends_with($host, $akhiran)) {
                return true;
            }
        }

        return false;
    }

    public static function siap(): bool
    {
        return filled(config('services.vapid.public_key'))
            && filled(config('services.vapid.private_key'));
    }

    /**
     * Kirim ke satu pengguna (admin), lewat semua perangkatnya.
     *
     * @return array{push: int, surel: bool, alasan: string|null}
     */
    public static function ke(User $pengguna, string $judul, string $isi, string $url = ''): array
    {
        $hasil = ['push' => 0, 'surel' => false, 'alasan' => null];

        if (! self::siap()) {
            $hasil['alasan'] = 'kunci VAPID belum dipasang';

            return $hasil;
        }

        $langganan = PushSubscription::query()
            ->where('user_id', $pengguna->getKey())
            ->limit(self::KUNCI_BATAS_PER_KIRIM)
            ->get();

        if ($langganan->isEmpty()) {
            $hasil['alasan'] = 'belum ada perangkat berlangganan';

            return $hasil;
        }

        $muatan = json_encode([
            'title' => $judul,
            'body' => $isi,
            // Alamat yang dibuka saat notifikasi diklik. Dibatasi ke panel:
            // notifikasi panel tidak boleh membuka halaman depan.
            'url' => $url !== '' ? $url : route('admin.dashboard', absolute: false),
            'icon' => '/ikon-pwa-192.png',
            'badge' => '/ikon-pwa-192.png',
            'tag' => 'panel-admin',
            // Notifikasi dengan tag sama saling menimpa: sepuluh pesanan baru
            // menghasilkan sepuluh notifikasi bertumpuk, bukan satu yang
            // menyebut jumlahnya.
            'renotify' => true,
        ], JSON_UNESCAPED_UNICODE);

        $push = new WebPush([
            'VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        // queueNotification() mengembalikan void — tidak ada hasil per
        // perangkat untuk disimpan. Laporan baru muncul di flush(), dan satu-
        // satunya penghubung yang stabil adalah endpoint langganan.
        $perEndpoint = [];

        foreach ($langganan as $satu) {
            // Langganan lama yang endpoint-nya tidak aman dilewati, bukan
            // dikirimi. Baris seperti itu seharusnya tidak pernah dibuat lagi.
            if (! self::endpointAman((string) $satu->endpoint)) {
                continue;
            }

            $perEndpoint[$satu->endpoint] = $satu;

            $langgananPush = Subscription::create([
                'endpoint' => $satu->endpoint,
                'publicKey' => $satu->public_key,
                'authToken' => $satu->auth_token,
            ]);

            $push->queueNotification($langgananPush, $muatan ?: '');
        }

        // Satu proses untuk semua perangkat: koneksi ke layanan push dipakai
        // bersama, bukan satu perangkat satu koneksi.
        foreach ($push->flush() as $laporan) {
            $satu = $perEndpoint[$laporan->getEndpoint()] ?? null;

            if ($satu === null) {
                continue;
            }

            if ($laporan->isSuccess()) {
                $hasil['push']++;
                $satu->forceFill(['last_used_at' => now()])->save();

                continue;
            }

            // 404/410: langganan sudah tidak berlaku. Dibuang supaya notifikasi
            // berikutnya tidak menyia-nyiakan waktu ke alamat kosong.
            if ($laporan->isSubscriptionExpired()) {
                $satu->delete();

                continue;
            }
        }

        if ($hasil['push'] === 0) {
            $hasil['alasan'] = 'tidak ada perangkat yang menerima push';

            return $hasil;
        }

        return $hasil;
    }

    /** Ringkasan saja: "ada 3 notifikasi baru" dengan tautannya. */
    public static function keSemuaAdmin(string $judul, string $isi, string $url = ''): void
    {
        User::query()
            ->where('role', 'super_admin')
            ->get()
            ->each(function (User $admin) use ($judul, $isi, $url): void {
                $hasil = self::ke($admin, $judul, $isi, $url);

                // Push tidak sampai? Surel sebagai cadangan — kecuali admin
                // mematikannya. Kekecewaan penting, tetapi tidak boleh
                // mengganggu halaman yang memicunya, sehingga jalur surel
                // diletakkan di belakang.
                if ($hasil['push'] === 0 && SakelarNotifikasi::kirimAktif('push_surel_cadangan')) {
                    self::kirimSurel($admin, $judul, $isi, $url);
                }
            });
    }

    private static function kirimSurel(User $admin, string $judul, string $isi, string $url = ''): void
    {
        if (blank($admin->email)) {
            return;
        }

        // Antrean: mengirim surel di tengah permintaan membuat halaman menunggu
        // SMTP. Notifikasi yang datang terlambat jauh lebih baik daripada
        // halaman yang ikut tertahan.
        Mail::raw(
            $isi.PHP_EOL.PHP_EOL.'Buka panel: '.url($url !== '' ? $url : '/admin'),
            function ($pesan) use ($admin, $judul): void {
                $pesan->to($admin->email, $admin->name)
                    ->subject('[Panel] '.$judul);
            },
        );
    }
}
