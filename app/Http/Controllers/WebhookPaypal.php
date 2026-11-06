<?php

namespace App\Http\Controllers;

use App\Services\Pembayaran\PaypalGateway;
use App\Support\CatatanAktivitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook PayPal.
 *
 * ── KENAPA ENDPOINT INI ADA ────────────────────────────────────────────
 * Sebelumnya tidak ada. `PaypalGateway::verifikasiTandaTangan()` dan
 * `tanganiWebhook()` sudah ditulis lengkap, tetapi TIDAK PERNAH dipanggil dari
 * mana pun — jadi pembayaran PayPal tidak akan pernah tercatat walaupun
 * uangnya sudah masuk ke akun toko.
 *
 * ── URUTAN YANG DIPAKAI, DAN KENAPA ────────────────────────────────────
 *   1. Baca isi permintaan.
 *   2. Verifikasi tanda tangan ke PayPal (bukan dihitung sendiri).
 *   3. Baru percayai isinya.
 *
 * Langkah 2 tidak boleh dilewati. Tanpa verifikasi, siapa pun yang mengetahui
 * alamat endpoint ini dapat mengirim JSON yang menyatakan "pesanan ini sudah
 * dibayar", dan pesanan akan dikirim tanpa uang masuk.
 *
 * ── SELALU MENJAWAB 200 ────────────────────────────────────────────────
 * PayPal mengulang kiriman yang dijawab 4xx/5xx. Untuk peristiwa yang memang
 * tidak relevan (jenis lain, pesanan tidak dikenal), jawaban 200 adalah
 * pernyataan "sudah diterima, jangan kirim lagi" — bukan tanda berhasil.
 * Yang gagal diverifikasi TETAP dijawab 200 tetapi dicatat sebagai kejadian
 * keamanan, supaya pengulangan tidak membanjiri server.
 */
class WebhookPaypal extends Controller
{
    public function __invoke(Request $request, PaypalGateway $gateway): JsonResponse
    {
        $peristiwa = json_decode($request->getContent(), true);

        if (! is_array($peristiwa)) {
            // Bukan JSON: tidak ada yang bisa diproses, dan mengulang tidak
            // akan mengubah apa pun.
            return response()->json(['diterima' => false, 'alasan' => 'Isi bukan JSON.'], 200);
        }

        try {
            $sah = $gateway->verifikasiTandaTangan($peristiwa, $request);

            $hasil = $gateway->tanganiWebhook($peristiwa, $sah);
        } catch (Throwable $e) {
            /* Kegagalan di pihak kita (jaringan ke PayPal putus, basis data
             * terkunci) HARUS dijawab 5xx. Ini satu-satunya keadaan di mana
             * kita ingin PayPal mengirim ulang: peristiwanya mungkin sah, kita
             * yang belum bisa memprosesnya. Menjawab 200 di sini berarti
             * pembayaran yang sah hilang selamanya. */
            Log::error('Webhook PayPal gagal diproses', [
                'jenis' => $peristiwa['event_type'] ?? null,
                'galat' => $e->getMessage(),
            ]);

            return response()->json(['diterima' => false, 'alasan' => 'Gagal diproses, silakan ulangi.'], 500);
        }

        if ($sah) {
            CatatanAktivitas::catat('paypal.webhook-diterima', [
                'jenis' => $peristiwa['event_type'] ?? null,
                'diabaikan' => $hasil['diabaikan'],
                'alasan' => $hasil['alasan'],
            ]);
        }

        return response()->json([
            'diterima' => true,
            'tanda_tangan_sah' => $sah,
            ...$hasil,
        ], 200);
    }
}
