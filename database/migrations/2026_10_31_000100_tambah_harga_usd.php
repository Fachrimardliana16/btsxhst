<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga USD per produk dan per varian.
 *
 * ── KENAPA DUA KOLOM, BUKAN SATU ───────────────────────────────────────
 * Rupiah tetap HARGA INDUK. Seluruh perhitungan pesanan, pajak, diskon, dan
 * laporan penjualan bekerja dalam rupiah; mengubahnya menjadi dua mata uang
 * berarti setiap laporan harus memilih kurs, dan angka di dashboard berubah
 * setiap hari tanpa ada transaksi.
 *
 * USD di sini adalah harga JUAL yang ditentukan pemilik toko sendiri untuk
 * pembeli luar negeri. Ia bukan hasil konversi otomatis: toko yang menjual
 * ke luar negeri biasanya membulatkan sendiri ($280, bukan $278,43) dan
 * menambahkan biaya yang tidak terlihat di harga rupiah.
 *
 * Kolomnya NULLABLE dengan sengaja. NULL berarti "belum diisi", dan pembeli
 * yang memilih USD akan melihat rupiah sebagai gantinya — lebih baik daripada
 * menampilkan $0 atau angka karangan hasil konversi yang tidak pernah
 * disetujui pemilik toko.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // presisi 12,2 cukup untuk $999.999.999,99 — jauh di atas harga
            // knalpot mana pun, dan tetap memberi ruang bila kelak ada produk
            // lain.
            $table->decimal('base_price_usd', 12, 2)->nullable()->after('base_price');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            // Selisih harga varian dalam USD, mengikuti pola price_adjustment
            // rupiah. Boleh negatif (varian lebih murah).
            $table->decimal('price_adjustment_usd', 12, 2)->nullable()->after('price_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('base_price_usd');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('price_adjustment_usd');
        });
    }
};
