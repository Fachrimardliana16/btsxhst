<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menerjemahkan catatan riwayat pesanan lama ke bahasa Inggris.
 *
 * Catatan ini ditulis ke order_status_history saat kejadiannya berlangsung,
 * jadi mengubah teks di kode hanya berlaku untuk pesanan baru. Halaman Lacak
 * Pesanan menampilkan catatan apa adanya, dan seluruh antarmuka situs memakai
 * bahasa Inggris — catatan berbahasa Indonesia di sana terlihat seperti sisa
 * terjemahan yang belum selesai.
 *
 * Hanya teks yang PERSIS sama dengan catatan bawaan aplikasi yang diganti.
 * Catatan yang ditulis admin sendiri tidak disentuh.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $peta = [
        'Pesanan dibuat dari halaman checkout.' => 'Order placed from the checkout page.',
        'Pembayaran diterima.' => 'Payment received.',
        'Pembayaran PayPal diterima' => 'PayPal payment received.',
        'Pembayaran manual diverifikasi' => 'Manual payment verified.',
        'Pesanan sedang disiapkan di bengkel.' => 'Order is being prepared.',
        'Paket diserahkan ke kurir.' => 'Package handed to the courier.',
        'Paket diterima pelanggan.' => 'Package received by the customer.',
        'Dibatalkan otomatis: belum dibayar sampai batas waktu.' => 'Cancelled automatically: payment was not received before the deadline.',
        'Dibatalkan karena pembayaran tidak diterima dalam 24 jam.' => 'Cancelled because payment was not received within 24 hours.',
    ];

    public function up(): void
    {
        foreach ($this->peta as $lama => $baru) {
            DB::table('order_status_history')
                ->where('note', $lama)
                ->update(['note' => $baru]);
        }
    }

    public function down(): void
    {
        foreach ($this->peta as $lama => $baru) {
            DB::table('order_status_history')
                ->where('note', $baru)
                ->update(['note' => $lama]);
        }
    }
};
