<?php

namespace App\Support;

use App\Models\Setting;
use App\Support\Pengaturan as Pembaca;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Jembatan antara baris `settings` dan komponen pengaturan.
 *
 * Tiga hal yang ditangani di sini:
 *
 *  1. Tipe "boolean" disimpan sebagai teks "0"/"1". Livewire butuh bool
 *     sungguhan pada properti wire:model, jadi konversinya dilakukan sekali
 *     di sini, bukan di setiap sambungan view.
 *
 *  2. Pengaturan bertipe "select" divalidasi terhadap options_json, sehingga
 *     nilai di luar daftar tidak pernah masuk basis data.
 *
 *  3. Kredensial terenkripsi tidak pernah dikembalikan apa adanya. Yang
 *     dikembalikan hanya penanda apakah nilainya sudah pernah diisi.
 */
class KomponenPengaturan
{
    public const TIPE_BOOLEAN = 'boolean';

    /** Kunci yang nilainya tidak boleh dikirim balik ke antarmuka. */
    public const KUNCI_RAHASIA = [
        'paypal_client_id',
        'paypal_client_secret',
        'manual_bank_accounts',
    ];

    /**
     * Pastikan baris untuk satu grup ada, tanpa mengisi nilai apa pun.
     *
     * Formulir membaca kolomnya dari tabel `settings`. Instalasi baru yang
     * belum di-seed karena itu menampilkan tab kosong. Baris yang belum ada
     * dibuat dengan nilai null — bukan nilai contoh — sehingga pemilik toko
     * mulai dari keadaan kosong dan mengisinya sendiri lewat panel.
     *
     * Baris yang sudah ada tidak disentuh: nilai maupun metadatanya dibiarkan,
     * supaya membuka halaman ini tidak pernah menimpa pengaturan yang berlaku.
     */
    public static function pastikanSkema(string $grup): void
    {
        $ada = Setting::query()
            ->where('group_name', $grup)
            ->pluck('key')
            ->flip();

        foreach (DefinisiPengaturan::untukGrup($grup) as $b) {
            [$key, , , $type, $label, $encrypted, $public] = $b;
            $options = $b[7] ?? null;

            if ($ada->has($key)) {
                continue;
            }

            $setting = new Setting;
            $setting->fill([
                'key' => $key,
                'group_name' => $grup,
                'type' => $type,
                'label' => $label,
                'options_json' => $options,
                'is_encrypted' => $encrypted,
                'is_public' => $public,
            ]);

            // Enkripsi dibaca dari atribut, jadi is_encrypted sudah terisi
            // sebelum value ditetapkan (lihat catatan di SettingSeeder).
            $setting->value = null;
            $setting->save();
        }
    }

    /**
     * Nilai formulir satu grup pengaturan.
     *
     * @return array<string, mixed>
     */
    public static function formulir(string $grup): array
    {
        $hasil = [];

        foreach (self::barisGrup($grup) as $setting) {
            if ($setting->is_encrypted) {
                // Properti tetap ada dan bernama sama supaya formulir tidak
                // perlu bercabang, tetapi isinya selalu kosong.
                $hasil[$setting->key] = '';
                $hasil[self::kunciTerisi($setting->key)] = filled($setting->value);

                continue;
            }

            $hasil[$setting->key] = $setting->type === self::TIPE_BOOLEAN
                ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN)
                : ($setting->value ?? '');
        }

        return $hasil;
    }

    public static function kunciTerisi(string $kunci): string
    {
        return '__terisi_'.$kunci;
    }

    /**
     * Aturan validasi untuk satu grup, dibangun dari metadata kolom sehingga
     * menambah pengaturan baru tidak menuntut perubahan di komponen.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function aturan(string $grup): array
    {
        $aturan = [];

        foreach (self::barisGrup($grup) as $setting) {
            // Rahasia tidak ikut divalidasi: kolomnya selalu kosong di formulir.
            if ($setting->is_encrypted) {
                continue;
            }

            $dasar = ['nullable'];

            $aturan[$setting->key] = match ($setting->type) {
                'email' => [...$dasar, 'email', 'max:120'],
                'url' => [...$dasar, 'url', 'max:255', 'regex:/^https:\/\//i'],
                'number' => [...$dasar, 'numeric'],
                self::TIPE_BOOLEAN => [...$dasar, 'boolean'],
                'select' => [...$dasar, 'in:'.implode(',', $setting->options_json ?? [])],
                'textarea' => [...$dasar, 'string', 'max:5000'],
                default => [...$dasar, 'string', 'max:255'],
            };
        }

        // Kolom rahasia tetap divalidasi panjangnya bila diisi, tanpa pernah
        // dibandingkan dengan nilai tersimpan.
        foreach (self::barisGrup($grup)->where('is_encrypted', true) as $setting) {
            $aturan[$setting->key] = ['nullable', 'string', 'max:255', 'bail'];
        }

        return $aturan;
    }

    /** Label ramah untuk pesan galat, memakai kolom label. */
    public static function label(string $grup): array
    {
        return self::barisGrup($grup)->pluck('label', 'key')->all();
    }

    /**
     * Nilai yang siap ditulis, dengan rahasia kosong dibuang agar tidak
     * menimpa kredensial yang sudah tersimpan.
     *
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed>
     */
    public static function saringKosong(array $nilai): array
    {
        $hasil = [];

        // Semua kolom terenkripsi, bukan hanya yang tercantum di
        // KUNCI_RAHASIA. Daftar manual mudah tertinggal saat pengaturan baru
        // ditambahkan — dan yang tertinggal akan terhapus saat formulirnya
        // disimpan dengan kolom rahasia dibiarkan kosong.
        $terenkripsi = Setting::query()
            ->where('is_encrypted', true)
            ->pluck('key')
            ->all();

        foreach ($nilai as $kunci => $isi) {
            if (str_starts_with($kunci, '__')) {
                continue;
            }

            if (blank($isi)
                && (in_array($kunci, self::KUNCI_RAHASIA, true) || in_array($kunci, $terenkripsi, true))) {
                // Dibiarkan kosong berarti "jangan ubah", bukan "kosongkan".
                // Mengosongkan kredensial dilakukan lewat tombol hapus khusus.
                continue;
            }

            $hasil[$kunci] = $isi;
        }

        return $hasil;
    }

    /** Apakah grup ini punya setidaknya satu kolom rahasia. */
    public static function punyaRahasia(string $grup): bool
    {
        return self::barisGrup($grup)->where('is_encrypted', true)->isNotEmpty();
    }

    /** Hapus satu kredensial tersimpan. */
    public static function hapus(string $kunci, ?int $userId = null): void
    {
        $setting = Setting::query()->where('key', $kunci)->first();

        if ($setting === null || ! $setting->is_encrypted) {
            return;
        }

        $setting->value = null;
        $setting->updated_by = $userId ?? auth()->id();
        $setting->save();

        CatatanAktivitas::catat('setting.rahasia.dihapus', [$setting->key => CatatanAktivitas::PENANDA_RAHASIA], $userId, $setting);

        Pembaca::segarkan();
    }

    /**
     * Nilai asli satu kunci, termasuk yang terenkripsi.
     *
     * Dipakai hanya di sisi server — misalnya saat memanggil PayPal. Tidak
     * pernah dipanggil dari render view.
     */
    public static function mentah(string $kunci): ?string
    {
        try {
            $setting = Setting::query()->where('key', $kunci)->first();
        } catch (Throwable) {
            return null;
        }

        if ($setting === null) {
            return null;
        }

        $nilai = $setting->value;

        return filled($nilai) ? (string) $nilai : null;
    }

    /**
     * Baris satu grup, HANYA yang masih didefinisikan di DefinisiPengaturan.
     *
     * `pastikanSkema()` bersifat menambah: ia membuat baris yang belum ada,
     * tetapi tidak pernah menghapus baris yang definisinya sudah dibuang dari
     * kode. Dulu daftar kurir disimpan sebagai dua baris settings
     * (`shipping_couriers` dan `shipping_courier_default`); setelah pindah ke
     * tabel `couriers`, barisnya tetap tinggal di basis data produksi dan tab
     * yang membaca grup itu masih menampilkannya sebagai "Courier list" dan
     * "Default courier" — kolom tanpa kode yang mengurusinya.
     *
     * Menyaring di sini, bukan menghapus barisnya, membuat definisi kode
     * menjadi satu-satunya sumber kebenaran formulir. Nilai lama tetap utuh
     * bila suatu saat definisinya dikembalikan.
     *
     * @return Collection<int, Setting>
     */
    public static function barisGrup(string $grup): Collection
    {
        $kunci = array_column(DefinisiPengaturan::untukGrup($grup), 0);

        if ($kunci === []) {
            return collect();
        }

        return Setting::query()
            ->where('group_name', $grup)
            ->whereIn('key', $kunci)
            ->orderBy('id')
            ->get();
    }
}
