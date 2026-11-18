<?php

namespace App\Livewire\Front;

use App\Enums\StatusPembayaran;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pembayaran\PembayaranManual;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Halaman untuk pembeli mengunggah bukti transfer (checklist F9-05).
 *
 * Dibuka lewat ULID pesanan, tanpa akun, karena checkout toko ini tidak
 * mewajibkan akun. ULID 26 karakter acak itulah yang menjaga aksesnya: hanya
 * pembeli yang memegang nomor pesanannya dapat membuka halaman ini.
 *
 * Yang ditampilkan disesuaikan dengan keadaan pesanan. Pembeli yang sudah
 * mengunggah bukti tidak disuruh mengunggah lagi; pembeli yang pesanannya
 * sudah lunas hanya melihat keterangan, bukan formulir yang pasti ditolak.
 */
#[Layout('layouts.front')]
class UnggahBukti extends Component
{
    use WithFileUploads;

    public string $pesananUlid = '';

    /** @var TemporaryUploadedFile|null */
    public $bukti = null;

    public string $catatan = '';

    public ?string $pesan = null;

    public ?string $galat = null;

    public bool $berhasil = false;

    public function mount(string $ulid): void
    {
        $this->pesananUlid = $ulid;

        // Memastikan pesanan ada. ULID yang salah menghasilkan 404, bukan
        // halaman kosong yang membingungkan.
        $this->pesanan();
    }

    private function pesanan(): Order
    {
        return Order::query()
            ->where('ulid', $this->pesananUlid)
            ->firstOrFail();
    }

    public function unggah(PembayaranManual $manual): void
    {
        $this->galat = null;
        $this->pesan = null;

        $this->validate([
            'bukti' => ['required', 'file', 'max:5120'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'bukti.required' => 'Select the transfer receipt file first.',
            'bukti.max' => 'The file must be 5 MB or smaller.',
            'bukti.file' => 'The file cannot be read.',
            'catatan.max' => 'The note must be 500 characters or fewer.',
        ]);

        $pesanan = $this->pesanan();

        try {
            $hasil = $manual->unggahBukti(
                $pesanan,
                $this->bukti,
                $this->catatan === '' ? null : $this->catatan,
            );

            $this->berhasil = true;
            $this->pesan = $hasil['pesan'];
            $this->bukti = null;
            $this->catatan = '';
        } catch (ValidationException $e) {
            // Pesan dari layanan lebih spesifik daripada validasi umum, jadi
            // diteruskan apa adanya ke kolomnya.
            foreach ($e->errors() as $kolom => $daftar) {
                $this->addError($kolom, $daftar[0]);
            }
        } catch (Throwable $e) {
            // Pesan pengecualian TIDAK diteruskan ke pembeli: isinya dapat memuat
            // jalur berkas server, nama tabel, atau kueri. Yang dicatat ke log,
            // yang ditampilkan hanya pesan umum.
            Log::warning('Bukti bayar gagal diunggah', [
                'pesanan' => $this->pesananUlid,
                'sebab' => $e->getMessage(),
            ]);

            $this->galat = 'The receipt could not be uploaded. Try again, or reach us on WhatsApp.';
        }
    }

    public function render(): View
    {
        $pesanan = $this->pesanan();
        $manual = app(PembayaranManual::class);
        $toko = Pengaturan::instance();

        $pembayaran = Payment::query()
            ->where('order_id', $pesanan->id)
            ->orderByDesc('id')
            ->get();

        return view('livewire.front.unggah-bukti', [
            'pesanan' => $pesanan,
            'pembayaran' => $pembayaran,
            'rekening' => $manual->daftarRekening(),
            'rekeningLengkap' => $manual->rekeningLengkap(),
            'sudahLunas' => $pesanan->payment_status === StatusPembayaran::Paid,
            'menunggu' => $pesanan->payment_status === StatusPembayaran::Pending,
        ])->title('Unggah bukti transfer · '.$toko->teks('site_name', 'Bites Exhaust'))
            // Halaman ini dibuka lewat ULID pesanan; tautannya setara token
            // akses pesanan dan tidak boleh masuk indeks.
            ->layoutData(['tanpaIndeks' => true]);
    }
}
