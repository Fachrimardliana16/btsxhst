<?php

namespace App\Livewire\Front;

use App\Enums\MetodePembayaran;
use App\Enums\TipeKejadianKeamanan;
use App\Http\Middleware\ZonaPelanggan;
use App\Models\Order;
use App\Services\Keranjang;
use App\Services\Pembayaran\PaypalGateway;
use App\Services\PesananService;
use App\Support\CatatanAktivitas;
use App\Support\Internasional;
use App\Support\Keamanan;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.front')]
class Checkout extends Component
{
    public const KUNCI_BATAS = 'checkout';

    #[Validate('required|string|min:2|max:80')]
    public string $nama = '';

    #[Validate('required|email|max:120')]
    public string $email = '';

    #[Validate('required|string|min:8|max:20')]
    public string $telepon = '';

    #[Validate('required|string|min:5|max:180')]
    public string $alamat1 = '';

    #[Validate('nullable|string|max:180')]
    public string $alamat2 = '';

    #[Validate('required|string|min:2|max:60')]
    public string $kota = '';

    #[Validate('required|string|min:2|max:60')]
    public string $provinsi = '';

    #[Validate('required|string|min:3|max:10')]
    public string $kodePos = '';

    #[Validate('required|string|max:40')]
    public string $kurir = 'JNE';

    /**
     * Negara tujuan, kode dua huruf (ISO 3166-1 alpha-2).
     *
     * Wajib per huruf latin yang seragam: alamat saja tidak cukup untuk
     * menghitung ongkir internasional — "Singapore", "S'pore" dan
     * "SG / Singapura" muncul dalam satu kolom, dan tidak ada yang mau
     * menebak mana yang mana.
     */
    // Tidak memakai #[Validate] untuk negara: daftar negara berasal dari
    // Internasional::NEGARA, dan ekspresi yang memanggil fungsi tidak boleh
    // ditulis dalam atribut. Aturannya ada di buatPesanan().
    public string $negara = 'ID';

    /**
     * Mata uang tagihan: selalu USD.
     *
     * Dulu IDR atau USD, dipilih pembeli dan disesuaikan dengan negara.
     * Pilihan itu dihapus bersama kolomnya: pasarnya luar negeri, seluruh
     * harga di situs sudah ditulis dalam dolar, dan membiarkan pembeli
     * menukar mata uang di langkah terakhir hanya akan menagih angka yang
     * berbeda dari yang ia baca di halaman produk.
     *
     * Properti tetap ada — bukan dihapus — karena PesananService dan
     * kolom `orders.shipping_currency` membacanya.
     */
    public string $mataUang = 'USD';

    #[Validate('nullable|string|max:500')]
    public string $catatan = '';

    #[Validate('required|in:paypal,manual')]
    public string $metode = 'manual';

    /**
     * Jebakan bot. Kolom ini tersembunyi dan wajib kosong.
     *
     * Bot yang mengisi semua kolom formulir ikut mengisi ini; manusia yang
     * tidak melihatnya membiarkannya kosong. Dipisah dari validasi biasa
     * supaya galatnya tidak mengajarkan bot cara memperbaiki diri.
     */
    public string $situs = '';

    /**
     * Jebakan kedua: waktu minimum sebelum formulir boleh dikirim.
     *
     * Isinya CAPTCHA ringan bagi bot: manusia butuh beberapa detik membaca
     * alamat, bot mengirim dalam hitungan milidetik. Angkanya cukup kecil
     * agar pembeli cepat tidak terganggu, cukup besar agar bot sederhana
     * tertinggal.
     */
    public int $dimulaiPada = 0;

    /** Segel tombol diklik. Lihat buatPesanan(). */
    public string $capTingkat = '';

    public ?string $galat = null;

    /**
     * Segel anti-penyalahgunaan: satu pesanan per halaman yang dimuat.
     *
     * Nilainya berupa string acak panjang yang digenerate saat mount() dan
     * hanya dilewatkan bersama permintaan buatPesanan(). Permintaan yang
     * membawa nilai salah, kosong, atau ulangan ditolak. Ini yang mencegah
     * satu sesi checkout mengirim RATUSAN pesanan beruntun, dan memaksa
     * pengunjung yang berpura-pura menjadi sesi baru untuk setiap percobaan.
     */
    public string $segel = '';

    public function mount(Keranjang $keranjang): void
    {
        if ($keranjang->jumlahItem() === 0) {
            $this->redirect(route('front.cart'), navigate: true);

            return;
        }

        $this->dimulaiPada = now()->timestamp;
        $this->segel = bin2hex(random_bytes(24));
        $this->capTingkat = 'rendah';

        // Catatan yang sudah ditulis di halaman keranjang diteruskan ke sini
        // (checklist F7-03), sehingga pembeli tidak perlu menulisnya dua kali.
        $this->catatan = $keranjang->catatan();

        // Kurir mengikuti nilai yang tersimpan di pengaturan, bukan pilihan
        // bawaan yang ditulis di kode. Tanpa ini, mengubah daftar kurir
        // menuntut perubahan kode.
        $this->kurir = (string) (Pengaturan::instance()->teks('shipping_courier_default') ?: 'JNE');

        // Metode pembayaran mengikuti mode yang aktif. Mode "manual" tidak boleh
        // menyodorkan PayPal, dan sebaliknya.
        $this->metode = match (Pengaturan::instance()->teks('payment_mode', 'manual')) {
            'paypal' => 'paypal',
            'both' => 'paypal',
            default => 'manual',
        };

        // Negara mengikuti alamat tersimpan, atau Indonesia. Mata uang mengikuti
        // negara: dalam negeri selalu IDR, luar negeri menawarkan USD sebagai
        // pilihan (tetap boleh memilih IDR).
        //
        // Pembeli yang kembali memakai zona yang dipilih sebelumnya lewat
        // cookie. Bila ia sudah pernah memilih Singapura, kolom negara sudah
        // terisi — dan ia tidak perlu mengulanginya setiap kali kembali.
        $zona = ZonaPelanggan::sekarang();

        $this->negara = $zona !== '' ? $zona : 'ID';

        if (auth()->check()) {
            $user = auth()->user();
            $this->nama = (string) $user->name;
            $this->email = (string) $user->email;
            $this->telepon = (string) ($user->phone_number ?? '');

            $utama = $user->addresses()->where('is_primary', true)->first()
                ?? $user->addresses()->first();

            if ($utama) {
                $this->nama = $utama->recipient_name;
                $this->telepon = $utama->phone_number;
                $this->alamat1 = $utama->address_line_1;
                $this->alamat2 = (string) $utama->address_line_2;
                $this->kota = $utama->city;
                $this->provinsi = $utama->state_province;
                $this->kodePos = $utama->postal_code;

                $kodeNegara = strtoupper(substr((string) ($utama->country ?? 'ID'), 0, 2));

                if ($kodeNegara !== '' && isset(Internasional::NEGARA[$kodeNegara])) {
                    $this->negara = $kodeNegara;
                }
            }
        }
    }

    /**
     * Negara berubah — mata uang TIDAK ikut berubah.
     *
     * Dulu pengiriman ke luar negeri menyarankan USD dan dalam negeri
     * memaksa IDR. Sekarang tagihannya selalu USD untuk semua negara, jadi
     * fungsi ini hanya menjaga nilainya bila ada permintaan lama yang masih
     * membawa `mataUang=IDR` dari sesi sebelumnya.
     */
    public function updatedNegara(string $nilai): void
    {
        $this->mataUang = 'USD';
    }

    /** Nilai mata uang dari permintaan lama selalu dikembalikan ke USD. */
    public function updatedMataUang(string $nilai): void
    {
        $this->mataUang = 'USD';
    }

    /**
     * Ciri penyalahgunaan yang diuji, satu per satu. Spam checkout bukan
     * satu bentuk, dan penyaring yang hanya mengenali satu pola menyerahkan
     * sisanya ke admin untuk dibersihkan manual.
     */
    private const CIRI_PENYALAHGUNAAN = [
        // Alamat luar negeri yang salah ketik: "Jalan", "Nusa", dll dalam
        // huruf Latin; aksara Asia lain muncul sebagai tak terbaca.
        'campur_aksara' => 'Alamat dalam huruf Latin saja, ya.',
        'tautan_terlalu_banyak' => 'Alamat tidak perlu memuat tautan.',
        'nama_kelabu' => 'Nama penerima tampak tidak wajar. Ketik nama Anda.',
        'telepon_patokan' => 'Nomor telepon tidak sah. Periksa kembali.',
        'segel_ulang' => 'Formulir sudah dipakai. Muat ulang halaman checkout.',
    ];

    /**
     * Periksa ciri penyalahgunaan. Mengembalikan pesan galat atau null.
     *
     * Semua pemeriksaan adalah heuristik: polanya yang menggoda, bukan yang
     * salah pasti. Karena itu kejahatannya TIDAK menghentikan pesanan bila
     * hanya satu ciri lemah (seperti campur aksara) — ia mengembalikan galat
     * karena alamat tak terbaca. Tetapi alat ini mengenali beberapa syarat
     * sekaligus sebelum harus memutuskan.
     */
    private function ciriPenyalahgunaan(): ?string
    {
        $alamat = $this->alamat1.' '.$this->alamat2;

        // a) Aksara di luar Latin (Cyrillic, Arab, CJK, dll).
        //    Naskah Latin saja yang masuk turunan lokasi pengiriman.
        //    Huruf Yunani dan Cyrillic sering dipakai untuk "memperindah"
        //    tautan palsu di alamat spam.
        if (preg_match('/[\x{0400}-\x{04FF}\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $alamat) === 1) {
            return self::CIRI_PENYALAHGUNAAN['campur_aksara'];
        }

        // b) Tautan beruntun di alamat.
        if (substr_count(strtolower($alamat), 'http') >= 2) {
            return self::CIRI_PENYALAHGUNAAN['tautan_terlalu_banyak'];
        }

        // c) Nama penerima tak dimulai dengan huruf: "emailjet" dsb.
        if (preg_match('/^[0-9\W_]+/u', trim($this->nama)) === 1) {
            return self::CIRI_PENYALAHGUNAAN['nama_kelabu'];
        }

        // d) Telepon berulang angka yang sama ("00000000000" dsb).
        if (preg_match('/^(.)\1{6,}$/', preg_replace('/\D/', '', $this->telepon) ?: '') === 1) {
            return self::CIRI_PENYALAHGUNAAN['telepon_patokan'];
        }

        return null;
    }

    public function buatPesanan(Keranjang $keranjang, PesananService $pesanan): void
    {
        $this->galat = null;

        // 1) Jebakan bot dan waktu minimum. Tanpa CAPTCHA, ini yang menyaring
        //    pengiriman otomatis.
        if ($this->situs !== '' || now()->timestamp - $this->dimulaiPada < config('iksan.brute_force.min_form_seconds')) {
            $this->galat = 'Formulir dikirim terlalu cepat. Muat ulang halaman lalu coba lagi.';

            return;
        }

        $kunci = self::KUNCI_BATAS.':'.request()->ip();

        // 5 percobaan per 10 menit (PRD Bagian 11.1), dijawab sebagai 429 —
        // bukan sebagai pesan galat di dalam halaman.
        //
        // Pembatas di dalam komponen hanya dapat mengubah tampilan: responsnya
        // tetap 200, sehingga pengawas lalu lintas dan peramban tidak pernah
        // tahu ada yang sedang dibatasi. Karena `buatPesanan` dijalankan lewat
        // permintaan Livewire, abort() di sini menghasilkan respons HTTP 429
        // yang sesungguhnya.
        // Batas ini TIDAK dipasang pada GET /checkout: membuka halaman checkout
        // berkali-kali adalah hal biasa (tombol kembali, muat ulang), sedangkan
        // yang dibatasi PRD adalah pembuatan pesanannya.
        // Batasnya 5 percobaan per 10 menit. tooManyAttempts memakai $maxAttempts
        // (angka pertama config), sama seperti RateLimiter::hit yang memakai
        // $decayMinutes (angka kedua) — keduanya harus dijaga tetap sejalan
        // dengan config, bukan ditulis ulang sebagai angka di sini.
        // Setiap percobaan terhitung, termasuk yang kemudian DITOLAK: pelaku
        // yang menabrak jebakan bot, segel, atau ciri penyalahgunaan harus tetap
        // mengisi kuota — kalau tidak, ia bisa mengulang tanpa batas justru
        // lewat penolakan. Diperiksa SEBELUM hit supaya percobaan keenam
        // mendapat 429, bukan 200.
        if (RateLimiter::tooManyAttempts($kunci.'|'.session()->getId(), config('iksan.rate_limits.checkout')[0])) {
            abort(429, 'Terlalu banyak percobaan checkout. Coba lagi beberapa menit lagi.');
        }

        RateLimiter::hit($kunci.'|'.session()->getId(), config('iksan.rate_limits.checkout')[1] * 60);

        // Segel. Satu muat halaman = satu pesanan.
        //
        // Nilainya tak terlihat oleh manusia (bukan angka, bukan jebakan) dan
        // paling murah untuk bot: mereka mencari field yang mereka isi lalu
        // mengirim ulang nilai itu ratusan kali tanpa memuat ulang halaman.
        // Menuntut harga benar membatalkan kekuatan utama mereka — mengirim
        // beruntun tanpa biaya.
        //
        // Sengaja berada SESUDAH hit(): batas laju pembatasan terakhir yang
        // tetap berlaku, bila segel dan ciri penyalahgunaan dilewati.
        if ($this->segel === '' || ! is_string($this->segel)) {
            $this->galat = 'Formulir harus dimuat ulang sebelum dipakai lagi.';

            return;
        }

        if (session()->pull('checkout.segel') === $this->segel) {
            $this->galat = 'Formulir ini sudah dipakai. Muat ulang halaman checkout.';

            return;
        }

        session()->put('checkout.segel', $this->segel);

        // Validasi atribut dulu: nama, email, telepon, alamat, dll.
        $this->validate();

        // Lalu negara, aturan terpisah. Daftar negara berasal dari
        // Internasional::NEGARA, dan ekspresi seperti itu tidak boleh ditulis
        // dalam atribut #[Validate].
        //
        // Sengaja TIDAK memakai validate([aturan]) dengan array yang hanya
        // memuat 'negara': itu membuang seluruh aturan atribut, sehingga
        // pesanan tanpa alamat yang sah bisa lolos. Dua langkah terpisah.
        Validator::make(
            ['negara' => $this->negara],
            ['negara' => ['required', 'string', 'size:2', Rule::in(array_keys(Internasional::NEGARA))]],
            [
                'negara.required' => 'Pilih negara tujuan pengiriman.',
                'negara.in' => 'Negara tujuan tidak dikenal.',
            ],
        )->validate();

        // Ciri penyalahgunaan. Dijalankan SETELAH validasi kolom dasar agar
        //    pesan salah kolom tidak berubah, dan SEBELUM pesanan dibuat —
        //    bukan sesudah.
        $ciri = $this->ciriPenyalahgunaan();

        if ($ciri !== null) {
            $this->galat = $ciri;

            // Dicatat supaya admin dapat melihat pola, bukan hanya galat
            // sesaat di layar pembeli.
            Keamanan::catat(
                TipeKejadianKeamanan::BatasLaju,
                request(),
                ringkasan: 'Checkout mencurigakan ('.$ciri.') negara '.$this->negara
                    .' — pesanan tidak dibuat.',
            );

            return;
        }

        // Pesanan dibuat sekali saja (checklist F7-07).
        //
        // Tanpa penanda ini, satu klik ganda atau satu percobaan ulang setelah
        // jaringan tersendat menghasilkan dua pesanan dan dua tahanan stok
        // untuk barang yang sama. Penandanya disimpan di sesi, jadi berlaku
        // per pengunjung; yang menjaganya dari penyerobotan adalah kunci
        // keranjang yang dipakai di sini.
        $kunciPesan = 'checkout:terkirim:'.session()->getId();

        if (session()->has($kunciPesan)) {
            $nomor = (string) session()->get($kunciPesan);

            $sudah = Order::query()->where('order_number', $nomor)->first();

            if ($sudah !== null) {
                $this->redirect(route('front.order.success', $sudah), navigate: true);

                return;
            }
        }

        try {
            $order = $pesanan->buatDariKeranjang(
                keranjang: $keranjang,
                data: [
                    'nama' => $this->nama,
                    'email' => $this->email,
                    'telepon' => $this->telepon,
                    'alamat1' => $this->alamat1,
                    'alamat2' => $this->alamat2,
                    'kota' => $this->kota,
                    'provinsi' => $this->provinsi,
                    'kodePos' => $this->kodePos,
                    'kurir' => $this->kurir,
                    'negara' => $this->negara,
                    'mataUang' => $this->mataUang,
                    'catatan' => $this->catatan,
                ],
                metode: MetodePembayaran::from($this->metode),
                user: auth()->user(),
            );
        } catch (\RuntimeException $e) {
            $this->galat = $e->getMessage();

            return;
        }

        /* ═══ PAYPAL: pembeli harus BENAR-BENAR pergi ke PayPal ═══════════
         *
         * Sebelumnya blok ini tidak ada sama sekali. Checkout hanya mencatat
         * `payment_method = paypal` lalu langsung melepas pembeli ke halaman
         * sukses — tanpa satu pun panggilan ke PayPal. Akibatnya pembeli
         * buntu: pesanan berstatus "belum dibayar", tidak ada tombol bayar,
         * dan tidak ada cara melanjutkan.
         *
         * Di sini pesanan PayPal dibuat, lalu pembeli diarahkan ke halaman
         * persetujuan milik PayPal. Uangnya belum berpindah pada titik ini;
         * penangkapan dana terjadi setelah pembeli menyetujui, di halaman
         * kembali (lihat PaypalKembali).
         *
         * KEGAGALAN TIDAK MEMBATALKAN PESANAN. Kalau PayPal sedang tidak
         * dapat dihubungi, pesanan tetap ada dan pembeli tetap mendapat
         * halaman sukses berisi tombol "Coba bayar lagi". Membuang pesanan
         * hanya karena pihak ketiga sedang down berarti pembeli kehilangan
         * pilihannya tanpa alasan.
         */
        if ($order->payment_method === MetodePembayaran::Paypal) {
            try {
                $hasil = app(PaypalGateway::class)->buatPesanan($order);

                if (filled($hasil['setuju_url'] ?? null)) {
                    CatatanAktivitas::catat('paypal.pesanan-dibuat', [
                        'pesanan' => $order->order_number,
                        'paypal_id' => $hasil['id'] ?? null,
                    ]);

                    // Langsung ke PayPal: pembeli tidak perlu singgah di
                    // halaman sukses lalu mencari tombol tambahan.
                    $this->redirect($hasil['setuju_url']);

                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('PayPal gagal membuat pesanan', [
                    'pesanan' => $order->order_number,
                    'galat' => $e->getMessage(),
                ]);

                // Pesanan tetap ada; pembeli mendapat tombol coba lagi di
                // halaman sukses.
            }
        }

        session()->put($kunciPesan, $order->order_number);

        // Zona pengiriman diingat supaya kunjungan berikutnya tidak mengisi
        // ulang kolom negara. Ditulis hanya setelah pesanan berhasil dibuat:
        // pilihan yang gagal tersimpan akan membuat pembeli terjebak di zona
        // yang bukan ia pilih.
        //
        // Ini penulisan yang TIDAK butuh cookie izin terlebih dulu: pembeli
        // baru saja memilih negara sendiri di form ini, jadi persetujuannya
        // nyata dan tertulis pada tindakan itu — bukan sekedar izin di
        // pemberitahuan cookie. Cookie izin ikut ditulis supaya pemberitahuan
        // tidak muncul lagi setelahnya.
        cookie()->queue(cookie(
            name: ZonaPelanggan::COOKIE,
            value: $this->negara,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: false,
            sameSite: 'lax',
        ));

        cookie()->queue(cookie(
            name: ZonaPelanggan::IZIN,
            value: '1',
            minutes: 60 * 24 * 365,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: false,
            sameSite: 'lax',
        ));

        $this->dispatch('keranjang-berubah');
        $this->redirect(route('front.order.success', $order), navigate: true);
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $keranjang = app(Keranjang::class);

        $mode = $toko->teks('payment_mode', 'manual');

        // Daftar kurir dari pengaturan, bukan dari kode (PRD Bagian 8 langkah 3
        // menyebut daftar statis; "statis" berarti tidak dihitung dari berat
        // atau tujuan, bukan berarti tidak dapat diubah tanpa menyentuh kode).
        $kurir = $toko->larikTeks('shipping_couriers', ['JNE', 'J&T Express', 'SiCepat', 'Pos Indonesia']);

        if ($this->kurir !== '' && ! in_array($this->kurir, $kurir, true)) {
            $kurir[] = $this->kurir;
        }

        return view('livewire.front.checkout', [
            'ringkasan' => $keranjang->ringkasan(),
            'modePembayaran' => $mode,
            'daftarNegara' => Internasional::NEGARA,
            'danNegeri' => Internasional::dalamNegeri($this->negara),
            'namaNegara' => Internasional::namaNegara($this->negara),
            'kodeTelepon' => Internasional::kodeTelepon($this->negara),
            // Hanya metode yang memang aktif. Menampilkan pilihan PayPal saat
            // mode manual berarti menawarkan pembayaran yang tidak tersedia.
            'daftarMetode' => match ($mode) {
                'paypal' => ['paypal'],
                'both' => ['paypal', 'manual'],
                default => ['manual'],
            },
            // Diberi nama berbeda dari properti $kurir. Livewire menyuntikkan
            // properti publik ke dalam view, dan nama yang sama membuat properti
            // (string) menimpa variabel ini (daftar), sehingga @foreach atasnya
            // gagal dan halaman checkout tidak dapat dirender sama sekali.
            'daftarKurir' => $kurir,
            // Ringkasan checkout tidak lagi mengirim perkiraan mata uang asing.
            // Pilihan mata uang dihapus dan seluruh situs kini menagih USD,
            // jadi satu-satunya angka yang perlu dibaca pembeli sudah ada di
            // $ringkasan — dalam dolar.
        ])
            ->title(__('site.checkout.title').' · '.$toko->teks('site_name'))
            ->layoutData([
                'kanonikal' => route('front.checkout'),
                'metaOg' => [
                    'tipe' => 'website',
                    'judul' => __('site.checkout.title'),
                    'deskripsi' => $toko->teks('site_description', __('site.brand.tagline')),
                    'gambar' => null,
                ],
                // Halaman checkout tidak boleh diindeks: isinya bergantung pada
                // keranjang pengunjung, dan yang terindeks hanya akan berupa
                // halaman kosong atau keranjang orang lain.
                'tanpaIndeks' => true,
            ]);
    }
}
