<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah kolom pengaturan untuk isi halaman Tentang dan kartu Layanan
 * Bengkel di beranda.
 *
 * Sebelumnya kedua section ditulis di berkas terjemahan, sehingga pemilik
 * toko tidak dapat mengubahnya dari panel dan isinya tetap tampil walaupun
 * basis data kosong. Baris di sini sengaja bernilai kosong: section baru
 * muncul setelah admin mengisi tab "Konten Halaman".
 *
 * insertOrIgnore dipakai supaya migrasi tidak menimpa isi yang sudah diubah
 * admin bila dijalankan ulang pada basis data yang lebih lama.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $baris = [
        ['about_judul', 'text', 'Judul halaman Tentang'],
        ['about_proses', 'textarea', 'Paragraf proses (Tentang)'],
        ['about_langkah_1_judul', 'text', 'Langkah 1 — judul'],
        ['about_langkah_1_isi', 'textarea', 'Langkah 1 — isi'],
        ['about_langkah_2_judul', 'text', 'Langkah 2 — judul'],
        ['about_langkah_2_isi', 'textarea', 'Langkah 2 — isi'],
        ['about_langkah_3_judul', 'text', 'Langkah 3 — judul'],
        ['about_langkah_3_isi', 'textarea', 'Langkah 3 — isi'],
        ['layanan_judul', 'text', 'Judul section Layanan'],
        ['layanan_1_judul', 'text', 'Layanan 1 — judul'],
        ['layanan_1_isi', 'textarea', 'Layanan 1 — isi'],
        ['layanan_2_judul', 'text', 'Layanan 2 — judul'],
        ['layanan_2_isi', 'textarea', 'Layanan 2 — isi'],
        ['layanan_3_judul', 'text', 'Layanan 3 — judul'],
        ['layanan_3_isi', 'textarea', 'Layanan 3 — isi'],
    ];

    public function up(): void
    {
        $sekarang = now();

        $isi = array_map(fn (array $b): array => [
            'key' => $b[0],
            'value' => null,
            'group_name' => 'konten',
            'type' => $b[1],
            'label' => $b[2],
            'options_json' => null,
            'is_encrypted' => false,
            'is_public' => true,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ], $this->baris);

        DB::table('settings')->insertOrIgnore($isi);
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', array_column($this->baris, 0))
            ->delete();
    }
};
