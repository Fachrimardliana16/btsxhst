<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pelacakan kiriman otomatis (Fase 2: P2-05).
 *
 * Toko tidak memiliki akun API kurir, jadi yang dikerjakan di sini dua hal yang
 * dapat dilakukan tanpa akun:
 *
 * 1. Menerjemahkan nomor resi menjadi tautan pelacakan resmi kurir, supaya
 *    pembeli tidak perlu mencari sendiri. Tautan ini yang ditampilkan di
 *    halaman lacak pesanan dan di pesan WhatsApp.
 * 2. Mengambil status terakhir dari penyedia pelacakan bila kuncinya diisi di
 *    pengaturan. Bila tidak diisi, fitur ini diam — bukan gagal, dan bukan
 *    mengarang status.
 *
 * Status yang diambil TIDAK mengubah status pesanan. Nomor resi yang sudah
 * bergerak bukan bukti barang diterima, dan pesanan yang berpindah ke "selesai"
 * karena API pihak ketiga berarti stok berkurang tanpa ada yang memeriksa.
 */
class PelacakanKurir
{
    /**
     * Pola bawaan untuk pesanan lama.
     *
     * Tautan resmi kini disimpan di tabel couriers dan dapat disunting admin.
     * Peta ini hanya cadangan: pesanan yang dibuat sebelum tabel itu ada
     * menyimpan nama kurir sebagai teks, dan namanya mungkin sudah tidak
     * terdaftar lagi di master.
     */
    private const TAUTAN = [
        'jne' => 'https://www.jne.co.id/id/tracking/trace/%s',
        'j&t express' => 'https://jet.co.id/track?awb=%s',
        'jnt' => 'https://jet.co.id/track?awb=%s',
        'sicepat' => 'https://www.sicepat.com/checkAwb/%s',
        'pos indonesia' => 'https://www.posindonesia.co.id/id/tracking?q=%s',
        'anteraja' => 'https://anteraja.id/tracking?awb=%s',
        'j&t' => 'https://jet.co.id/track?awb=%s',
        'lion parcel' => 'https://lionparcel.com/track/%s',
        'ninja xpress' => 'https://www.ninjaxpress.co/id-id/tracking?awb=%s',
    ];

    /** Tautan pelacakan kurir untuk sebuah pesanan, bila resinya ada. */
    public static function tautan(Order $pesanan): ?string
    {
        $resi = trim((string) ($pesanan->tracking_number ?? ''));
        $nama = trim((string) ($pesanan->shipping_courier ?? ''));
        $kurir = mb_strtolower($nama);

        if ($resi === '') {
            return null;
        }

        $master = Courier::query()
            ->whereRaw('lower(name) = ?', [$kurir])
            ->orWhere('code', $kurir)
            ->first();

        if ($master !== null) {
            return $master->tautan($resi);
        }

        $pola = self::TAUTAN[$kurir] ?? null;

        if ($pola === null) {
            // Kurir tidak dikenal: tautan pencarian umum, bukan tautan kosong.
            return 'https://www.google.com/search?q='.rawurlencode($nama.' '.$resi.' lacak resi');
        }

        return sprintf($pola, rawurlencode($resi));
    }

    /**
     * Status terakhir dari penyedia pelacakan.
     *
     * @return array{tersedia: bool, ringkas: string, diperbarui: ?string}
     */
    public static function status(Order $pesanan): array
    {
        $kosong = ['tersedia' => false, 'ringkas' => '', 'diperbarui' => null];

        $resi = trim((string) ($pesanan->tracking_number ?? ''));
        $kunci = (string) (Pengaturan::instance()?->teks('tracking_api_key') ?? '');

        if ($resi === '' || $kunci === '') {
            return $kosong;
        }

        $kurir = (string) ($pesanan->shipping_courier ?? '');

        try {
            $respons = Http::withHeaders(['Key' => $kunci])
                ->timeout(10)
                ->get('https://pro.rajaongkir.com/api/waybill', [
                    'waybill' => $resi,
                    'courier' => mb_strtolower(str_replace(' ', '', $kurir)),
                ]);

            if (! $respons->successful()) {
                Log::info('Pelacakan kurir tidak tersedia', ['status' => $respons->status()]);

                return $kosong;
            }

            $ringkasan = (array) $respons->json('rajaongkir.result.manifest', []);
            $terakhir = $ringkasan[0] ?? [];

            if ($terakhir === []) {
                return $kosong;
            }

            return [
                'tersedia' => true,
                'ringkas' => (string) ($terakhir['manifest_description'] ?? ''),
                'diperbarui' => (string) ($terakhir['manifest_date'] ?? ''),
            ];
        } catch (Throwable $e) {
            // Kegagalan pelacakan tidak boleh menggagalkan halaman pesanan.
            Log::info('Pelacakan kurir gagal', ['sebab' => $e->getMessage()]);

            return $kosong;
        }
    }
}
