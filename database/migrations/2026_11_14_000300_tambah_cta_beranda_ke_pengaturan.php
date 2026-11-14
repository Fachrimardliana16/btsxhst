<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah isi panel CTA di beranda ke tab Konten Halaman.
 *
 * Sebelumnya judul dan paragraf panel ini ditulis di berkas terjemahan,
 * sehingga panel tetap muncul walaupun toko belum punya apa pun untuk
 * ditawarkan. Nilai awal kosong: panel hilang sampai admin mengisinya.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $baris = [
        ['cta_judul', 'Judul panel CTA beranda', 'text'],
        ['cta_isi', 'Paragraf panel CTA beranda', 'textarea'],
    ];

    public function up(): void
    {
        $sekarang = now();

        $isi = array_map(fn (array $b): array => [
            'key' => $b[0],
            'value' => null,
            'group_name' => 'konten',
            'type' => $b[2],
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
