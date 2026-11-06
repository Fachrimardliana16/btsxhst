<?php

namespace App\Livewire\Front;

use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HalamanStatis extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        /*
         * Dua halaman hukum minimum untuk MVP.
         *
         * Isinya dulu ditulis langsung sebagai string Indonesia di kelas ini,
         * sehingga halaman Kebijakan Privasi dan Syarat & Ketentuan TETAP
         * berbahasa Indonesia walaupun pengunjung memilih English. Sekarang
         * isinya tinggal di lang/{locale}/site.php di bawah kunci `halaman`,
         * dan yang tersisa di sini hanya daftar slug yang sah.
         *
         * Email kontak disisipkan lewat placeholder :email, bukan dirangkai
         * dengan titik, supaya kalimatnya tetap utuh di kedua bahasa —
         * urutan kata berbeda antara Indonesia dan Inggris.
         */
        $sah = ['kebijakan-privasi', 'syarat-ketentuan'];

        abort_unless(in_array($this->slug, $sah, true), 404);

        /* Slug memakai tanda hubung ('kebijakan-privasi'), sedangkan kunci
         * terjemahan memakai garis bawah ('kebijakan_privasi'). Tanpa
         * pemetaan ini, Laravel membaca titik pada 'site.halaman.kebijakan-
         * privasi.judul' sebagai kunci bersarang dan mengembalikan string
         * mentahnya, bukan teks — halaman tampil sebagai
         * "site.halaman.kebijakan-privasi.judul". */
        $kunci = str_replace('-', '_', $this->slug);

        $judul = __('site.halaman.'.$kunci.'.judul');
        $isi = __('site.halaman.'.$kunci.'.isi', [
            'email' => (string) $toko->teks('contact_email'),
        ]);

        return view('livewire.front.halaman-statis', [
            'judul' => $judul,
            'isi' => $isi,
        ])->title($judul.' · '.$toko->teks('site_name'));
    }
}
