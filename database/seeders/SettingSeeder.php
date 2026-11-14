<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\DefinisiPengaturan;
use App\Support\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Seluruh kunci pengaturan pada PRD Bagian 7.4.
 *
 * Idempoten: baris yang sudah ada diperbarui metadatanya, tetapi nilai yang
 * sudah diisi admin tidak ditimpa. Definisi kuncinya ada di
 * App\Support\DefinisiPengaturan supaya formulir panel memakai daftar yang
 * sama ketika membuat baris kosong pada instalasi baru.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $baris = DefinisiPengaturan::semua();

        foreach ($baris as $b) {
            [$key, $value, $group, $type, $label, $encrypted, $public] = $b;
            $options = $b[7] ?? null;

            // is_encrypted HARUS diisi sebelum value.
            //
            // Cast NilaiPengaturan memutuskan enkripsi dengan membaca
            // $attributes['is_encrypted']. Laravel mengisi atribut sesuai urutan
            // larik, jadi bila `value` lebih dulu, cast belum melihat tanda
            // enkripsi dan nilainya tersimpan sebagai teks biasa — sementara
            // `is_encrypted` tetap tercatat true. Akibatnya baris itu tidak
            // dapat dibaca lagi: cast mencoba mendekripsi teks biasa, gagal,
            // lalu mengembalikan null. Rekening transfer tampak kosong padahal
            // isinya ada di basis data.
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            $setting->fill([
                'group_name' => $group,
                'type' => $type,
                'label' => $label,
                'options_json' => $options,
                'is_encrypted' => $encrypted,
                'is_public' => $public,
            ]);

            if (! $setting->exists) {
                $setting->value = $value;
            }

            $setting->save();
        }

        Pengaturan::lupakan();

        $this->command?->info('SettingSeeder: '.count($baris).' kunci pengaturan.');
    }
}
