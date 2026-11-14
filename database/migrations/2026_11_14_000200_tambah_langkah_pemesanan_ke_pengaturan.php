<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah kolom pengaturan untuk section "Langkah pemesanan" di beranda.
 *
 * Sebelumnya empat langkah ditulis di berkas terjemahan dan tetap tampil
 * walaupun basis data kosong. Nilai awal sengaja kosong: section hilang dari
 * beranda sampai admin mengisinya lewat tab Konten Halaman.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> */
    private array $baris = [
        ['cara_pesan_judul', 'Judul section langkah pemesanan'],
        ['cara_pesan_langkah_1', 'Langkah pemesanan 1'],
        ['cara_pesan_langkah_2', 'Langkah pemesanan 2'],
        ['cara_pesan_langkah_3', 'Langkah pemesanan 3'],
        ['cara_pesan_langkah_4', 'Langkah pemesanan 4'],
    ];

    public function up(): void
    {
        $sekarang = now();

        $isi = array_map(fn (array $b): array => [
            'key' => $b[0],
            'value' => null,
            'group_name' => 'konten',
            'type' => $b[0] === 'cara_pesan_judul' ? 'text' : 'textarea',
            'label' => $b[1],
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
