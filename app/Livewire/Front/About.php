<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\CacheKatalog;
use App\Support\DataTerstruktur;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class About extends Component
{
    public function render(): View
    {
        $toko = Pengaturan::instance();

        return view('livewire.front.about', [
            'angka' => CacheKatalog::ingat(
                'statistik.toko',
                [],
                600,
                fn () => [
                    'produk' => Product::query()->terbit()->count(),
                    'pesanan' => Order::query()->where('status', 'completed')->count(),
                    'kategori' => Category::query()->aktif()->count(),
                ],
            ),
        ])->title(__('site.nav.about').' · '.$toko->teks('site_name'))
            ->layoutData([
                /* Halaman ini tidak punya canonical sama sekali, padahal
                   /tentang bisa dibuka lewat beberapa URL (dengan parameter
                   pelacakan). Tanpa canonical, mesin pencari melihatnya
                   sebagai beberapa halaman berbeda berisi isi yang sama.

                   JSON-LD-nya menyatakan entitas toko — inilah halaman yang
                   paling mungkin dikutip mesin jawaban ketika seseorang
                   bertanya "toko knalpot di Yogyakarta yang mana?". */
                'kanonikal' => route('front.about'),
                'ringkasanAi' => $toko->teks('site_description', __('site.brand.tagline')),
                'kepala' => (function (): string {
                    $json = json_encode(
                        [
                            '@context' => 'https://schema.org',
                            '@graph' => [DataTerstruktur::toko()],
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    );

                    $json = str_replace(['</', '<!--'], ['<\/', '<\!--'], (string) $json);

                    return '<script type="application/ld+json">'.$json.'</script>';
                })(),
            ]);
    }
}
