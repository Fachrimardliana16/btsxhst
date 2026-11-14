<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus pengaturan mata uang rupiah.
 *
 * Toko menjual dalam dolar: harga produk, kupon, dan pesanan sudah memakai
 * USD. Dua kunci lama ini hanya menyisakan pilihan yang tidak berlaku lagi —
 * `paypal_currency` yang bisa disetel IDR dan `currency_symbol` yang menimpa
 * simbol tampilan. Membiarkannya berarti admin masih dapat memilih IDR dari
 * panel, lalu tagihan PayPal dan harga di halaman tidak lagi sejalan.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $kunci = ['paypal_currency', 'currency_symbol'];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', $this->kunci)->delete();
    }

    public function down(): void
    {
        $sekarang = now();

        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'paypal_currency',
                'value' => 'USD',
                'group_name' => 'pembayaran',
                'type' => 'select',
                'label' => 'Currency',
                'options_json' => json_encode(['USD']),
                'is_encrypted' => false,
                'is_public' => false,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
            [
                'key' => 'currency_symbol',
                'value' => '$',
                'group_name' => 'lanjutan',
                'type' => 'text',
                'label' => 'Currency symbol',
                'options_json' => null,
                'is_encrypted' => false,
                'is_public' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
        ]);
    }
};
