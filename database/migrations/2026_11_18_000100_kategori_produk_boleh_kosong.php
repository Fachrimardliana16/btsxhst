<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori boleh dihapus walau masih dipakai produk.
 *
 * Sebelumnya `products.category_id` wajib terisi, jadi menghapus kategori
 * harus ditolak selama ada produk yang menunjuk ke sana. Penolakan itu benar
 * secara teknis tetapi salah secara pemakaian: pemilik toko yang ingin
 * merapikan kategori terpaksa memindahkan produk satu per satu, dan produk
 * yang kategorinya belum ditentukan tidak punya tempat sama sekali.
 *
 * Kolomnya kini boleh kosong. Produk tanpa kategori tetap tampil di daftar
 * produk dan halaman detail — tampilan sudah memakai `?->` di semua tempat
 * yang membaca relasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Kolom dikembalikan wajib terisi. Baris yang sudah kosong harus
        // diberi kategori lebih dulu, kalau tidak migrasi mundur ini gagal
        // dengan galat NOT NULL — dan itu memang yang diinginkan: lebih baik
        // berhenti daripada menebak kategori untuk produk.
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }
};
