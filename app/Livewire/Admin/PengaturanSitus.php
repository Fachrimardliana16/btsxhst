<?php

namespace App\Livewire\Admin;

use App\Enums\GrupPengaturan;
use App\Enums\TipeKejadianKeamanan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Gambar\PengecilGambar;
use App\Services\Pembayaran\KlienPaypal;
use App\Services\Pengaturan\PenulisPengaturan;
use App\Support\Keamanan;
use App\Support\KomponenPengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Web Setting lima tab (checklist F4-01..F4-08).
 *
 * Satu komponen menangani kelima tab supaya perpindahan tab tidak memuat ulang
 * halaman dan nilai yang belum disimpan tidak hilang saat admin berpindah.
 * Penyimpanan tetap per tab: mengubah nomor WhatsApp tidak ikut menulis ulang
 * kredensial PayPal.
 */
#[Layout('layouts.admin')]
class PengaturanSitus extends KomponenAdmin
{
    use WithFileUploads;

    /** Tab aktif. Disimpan di URL supaya dapat ditautkan langsung. */
    #[Url(as: 'tab', history: true)]
    public string $tab = 'identity';

    /** Nilai formulir tab aktif, termasuk yang belum disimpan. */
    public array $nilai = [];

    /**
     * Berkas gambar yang baru dipilih, satu per kunci pengaturan. Livewire
     * mengunggahnya ke penyimpanan sementara, bukan langsung ke disk publik,
     * sehingga berkas belum tayang sebelum admin menekan simpan.
     *
     * @var array<string, mixed>
     */
    public array $berkas = [];

    public ?string $pesanSukses = null;

    public ?string $galat = null;

    /** @var array<string, mixed>|null hasil uji koneksi PayPal */
    public ?array $hasilPaypal = null;

    public bool $sedangUji = false;

    /** Nomor versi data, dipakai untuk memuat ulang formulir setelah simpan. */
    public int $versi = 0;

    public function mount(?string $tab = null): void
    {

        if ($tab !== null && $this->tabSah($tab)) {
            $this->tab = $tab;
        }

        if (! $this->tabSah($this->tab)) {
            $this->tab = GrupPengaturan::Identitas->value;
        }

        $this->muat();
    }

    /** Pindah tab tanpa memuat ulang halaman. */
    public function pilihTab(string $tab): void
    {
        if (! $this->tabSah($tab)) {
            return;
        }

        $this->tab = $tab;
        $this->bersihkanPesan();
        $this->muat();
    }

    public function simpan(PenulisPengaturan $penulis): void
    {
        // Diperiksa di server, bukan hanya dengan menyembunyikan tombol.
        // Permintaan Livewire dapat dikirim langsung tanpa melewati antarmuka,
        // sehingga menyembunyikan tombolnya saja tidak menghalangi apa pun.
        $this->authorize('aturSitus', User::class);

        $this->bersihkanPesan();

        $aturan = $this->aturanBerprefiks();

        // Grup tanpa satu pun baris pengaturan menghasilkan larik aturan kosong.
        // Livewire melempar MissingRulesException untuk itu — 500 pada
        // /livewire/update, lalu komponen hilang dari DOM. Tidak ada yang perlu
        // divalidasi bila tidak ada kolom yang bisa diisi.
        if ($aturan !== []) {
            $this->validate(
                $aturan,
                [],
                $this->labelBerprefiks(),
            );
        }

        $this->unggahGambarTertunda($penulis);

        $nilai = KomponenPengaturan::saringKosong($this->nilai);

        if ($nilai === []) {
            $this->pesanSukses = __('site.msg.pengaturan.tidak_ada_perubahan');

            return;
        }

        try {
            $hasil = $penulis->simpan($nilai);
        } catch (Throwable $e) {
            Keamanan::catat(
                TipeKejadianKeamanan::AksesTidakSah,
                request(),
                auth()->user()?->email,
                auth()->id(),
                'Failed to save settings for tab '.$this->tab,
            );
            Log::error(__('site.msg.pengaturan.gagal'), [
                'tab' => $this->tab,
                'sebab' => $e->getMessage(),
            ]);

            $this->galat = __('site.msg.pengaturan.gagal_periksa');

            return;
        }

        $this->versi++;

        if ($hasil['diubah'] === []) {
            $this->pesanSukses = __('site.msg.pengaturan.tidak_ada_perubahan');
        } else {
            $this->pesanSukses = count($hasil['diubah']).' settings saved: '.implode(', ', $hasil['diubah']).'.';
        }

        // Muat ulang dari basis data supaya yang tampil benar-benar nilai
        // tersimpan, bukan sisa formulir.
        $this->muat();

        if ($this->tab === GrupPengaturan::Pembayaran->value) {
            // Hasil uji koneksi sebelumnya tidak lagi mewakili kredensial baru.
            $this->hasilPaypal = null;
        }
    }

    /**
     * Uji koneksi PayPal memakai kredensial TERSIMPAN, bukan yang sedang
     * diketik. Kalau admin baru mengubah kredensial dan belum menyimpan, yang
     * diuji masih yang lama — itu memang yang akan dipakai toko.
     */
    public function ujiKoneksiPaypal(KlienPaypal $klien): void
    {
        $this->bersihkanPesan();
        $this->sedangUji = true;

        try {
            $this->hasilPaypal = $klien->ujiKoneksi();
        } catch (Throwable $e) {
            // Rincian teknis hanya ke log. Pesan pengecualian dapat memuat
            // kredensial PayPal, jalur berkas, atau jawaban mentah API.
            Log::error('Uji koneksi PayPal gagal', ['sebab' => $e->getMessage()]);

            $this->hasilPaypal = [
                'ok' => false,
                'pesan' => __('site.msg.pengaturan.paypal_gagal', ['pesan' => __('site.msg.pengaturan.cek_log')]),
                'detail' => [],
            ];
        } finally {
            $this->sedangUji = false;
        }
    }

    /**
     * Mengirim email percobaan ke alamat admin.
     *
     * Ada karena konfigurasi SMTP yang salah TIDAK terlihat sampai ada
     * pesanan sungguhan — dan saat itu terjadi, pembeli yang menunggu
     * emailnya tidak pernah tahu apa yang gagal. Tombol ini memindahkan
     * pemeriksaan itu ke saat pemilik toko masih di depan layar.
     *
     * Galat SMTP ditampilkan apa adanya (dipangkas). Pesan seperti
     * "Connection refused" atau "535 Authentication failed" langsung
     * menunjuk bagian mana yang salah; pesan ramah yang samar justru
     * membuat orang menebak-nebak.
     */
    public function ujiSmtp(): void
    {
        $this->authorize('aturSitus', User::class);

        $this->bersihkanPesan();

        $tujuan = trim((string) ($this->nilai['admin_email'] ?? ''));

        if ($tujuan === '' || filter_var($tujuan, FILTER_VALIDATE_EMAIL) === false) {
            $this->galat = __('site.msg.pengaturan.isi_email_dulu');

            return;
        }

        // Pengaturan yang baru diketik belum tersimpan; terapkan dulu supaya
        // yang diuji adalah isian di layar, bukan nilai lama di basis data.
        $this->terapkanKonfigurasiSementara();

        try {
            Mail::raw(
                'This is a test email from the '.config('app.name').' panel.'."\n\n"
                ."If you receive this email, the SMTP settings are correct and\n"
                .'order notifications will be delivered to this address.',
                function ($m) use ($tujuan): void {
                    $m->to($tujuan)->subject('Test email · SMTP configuration works');
                },
            );

            $this->pesanSukses = 'Test email sent to '.$tujuan.'. Check the inbox (and spam folder) in a few minutes.';
        } catch (Throwable $e) {
            Log::error('Test email failed', ['sebab' => $e->getMessage()]);
            $this->galat = __('site.msg.pengaturan.email_uji_gagal');
        }
    }

    /**
     * Menerapkan isian di layar ke config mail, tanpa menyimpan.
     *
     * Dipakai oleh ujiSmtp() supaya pengguna dapat mencoba sebelum menekan
     * Simpan. Nilai yang kosong diabaikan agar tidak menghapus konfigurasi
     * yang sudah benar.
     */
    private function terapkanKonfigurasiSementara(): void
    {
        $peta = [
            'mail_mailer' => 'mail.default',
            'mail_host' => 'mail.mailers.smtp.host',
            'mail_username' => 'mail.mailers.smtp.username',
            'mail_password' => 'mail.mailers.smtp.password',
            'mail_from_address' => 'mail.from.address',
            'mail_from_name' => 'mail.from.name',
        ];

        foreach ($peta as $kunci => $jalur) {
            $nilai = trim((string) ($this->nilai[$kunci] ?? ''));

            if ($nilai !== '') {
                config([$jalur => $nilai]);
            }
        }

        $port = trim((string) ($this->nilai['mail_port'] ?? ''));
        if ($port !== '' && ctype_digit($port)) {
            config(['mail.mailers.smtp.port' => (int) $port]);
        }

        $enkripsi = trim((string) ($this->nilai['mail_encryption'] ?? ''));
        if ($enkripsi !== '') {
            config(['mail.mailers.smtp.encryption' => $enkripsi === 'none' ? null : $enkripsi]);
        }

        // MailManager di-cache container; buang supaya memakai setelan baru.
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
    }

    /** Hapus kredensial tersimpan. Nilainya tidak pernah ditampilkan lagi. */
    public function hapusKredensial(string $kunci): void
    {
        $this->authorize('aturSitus', User::class);

        $this->bersihkanPesan();

        if (! in_array($kunci, KomponenPengaturan::KUNCI_RAHASIA, true)) {
            $this->galat = 'Key "'.$kunci.'" is not a credential that can be deleted.';

            return;
        }

        KomponenPengaturan::hapus($kunci);

        $this->nilai[$kunci] = '';
        $this->nilai[KomponenPengaturan::kunciTerisi($kunci)] = false;
        $this->hasilPaypal = null;

        $this->pesanSukses = 'Credential "'.$kunci.'" removed. This setting is now empty.';
    }

    /**
     * Simpan berkas gambar yang dipilih sebelum nilai teks disimpan, supaya
     * kunci gambar sudah berisi jalur yang sah ketika divalidasi.
     */
    private function unggahGambarTertunda(PenulisPengaturan $penulis): void
    {
        if ($this->berkas === []) {
            return;
        }

        $pengecil = app(PengecilGambar::class);

        foreach ($this->berkas as $kunci => $unggahan) {
            if ($unggahan === null) {
                continue;
            }

            $this->validate([
                'berkas.'.$kunci => ['file', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120'],
            ], [], [$kunci => 'Berkas '.$kunci]);

            $jalur = $penulis->simpanGambar($kunci, $unggahan, $pengecil);

            $this->nilai[$kunci] = $jalur;
            $this->berkas[$kunci] = null;
        }
    }

    /** Isi ulang nilai formulir untuk tab aktif. */
    private function muat(): void
    {
        $this->nilai = KomponenPengaturan::formulir($this->tab);
        $this->berkas = [];
    }

    /**
     * Livewire menuntut setiap kunci aturan menunjuk properti yang benar-benar
     * ada. Karena nilainya berada di dalam properti array $nilai, kuncinya harus
     * berawalan "nilai." — tanpa awalan itu, Livewire menolak sebelum validasi
     * dijalankan dengan galat "No property found for validation".
     *
     * @return array<string, array<int, mixed>>
     */
    private function aturanBerprefiks(): array
    {
        $aturan = [];

        foreach (KomponenPengaturan::aturan($this->tab) as $kunci => $daftar) {
            $aturan['nilai.'.$kunci] = $daftar;
        }

        return $aturan;
    }

    /** @return array<string, string> */
    private function labelBerprefiks(): array
    {
        $label = [];

        foreach (KomponenPengaturan::label($this->tab) as $kunci => $teks) {
            $label['nilai.'.$kunci] = $teks;
        }

        return $label;
    }

    private function bersihkanPesan(): void
    {
        $this->pesanSukses = null;
        $this->galat = null;
    }

    private function tabSah(string $tab): bool
    {
        return in_array($tab, GrupPengaturan::values(), true);
    }

    public function render(): View
    {
        $grup = GrupPengaturan::from($this->tab);

        return view('livewire.admin.pengaturan', [
            'judul' => 'Site Settings',
            'daftarTab' => collect(GrupPengaturan::cases())->map(fn (GrupPengaturan $g): array => [
                'nilai' => $g->value,
                'label' => $g->label(),
                'ikon' => $g->ikon(),
            ]),
            'grup' => $grup,
            'baris' => $this->barisTampil($grup),
        ])->layout('layouts.admin', ['judul' => 'Site Settings', 'bagian' => 'Site']);
    }

    /**
     * Metadata kolom untuk tab aktif. Nilai rahasia diganti penanda kosong dan
     * tidak pernah ikut ke tampilan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function barisTampil(GrupPengaturan $grup): Collection
    {
        return Setting::query()
            ->where('group_name', $grup->value)
            ->orderBy('id')
            ->get()
            ->map(fn (Setting $s): array => [
                'kunci' => $s->key,
                'label' => $s->label,
                'tipe' => $s->type,
                'opsi' => $s->options_json ?? [],
                'rahasia' => $s->is_encrypted,
                'terisi' => $s->is_encrypted && filled($s->value),
                'bantuan' => $this->bantuan($s->key),
            ]);
    }

    /** Keterangan tambahan di bawah kolom. Tidak semua kunci punya. */
    private function bantuan(string $kunci): ?string
    {
        return match ($kunci) {
            'whatsapp_number' => 'International format without a plus sign, e.g. 628123456789.',
            'whatsapp_message_template' => 'Available placeholders: {nama_produk}, {url_produk}, {nama_toko}.',
            'maps_embed' => __('site.msg2.pengaturan.maps_hint'),
            'paypal_client_id', 'paypal_client_secret' => __('site.msg2.pengaturan.nilai_tersembunyi'),
            'manual_bank_accounts' => 'Save as a JSON array: [{"bank":"BCA","nomor":"123","atas_nama":"Name"}].',
            'order_prefix' => 'Used as the order number prefix, e.g. IK-20261017-0001.',
            'low_stock_threshold' => 'The low-stock warning appears when remaining stock reaches this number.',
            'maintenance_mode' => 'When enabled, visitors see the maintenance page. The admin panel remains accessible.',
            'notif_item_stok_habis' => __('site.msg2.pengaturan.bantuan_stok_habis'),
            'notif_item_stok_menipis' => __('site.msg2.pengaturan.bantuan_stok_menipis'),
            'notif_item_pesanan_siap_kirim' => __('site.msg2.pengaturan.bantuan_perlu_dikirim'),
            'notif_item_hampir_batal' => __('site.msg2.pengaturan.bantuan_hampir_batal'),
            'notif_item_masuk_keranjang' => __('site.msg2.pengaturan.bantuan_keranjang'),
            'notif_kirim_dibuat' => 'Sent to the buyer when a new order arrives.',
            'notif_kirim_dibayar' => 'Sent to the buyer after payment is verified.',
            'notif_kirim_dikirim' => 'Sent to the buyer when an order becomes shipped.',
            'notif_push_perangkat' => __('site.msg2.pengaturan.bantuan_push'),
            'notif_push_surel_cadangan' => 'If push cannot be delivered — no subscribed device or the push service is down — send an email to the admin instead.',
            default => null,
        };
    }
}
