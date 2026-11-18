<?php

namespace App\Livewire\Admin;

use App\Events\NotifikasiPanelMuncul;
use App\Support\NotifikasiAdmin;
use App\Support\SakelarNotifikasi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

/**
 * Lonceng notifikasi pada kepala panel.
 *
 * Isinya dihitung saat dirender, bukan disimpan di tabel tersendiri. Notifikasi
 * yang disimpan akan basi — misalnya "stok habis" yang tetap tampil setelah
 * stok diisi ulang — dan menyimpan salinan data yang sudah ada berarti dua
 * sumber kebenaran yang bisa berbeda.
 *
 * Yang disimpan hanyalah tanda "sudah dilihat" per butir per pengguna
 * (notification_reads), beserta sidik keadaan saat dilihat. Lencana pada ikon
 * menghitung butir yang BELUM dilihat saja: satu butir yang dibuka hilang dari
 * hitungan, dan bila isinya berubah nanti butir itu muncul lagi.
 *
 * Komponen ini menyegarkan diri saat menerima peristiwa 'notifikasi-diperbarui',
 * yang dikirim komponen lain setelah tindakan yang mengubah angkanya.
 */
class LoncengNotifikasi extends KomponenAdmin
{
    public bool $buka = false;

    public function alihkan(): void
    {
        $this->buka = ! $this->buka;
    }

    /**
     * Tandai satu butir sudah dilihat, lalu arahkan ke halamannya.
     *
     * Butir tetap ditandai walaupun tautannya dibuka: yang dijanjikan lencana
     * adalah "butir ini belum kamu buka", bukan "butir ini belum kamu tangani".
     * Keadaannya menyusul sendiri — stok yang diisi ulang menghapus butirnya,
     * pesanan yang dikirim mengubah angkanya menjadi belum dilihat lagi.
     */
    public function bukaButir(string $kunci): void
    {
        NotifikasiAdmin::tandaiDilihat($kunci, auth()->user());
    }

    public function tandaiSemua(): void
    {
        $n = NotifikasiAdmin::tandaiSemuaDilihat(auth()->user());

        if ($n > 0) {
            $this->dispatch('notifikasi-diperbarui');
        }
    }

    #[On('notifikasi-diperbarui')]
    public function segarkan(): void
    {
        // Render ulang sudah cukup; isinya dihitung dari kueri.
    }

    /**
     * Jumlah notifikasi belum dilihat pada render SEBELUMNYA.
     *
     * Push hanya dikirim ketika angkanya NAIK. Penanda ini disimpan di sesi,
     * bukan di properti publik komponen: properti Livewire kembali ke nilai
     * awalnya pada setiap muat halaman, sehingga notifikasi yang sama dikirim
     * ulang di setiap navigasi admin.
     */
    private function jumlahSebelumnya(): int
    {
        return (int) session('admin.lonceng.diberitahukan', 0);
    }

    /**
     * Kirim push hanya bila jumlah notifikasi yang belum dilihat BERTAMBAH.
     *
     * Angka disimpan per sesi pengguna (bukan per permintaan) agar lonceng
     * yang dirender ulang oleh Livewire — atau admin yang membuka panel di
     * tab lain — tidak memicunya berulang kali untuk angka yang sama.
     */
    private function beriTahu(Collection $belum): void
    {
        $jumlah = $belum->count();

        if ($jumlah <= $this->jumlahSebelumnya()) {
            return;
        }

        $pengguna = auth()->user();

        if ($pengguna === null || ! SakelarNotifikasi::kirimAktif('push_perangkat')) {
            return;
        }

        // Batas bawah 1: lonceng yang sudah kosong tidak perlu diberitahukan.
        $pintar = $belum->take(3)->pluck('teks')->implode(' · ');

        NotifikasiPanelMuncul::dispatch(
            $jumlah,
            $pintar !== '' ? $pintar : 'Something new needs your attention.',
            route('admin.dashboard'),
        );

        session(['admin.lonceng.diberitahukan' => $jumlah]);
    }

    public function render(): View
    {
        $semua = NotifikasiAdmin::semua(auth()->user());
        $belum = $semua->where('dilihat', false);

        $this->beriTahu($belum);

        return view('livewire.admin.lonceng-notifikasi', [
            'butir' => $semua,
            'belum' => $belum->count(),
            // Mendesak hanya dihitung dari yang belum dilihat: cincin berdenyut
            // pada ikon adalah ajakan membuka, bukan hiasan.
            'mendesak' => $belum->where('nada', 'danger')->count(),
        ]);
    }
}
