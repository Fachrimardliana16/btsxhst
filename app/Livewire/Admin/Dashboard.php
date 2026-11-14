<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPesanan;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin')]
class Dashboard extends KomponenAdmin
{
    public function render(): View
    {
        $hariIni = now()->startOfDay();
        $bulanIni = now()->startOfMonth();

        $pesananHariIni = Order::query()->where('created_at', '>=', $hariIni)->count();

        $pendapatanHariIni = (float) Order::query()
            ->where('created_at', '>=', $hariIni)
            ->whereNotIn('status', [StatusPesanan::Cancelled->value])
            ->sum('grand_total');

        $menungguProses = Order::query()
            ->whereIn('status', [StatusPesanan::Paid->value, StatusPesanan::Processing->value])
            ->count();

        $ambangStok = (int) Pengaturan::instance()->angka('low_stock_threshold', 3);

        $stokMenipis = ProductVariant::query()
            ->where('is_active', true)
            ->whereRaw('(stock_quantity - reserved_quantity) <= ?', [$ambangStok])
            ->count();

        // Grafik 30 hari. Satu kueri agregat, bukan 30 kueri terpisah.
        $grafik = Order::query()
            ->selectRaw('date(created_at) as hari, count(*) as jumlah, sum(grand_total) as nilai')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->whereNotIn('status', [StatusPesanan::Cancelled->value])
            ->groupBy('hari')
            ->orderBy('hari')
            ->get()
            ->keyBy('hari');

        $seri = collect(range(29, 0))->map(function (int $mundur) use ($grafik): array {
            $tanggal = now()->subDays($mundur)->format('Y-m-d');
            $baris = $grafik->get($tanggal);

            return [
                'tanggal' => $tanggal,
                'label' => now()->subDays($mundur)->format('d/m'),
                'jumlah' => (int) ($baris->jumlah ?? 0),
                'nilai' => (float) ($baris->nilai ?? 0),
            ];
        });

        $maksNilai = max(1.0, (float) $seri->max('nilai'));

        $terbaru = Order::query()
            ->with('items')
            ->latest('created_at')
            ->take(10)
            ->get();

        $terlaris = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $bulanIni)
            ->whereNotIn('orders.status', [StatusPesanan::Cancelled->value])
            ->selectRaw('order_items.product_name as nama, sum(order_items.quantity) as terjual, sum(order_items.subtotal) as nilai')
            ->groupBy('order_items.product_name')
            ->orderByDesc('terjual')
            ->limit(5)
            ->get();

        // Peringatan yang berasal dari data nyata, bukan daftar tetap.
        $peringatan = [];

        // Sebagian modul yang dituju peringatan ini belum dibangun. Memanggil
        // route() untuk rute yang tidak ada melempar pengecualian, dan seluruh
        // dashboard gagal render — peringatan yang seharusnya membantu justru
        // mematikan halaman. Arahkan ke dashboard bila rutenya belum ada.
        $rute = fn (string $nama, array $parameter = []): string => Route::has($nama)
            ? route($nama, $parameter)
            : route('admin.dashboard');

        $pendingTua = Order::query()
            ->where('status', StatusPesanan::Pending->value)
            ->where('created_at', '<=', now()->subDay())
            ->count();

        if ($pendingTua > 0) {
            $peringatan[] = [
                'nada' => 'warn',
                'teks' => __('site.msg.dashboard.pesanan_belum_bayar', ['jumlah' => $pendingTua]),
                'rute' => $rute('admin.orders.index', ['status' => 'pending']),
            ];
        }

        $tanpaGambar = Product::query()->doesntHave('media')->count();

        if ($tanpaGambar > 0) {
            $peringatan[] = [
                'nada' => 'warn',
                'teks' => __('site.msg.dashboard.produk_tanpa_gambar', ['jumlah' => $tanpaGambar]),
                'rute' => $rute('admin.products.index'),
            ];
        }

        $pesanBaru = ContactMessage::query()->belumDibaca()->count();

        if ($pesanBaru > 0) {
            $peringatan[] = [
                'nada' => 'steel',
                'teks' => __('site.msg.dashboard.pesan_belum_dibaca', ['jumlah' => $pesanBaru]),
                'rute' => $rute('admin.customers.index'),
            ];
        }

        // ── Kelengkapan pengaturan penting ────────────────────────────
        //
        // Peringatan ini dulu hanya memeriksa nomor WhatsApp, padahal masalah
        // yang sama berlaku untuk semua pengaturan yang membuat fitur situs
        // berhenti bekerja tanpa suara: nama toko kosong, email kontak kosong,
        // SMTP belum diisi, dan kredensial PayPal masih placeholder. Semuanya
        // ditampilkan sebagai satu daftar yang menautkan langsung ke tab
        // pengaturannya.

        $toko = Pengaturan::instance();
        $kosong = fn (string $kunci): bool => trim((string) $toko->teks($kunci, '')) === '';

        // Nama toko punya cadangan dari terjemahan, jadi accessor tidak pernah
        // kosong. Yang diperiksa nilai tersimpannya, karena nama cadangan itu
        // bukan pilihan admin.
        if (\App\Support\KomponenPengaturan::mentah('site_name') === null) {
            $peringatan[] = [
                'nada' => 'danger',
                'teks' => __('site.msg.dashboard.site_name_belum'),
                'rute' => $rute('admin.settings.index', ['tab' => 'identity']),
            ];
        }

        if ($kosong('whatsapp_number')) {
            $peringatan[] = [
                'nada' => 'danger',
                'teks' => __('site.msg.dashboard.wa_belum'),
                'rute' => $rute('admin.settings.index', ['tab' => 'contact']),
            ];
        }

        if ($kosong('contact_email')) {
            $peringatan[] = [
                'nada' => 'warn',
                'teks' => __('site.msg.dashboard.contact_email_belum'),
                'rute' => $rute('admin.settings.index', ['tab' => 'contact']),
            ];
        }

        // Nilai efektif, bukan nilai mentah: SMTP dari .env tetap dihitung
        // sudah terisi karena KonfigurasiMail hanya menimpa yang tidak kosong.
        if (config('mail.default') === 'smtp' && trim((string) config('mail.mailers.smtp.host')) === '') {
            $peringatan[] = [
                'nada' => 'danger',
                'teks' => __('site.msg.dashboard.mail_belum'),
                'rute' => $rute('admin.settings.index', ['tab' => 'email']),
            ];
        }

        $modeBayar = (string) $toko->teks('payment_mode', 'manual');

        if (in_array($modeBayar, ['paypal', 'both'], true)) {
            $idPaypal = strtoupper(trim((string) $toko->teks('paypal_client_id', '')));
            $rahasiaPaypal = strtoupper(trim((string) $toko->teks('paypal_client_secret', '')));

            // Placeholder bawaan seeder juga dianggap belum diisi: secara
            // teknis ada isinya, tetapi PayPal akan menolaknya.
            $paypalBelumSiap = $idPaypal === '' || $rahasiaPaypal === ''
                || str_contains($idPaypal, 'CONTOH')
                || str_contains($rahasiaPaypal, 'CONTOH');

            if ($paypalBelumSiap) {
                $peringatan[] = [
                    'nada' => 'danger',
                    'teks' => __('site.msg.dashboard.paypal_kredensial_belum'),
                    'rute' => $rute('admin.settings.index', ['tab' => 'payment']),
                ];
            }

            if ($kosong('paypal_webhook_id')) {
                $peringatan[] = [
                    'nada' => 'warn',
                    'teks' => __('site.msg.dashboard.paypal_webhook_belum'),
                    'rute' => $rute('admin.settings.index', ['tab' => 'payment']),
                ];
            }
        }

        return view('livewire.admin.dashboard', [
            'judul' => 'Dashboard',
            'bagian' => 'Overview',
            'kartu' => [
                ['label' => __('site.msg.dashboard.pesanan_hari_ini'), 'nilai' => (string) $pesananHariIni, 'ikon' => 'receipt'],
                ['label' => "Today's revenue", 'nilai' => dolar($pendapatanHariIni), 'ikon' => 'chart'],
                ['label' => 'Awaiting processing', 'nilai' => (string) $menungguProses, 'ikon' => 'box'],
                ['label' => __('site.msg.dashboard.stok_menipis'), 'nilai' => (string) $stokMenipis, 'ikon' => 'alert'],
            ],
            'seri' => $seri,
            'maksNilai' => $maksNilai,
            'terbaru' => $terbaru,
            'terlaris' => $terlaris,
            'peringatan' => $peringatan,
        ])->layout('layouts.admin', ['judul' => 'Dashboard', 'bagian' => 'Overview']);
    }
}
