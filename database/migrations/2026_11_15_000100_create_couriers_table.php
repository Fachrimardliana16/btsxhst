<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Daftar ekspedisi sebagai tabel master, bukan satu nilai JSON di pengaturan.
 *
 * Sebelumnya daftar kurir hanya sebaris JSON (`shipping_couriers`) yang harus
 * disunting sebagai teks, tanpa tempat menyimpan tautan pelacakan per kurir dan
 * tanpa urutan yang dapat diatur. Tabel ini menggantikannya.
 *
 * Isi lama TIDAK dihapus dari tabel settings: migrasi ini hanya menyalinnya ke
 * baris couriers. Bila hasilnya perlu dibatalkan, nilai lamanya masih utuh.
 * Daftar bawaan dipakai bila pengaturan lama kosong, supaya checkout tidak
 * kehilangan pilihan kurir setelah migrasi.
 */
return new class extends Migration
{
    /** Pola tautan resmi untuk nama kurir yang sudah dikenal. */
    private const POLA = [
        'jne' => 'https://www.jne.co.id/id/tracking/trace/%s',
        'j&t express' => 'https://jet.co.id/track?awb=%s',
        'jnt' => 'https://jet.co.id/track?awb=%s',
        'j&t' => 'https://jet.co.id/track?awb=%s',
        'sicepat' => 'https://www.sicepat.com/checkAwb/%s',
        'pos indonesia' => 'https://www.posindonesia.co.id/id/tracking?q=%s',
        'anteraja' => 'https://anteraja.id/tracking?awb=%s',
        'lion parcel' => 'https://lionparcel.com/track/%s',
        'ninja xpress' => 'https://www.ninjaxpress.co/id-id/tracking?awb=%s',
    ];

    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('name');
            $table->string('code')->unique();

            // Tautan pelacakan resmi; "%s" diganti nomor resi. Boleh kosong
            // bila kurir tidak punya halaman pelacakan publik.
            $table->string('tracking_url', 500)->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'display_order'], 'idx_couriers_aktif');
        });

        $nama = $this->namaDariPengaturan();
        $bawaan = trim((string) DB::table('settings')->where('key', 'shipping_courier_default')->value('value'));

        $dipakai = [];
        $urutan = 0;
        $adaBawaan = false;

        foreach ($nama as $satu) {
            $kode = Str::slug($satu);

            if ($kode === '') {
                continue;
            }

            $kandidat = $kode;
            $nomor = 2;

            while (isset($dipakai[$kandidat])) {
                $kandidat = $kode.'-'.$nomor;
                $nomor++;
            }

            $dipakai[$kandidat] = true;
            $urutan++;

            $isBawaan = ! $adaBawaan && $satu === $bawaan;
            $adaBawaan = $adaBawaan || $isBawaan;

            DB::table('couriers')->insert([
                'ulid' => (string) Str::ulid(),
                'name' => $satu,
                'code' => $kandidat,
                'tracking_url' => self::POLA[mb_strtolower($satu)] ?? null,
                'display_order' => $urutan,
                'is_active' => true,
                'is_default' => $isBawaan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Belum ada yang cocok dengan kurir bawaan lama: pakai baris pertama
        // supaya checkout tetap punya nilai terpilih.
        if (! $adaBawaan) {
            DB::table('couriers')->orderBy('display_order')->limit(1)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }

    /** @return array<int, string> */
    private function namaDariPengaturan(): array
    {
        $mentah = DB::table('settings')->where('key', 'shipping_couriers')->value('value');
        $daftar = json_decode((string) $mentah, true);

        if (! is_array($daftar)) {
            $daftar = [];
        }

        $bersih = [];

        foreach ($daftar as $satu) {
            if (! is_string($satu)) {
                continue;
            }

            $satu = trim($satu);

            if ($satu !== '' && ! in_array($satu, $bersih, true)) {
                $bersih[] = $satu;
            }
        }

        return $bersih !== [] ? $bersih : ['JNE', 'J&T Express', 'SiCepat', 'Pos Indonesia'];
    }
};
