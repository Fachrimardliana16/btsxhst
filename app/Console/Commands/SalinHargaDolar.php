<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\HargaDolar;
use Illuminate\Console\Command;

/**
 * Menyalin harga rupiah ke kolom dolar.
 *
 * Dijalankan sekali setelah situs beralih ke dolar, lalu diulang setiap kali
 * harga rupiah diubah atau kurs bergerak jauh. Aman dijalankan berkali-kali:
 * baris yang sudah punya harga dolar dilewati kecuali `--paksa` diberikan,
 * sehingga harga yang sudah disetel manual di panel tidak tertimpa tanpa
 * sengaja.
 */
class SalinHargaDolar extends Command
{
    protected $signature = 'harga:salin-dolar
                            {--paksa : Timpa harga dolar yang sudah terisi}
                            {--kurs= : Kurs rupiah per dolar (bawaan: kurs hari ini)}';

    protected $description = 'Salin harga rupiah produk dan varian ke kolom harga dolar';

    public function handle(): int
    {
        $kurs = $this->option('kurs') !== null
            ? (float) $this->option('kurs')
            : HargaDolar::kurs();

        if ($kurs <= 0) {
            $this->error('Kurs tidak sah. Isi dengan --kurs=16000.');

            return self::FAILURE;
        }

        $paksa = (bool) $this->option('paksa');

        $this->info(sprintf('Kurs dipakai: Rp %s per USD', number_format($kurs, 0, ',', '.')));

        $produk = 0;
        $varian = 0;

        foreach (Product::query()->cursor() as $p) {
            $sudahAda = (float) ($p->base_price_usd ?? 0) > 0;

            if (! $sudahAda || $paksa) {
                $p->base_price_usd = round((float) $p->base_price / $kurs, 2);
                $p->save();
                $produk++;
            }

            foreach ($p->variants as $v) {
                $adaPenyesuaian = (float) ($v->price_adjustment ?? 0) !== 0.0;
                $sudahAdaVarian = $v->price_adjustment_usd !== null;

                if (! $sudahAdaVarian || $paksa) {
                    // Penyesuaian nol disimpan sebagai 0.00, bukan null:
                    // null berarti "belum disalin", dan itu membuat tampilan
                    // jatuh ke konversi kurs untuk varian yang sebenarnya
                    // sudah benar.
                    $v->price_adjustment_usd = $adaPenyesuaian
                        ? round((float) $v->price_adjustment / $kurs, 2)
                        : 0.0;

                    $v->save();
                    $varian++;
                }
            }
        }

        $this->info("Selesai: {$produk} produk dan {$varian} varian disalin.");

        return self::SUCCESS;
    }
}
