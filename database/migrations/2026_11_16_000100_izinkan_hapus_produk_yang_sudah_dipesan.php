<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mengizinkan produk yang pernah dipesan untuk dihapus.
 *
 * order_items sudah menyimpan salinan nama produk, nama varian, SKU, harga,
 * berat, dan subtotal pada saat pesanan dibuat. Riwayat pesanan, invoice,
 * surat jalan, dan laporan penjualan membaca salinan itu — bukan relasi ke
 * produk — sehingga menghapus produk tidak mengubah isi dokumen lama.
 *
 * Kolom product_id tetap ada supaya tautan "lihat produk" masih bisa dipakai
 * selama produknya ada; ia cukup menjadi NULL ketika produknya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Kembali ke restrictOnDelete hanya masuk akal bila tidak ada baris
        // yatim. Bila ada produk yang sudah telanjur dihapus, migrasi ini
        // gagal dengan sengaja: memaksakan NOT NULL akan mengisi kolom dengan
        // id produk yang tidak ada.
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }
};
