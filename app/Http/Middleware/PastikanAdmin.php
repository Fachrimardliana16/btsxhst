<?php

namespace App\Http\Middleware;

use App\Enums\TipeKejadianKeamanan;
use App\Support\Keamanan;
use App\Support\SesiAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang panel admin.
 *
 * Pemeriksaan dilakukan di server, bukan dengan menyembunyikan tombol di
 * antarmuka. Peran Pelanggan yang menembus rute admin ditolak dan dicatat.
 */
class PastikanAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Blokir alamat diperiksa lebih dulu, sebelum identitas.
        //
        // Termasuk untuk pengguna yang sudah masuk. Blokir berlaku satu jam
        // dan bertambah sendiri saat ambang kegagalan terlampaui; sesi yang
        // masih hidup dari alamat yang sedang diblokir justru keadaan yang
        // paling perlu ditutup.
        //
        // Karena panel ikut tertutup, halaman buka-blokir di dalamnya tidak
        // dapat dicapai saat blokir sedang berlaku. Jalan daruratnya ada di
        // luar panel: perintah `php artisan iksan:buka-blokir`, yang hanya
        // dapat dijalankan oleh orang yang memang punya akses ke server.
        if (Keamanan::ipDiblokir($request)) {
            Keamanan::catat(TipeKejadianKeamanan::IpDiblokir, $request, $request->user()?->email, $request->user()?->id, 'Alamat diblokir mencoba membuka panel');

            abort(403, 'Access from this network is temporarily blocked.');
        }

        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        $peran = $user->role;

        if (! $peran->bisaMasukPanel()) {
            Keamanan::catat(TipeKejadianKeamanan::AksesTidakSah, $request, $user->email, $user->id, 'Pelanggan mencoba membuka '.$request->path());

            // Ditandai supaya middleware CatatKeamanan tidak mencatat
            // kejadian yang sama untuk kedua kalinya.
            return CatatKeamanan::tandaiSudahDicatat(
                response('This account has no access to the admin panel.', 403)
            );
        }

        if ($user->is_blocked) {
            abort(403, 'This account is blocked.');
        }

        // Sesi admin berumur pendek. Sesi yang terlalu lama dibiarkan terbuka
        // adalah risiko terbesar pada panel yang hanya dipakai di satu meja.
        if (SesiAdmin::kedaluwarsa($request->session())) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('admin.login'))->with('galat', 'Your session has ended. Please sign in again.');
        }

        SesiAdmin::sentuh($request->session());

        return $next($request);
    }
}
