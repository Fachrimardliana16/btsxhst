<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan penjualan dan dasbor menyaring pesanan berdasarkan rentang tanggal
 * dan sekumpulan status pendapatan. Indeks gabungan `status, created_at`
 * tidak dapat dipakai untuk saringan tanggal saja karena status selalu berada
 * di kolom pertama. Tanpa indeks ini setiap laporan memindai seluruh tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at', 'idx_orders_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_created_at');
        });
    }
};
