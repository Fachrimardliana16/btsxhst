/* Bites Exhaust - perilaku global sisi peramban.
   Tanpa pustaka besar. Alpine disediakan Livewire. */

/* Penanda bahwa JavaScript jalan. Dipasang SEBELUM apa pun supaya aturan
   `html:not(.js) [data-reveal]` di tokens.css tidak sempat menyembunyikan isi
   yang tidak akan pernah dimunculkan saat skrip gagal dimuat. */
document.documentElement.classList.add('js');

const THEME_KEY = 'iksan.theme';
const LANG_KEY = 'iksan.lang';

/** Terapkan tema sebelum first paint. Dipanggil inline di <head>. */
function applyStoredTheme() {
    document.documentElement.setAttribute('data-theme', 'light');
}

applyStoredTheme();

/** Ikuti perubahan preferensi sistem bila pengguna belum memilih manual. */
window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', (event) => {
        if (!localStorage.getItem(THEME_KEY)) {
            document.documentElement.setAttribute(
                'data-theme',
                event.matches ? 'dark' : 'light',
            );
        }
    });

window.iksanTheme = {
    get current() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    },
    set(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        window.iksanTheme.perbaruiIkon();
    },
    toggle() {
        this.set(this.current === 'dark' ? 'light' : 'dark');
    },

    /**
     * Tampilkan ikon yang sesuai dengan tema aktif.
     *
     * Dulu pergantian ikon bersandar pada varian arbitrer Tailwind
     * [html[data-theme='dark']_&], yang rapuh: satu perubahan tanda kurung
     * membuat kedua ikon tampil bersamaan atau tidak tampil sama sekali, tanpa
     * galat apa pun. Sekarang kelasnya ditukar langsung, dan keadaannya
     * terbaca dari DOM.
     */
    perbaruiIkon() {
        const gelap = window.iksanTheme.current === 'dark';

        document.querySelectorAll('[data-tema-ikon]').forEach((el) => {
            const untukTemaGelap = el.dataset.temaIkon === 'gelap';
            el.classList.toggle('hidden', untukTemaGelap !== gelap);
            el.classList.toggle('block', untukTemaGelap === gelap);
        });
    },
};

window.iksanTheme.perbaruiIkon();

/**
 * Satu pendengar klik untuk seluruh halaman.
 *
 * CSP proyek ini tidak memuat 'unsafe-inline', sehingga atribut onclick di
 * dalam HTML tidak pernah dijalankan. Penanganan lewat data-aksi membuat
 * tombol tetap bekerja tanpa membuka 'unsafe-inline' dan tanpa bergantung
 * pada Alpine — penting karena tombol tema juga ada di halaman yang belum
 * menjalankan Alpine.
 */
document.addEventListener('click', (event) => {
    const pemicu = event.target.closest('[data-aksi]');

    if (!pemicu) {
        return;
    }

    const aksi = pemicu.dataset.aksi;

    // ganti-tema disabled

    // Tombol cari di mana pun: navbar, hero beranda, drawer.
    //
    // Panel pencarian ada di navbar, dan navbar memakai Alpine (x-data di
    // pembungkusnya). Yang dicari adalah pembungkus ITU — bukan x-data
    // terdekat — karena tombol di hero berada di luar navbar. Bila Alpine
    // belum siap, tombol jatuh ke halaman katalog supaya tidak mati.
    if (aksi === 'buka-cari') {
        // Header dirender dari partial navbar: penanda `.race-navbar__signal`
        // ada di dalamnya, dan pembungkus x-data adalah induk dua tingkat.
        const header = document.querySelector('header.race-navbar');
        const pembungkus = header?.parentElement;
        const data = pembungkus?._x_dataStack?.[0];

        if (data && 'cari' in data) {
            data.cari = true;
        } else {
            window.location.href = '/produk';
        }
    }

    // Halaman cetak: resi, invoice, surat jalan. Tombolnya memanggil
    // window.print() dari berkas ini, bukan dari atribut onclick, karena CSP
    // proyek ini tidak memuat 'unsafe-inline'.
    if (aksi === 'cetak') {
        window.print();
    }

    // Persetujuan cookie. Cookie izin ditulis dari sini supaya middleware
    // ZonaPelanggan dapat menulis cookie zona pada permintaan BERIKUTNYA:
    // menuliskannya dari server berarti membacanya pada permintaan yang sama,
    // saat pembeli belum menekan apa pun.
    if (aksi === 'cookie-setuju') {
        window.iksanCookie.setuju();
    }
});

/**
 * Pemberitahuan cookie.
 *
 * Nama cookie ditulis apa adanya di sini (bukan diterima dari server) karena
 * nilainya hanya perlu dibaca oleh skrip ini — sama dengan cara ZonaPelanggan
 * membacanya di PHP. Cookie ini tidak dienkripsi oleh Laravel dengan sengaja,
 * jadi keduanya membaca nilai yang sama.
 */
window.iksanCookie = {
    get key() {
        return 'iksan_izin';
    },

    get disetujui() {
        try {
            return document.cookie
                .split('; ')
                .some((baris) => baris.startsWith(this.key + '='));
        } catch (e) {
            return false;
        }
    },

    setuju() {
        // Satu tahun, sama dengan cookie zona. Hati-hati: tanpa SameSite,
        // cookie ini ikut terkirim pada permintaan lintas situs.
        document.cookie =
            this.key +
            '=1; path=/; max-age=31536000; SameSite=Lax' +
            (window.location.protocol === 'https:' ? '; Secure' : '');

        const notice = document.getElementById('cookie-notice');
        if (notice) {
            notice.classList.add('hidden');
        }
    },

    /** Tampilkan pemberitahuan hanya bila belum ada persetujuan. */
    perbarui() {
        if (this.disetujui) {
            return;
        }

        const notice = document.getElementById('cookie-notice');
        if (notice) {
            notice.classList.remove('hidden');
        }
    },
};

window.iksanCookie.perbarui();

window.iksanLang = {
    get current() {
        return document.documentElement.getAttribute('lang') || 'id';
    },
    set(locale) {
        localStorage.setItem(LANG_KEY, locale);
        document.documentElement.setAttribute('lang', locale);
    },
};

/* Bar navigasi bawah disembunyikan saat mengetik, agar tidak menutupi keyboard. */
document.addEventListener(
    'focusin',
    (event) => {
        const target = event.target;
        const typing =
            target instanceof HTMLElement &&
            (target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable) &&
            target.dataset.hideBottomNav !== 'false';
        document.documentElement.toggleAttribute('data-typing', typing);
    },
    true,
);

document.addEventListener(
    'focusout',
    () => document.documentElement.removeAttribute('data-typing'),
    true,
);

/* ── Muncul saat digulir ─────────────────────────────────────────────
 *
 * Bagian yang belum terlihat diredupkan sedikit lalu masuk saat mendekati
 * layar. Tujuannya bukan hiasan: tanpa ini seluruh beranda — hero, tentang,
 * empat kartu, CTA — dilukis penuh pada frame pertama, dan pengunjung menunggu
 * pekerjaan yang belum tentu ia lihat.
 *
 * Yang dipakai IntersectionObserver, bukan pendengar scroll: pendengar scroll
 * berjalan di setiap geseran dan memaksa tata letak dihitung ulang.
 *
 * Dua jebakan yang sudah kena dan dikunci di sini:
 *
 * 1. Livewire mengirim 'livewire:navigated' JUGA pada muat pertama. Versi
 *    pertama fungsi ini memanggil "tampilkan semua" di sana, sehingga animasi
 *    langsung habis sebelum terlihat. Sekarang peristiwa itu hanya MEMASANG
 *    ULANG pengamat, tidak menampilkan apa pun.
 * 2. Elemen yang sudah berada di layar pertama tidak boleh ikut dianimasikan,
 *    atau isinya berkedip masuk tepat setelah halaman tampil.
 */
const reveal = (() => {
    const PILIH = '[data-reveal]:not(.tampak)';
    const AWAL = 120;

    let pengamat = null;

    function tampilkan(el) {
        el.classList.add('tampak');
    }

    function amati() {
        if (pengamat) {
            pengamat.disconnect();
            pengamat = null;
        }

        if ('IntersectionObserver' in window) {
            pengamat = new IntersectionObserver(
                (entri) => {
                    for (const e of entri) {
                        if (!e.isIntersecting) continue;
                        tampilkan(e.target);
                        pengamat.unobserve(e.target);
                    }
                },
                // rootMargin bawah 120px: elemen mulai masuk sebelum menyentuh
                // tepi layar, jadi tidak terasa "muncul terlambat".
                { rootMargin: `0px 0px ${AWAL}px 0px`, threshold: 0.01 },
            );
        }

        document.querySelectorAll(PILIH).forEach((el) => {
            const kotak = el.getBoundingClientRect();
            const sudahDiLayar =
                kotak.top < window.innerHeight + AWAL && kotak.bottom > -AWAL;

            // Peramban tanpa IntersectionObserver: isi yang tak terbaca jauh
            // lebih buruk daripada animasi yang tak jalan.
            if (sudahDiLayar || pengamat === null) {
                tampilkan(el);

                return;
            }

            pengamat.observe(el);
        });
    }

    amati();

    document.addEventListener('livewire:navigated', amati);

    return { segarkan: amati };
})();

/* ── Cetak resi banyak (panel admin) ───────────────────────────────────
 *
 * Membaca kotak centang [data-cetak-resi] lalu mengarahkan peramban ke rute
 * cetak-resi dengan ULID terpilih. Satu berkas PDF berisi semua label.
 *
 * Sengaja BUKAN komponen Livewire dan bukan Alpine:
 *   - Livewire: setiap centang akan memicu permintaan ke server untuk sesuatu
 *     yang belum perlu dikirim. Yang diperlukan hanya daftar ULID saat tombol
 *     ditekan.
 *   - Alpine: (lihat catatan di pesanan-daftar.blade.php).
 *
 * Navigasi memakai window.location, bukan <form submit>: form di dalam tabel
 * admin bersarang dengan form Livewire dan membinggungkan peramban.
 */
(function () {
    function siapkan() {
        var bilah = document.getElementById('tombol-cetak-banyak');
        var label = document.getElementById('jumlah-terpilih');
        var wadah = document.getElementById('bilah-cetak');

        if (! bilah) return;

        function terpilih() {
            return Array.prototype.filter.call(
                document.querySelectorAll('[data-cetak-resi]'),
                function (k) { return k.checked; },
            );
        }

        function segarkan() {
            var daftar = terpilih();

            if (label) label.textContent = daftar.length + ' dipilih';

            bilah.disabled = daftar.length === 0;
            bilah.textContent = daftar.length > 1
                ? 'Cetak ' + daftar.length + ' resi sekaligus'
                : 'Cetak resi terpilih';

            // Seluruh bilah ikut hilang selama belum ada satu pun centang.
            // Dulu bilahnya selalu tampil dengan tombol mati — itu memakan
            // tempat tetap di halaman untuk sesuatu yang belum bisa dipakai,
            // dan mengundang pertanyaan "kenapa tidak bisa diklik?" padahal
            // jawabannya ada di kalimat sebelahnya.
            //
            // hidden (bukan x-show) dipilih dengan sengaja: blok ini harus
            // tetap bekerja tanpa Alpine, sama seperti tombol cetaknya.
            if (wadah) wadah.hidden = daftar.length === 0;
        }

        document.addEventListener('change', function (e) {
            if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-cetak-resi')) {
                segarkan();
            }
        });

        bilah.addEventListener('click', function () {
            var daftar = terpilih();

            if (daftar.length === 0) return;

            // Batas 100 label sekali cetak (ResiBanyak::BATAS). Diulang di sini
            // supaya admin tahu alasannya sebelum menunggu berkas gagal dibuat.
            if (daftar.length > 100) {
                window.alert('Maksimal 100 label sekali cetak. Kurangi pilihan Anda.');
                return;
            }

            var ulid = daftar
                .map(function (k) { return k.value; })
                .join(',');

            window.location.href = '/admin/pesanan/cetak-resi?ulid=' + encodeURIComponent(ulid);
        });

        segarkan();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', siapkan);
    } else {
        siapkan();
    }

    // Livewire mengganti isi halaman tanpa memuat ulang dokumen: simpul baru
    // harus disiapkan ulang.
    document.addEventListener('livewire:navigated', siapkan);
})();


/* ── Notifikasi push (pendaftaran perangkat) ───────────────────────────
 *
 * Alurnya, dan setiap langkah punya jalan gagal yang harus terlihat admin:
 *
 *   1. Service worker harus sudah terpasang. Di panel ia TIDAK terpasang
 *      otomatis — pendaftaran service worker hanya ada di layout depan
 *      (layouts/front.blade.php). Tanpa service worker, pushManager adalah
 *      undefined dan tombol izin tampak bekerja tanpa menghasilkan apa pun.
 *   2. Izin diminta lewat Notification.requestPermission().
 *   3. subscribe() memakai kunci VAPID publik dari server (data-kunci).
 *   4. Hasilnya dikirim ke Livewire lewat $wire.daftar().
 *
 * Kunci VAPID publik muncul di HTML sebagai atribut data. Itu benar: kunci
 * publik memang untuk dikirim ke peramban; yang rahasia hanya kunci privat
 * yang tetap di server.
 */
(function () {
    function siapkan() {
        var tombol = document.getElementById('tombol-izin-push');
        var pesan = document.getElementById('pesan-izin');

        if (!tombol) return;

        function tulis(teks, baik) {
            if (!pesan) return;
            pesan.textContent = teks;
            pesan.classList.remove('hidden');
            // Warna mengikuti isi yang bertetangga (plate warn-border-ok/warn),
            // bukan kelas baru: panel tidak punya kelas status tersendiri.
            pesan.style.borderLeft = baik ? '2px solid var(--ok)' : '2px solid var(--warn)';
            pesan.style.paddingLeft = '10px';
        }

        // Kunci VAPID publik dikirim sebagai base64url. API peramban minta
        // Uint8Array, dan konversinya bukan Base64 biasa: base64url memakai
        // '-' dan '_' sebagai pengganti '+' dan '/' serta tidak memakai '='.
        function keUint8Array(berkasKunci) {
            var padding = (4 - (berkasKunci.length % 4)) % 4;
            padding = padding === 3 ? 0 : padding;
            var base64 = (berkasKunci + padding === 0 ? berkasKunci : berkasKunci)
                .replace(/-/g, '+')
                .replace(/_/g, '/');
            if (padding) {
                base64 = base64 + '===='.slice(0, padding);
            }

            var mentah = window.atob(base64);
            var keluaran = new Uint8Array(mentah.length);

            for (var i = 0; i < mentah.length; i++) {
                keluaran[i] = mentah.charCodeAt(i);
            }

            return keluaran;
        }

        function ambilWire(el) {
            // Livewire 3 menempelkan helper ke properti $wire pada elemen.
            if (el.$wire) return el.$wire;

            for (var i = 0; i < el.attributes.length; i++) {
                var atribut = el.attributes[i];
                // Nama atribut membawa wire:id — yang dicari adalah komponennya.
                if (atribut.name === 'wire:id') {
                    var kunci = Livewire.find(atribut.value);
                    if (kunci) return kunci;
                }
            }

            return null;
        }

        tombol.addEventListener('click', function () {
            var kunci = tombol.dataset.kunci;
            var wire = ambilWire(tombol);

            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                tulis('Peramban ini tidak mendukung notifikasi push.', false);
                return;
            }

            if (!kunci) {
                tulis('Kunci VAPID belum dipasang di berkas .env.', false);
                return;
            }

            // Service worker dipanggil dari root: pendaftaran halaman depan
            // (layouts/front.blade.php) sudah memasanginya, dan hanya SW dengan
            // scope '/' yang boleh menerima push untuk seluruh situs.
            //
            // Versi di query string dibaca dari data-attribute <html> yang
            // ditulis layout (lihat App\Support\VersiAset). Alamat yang berubah
            // memaksa peramban memasang SW baru dan membuang cache lama.
            var versiSw = document.documentElement.getAttribute('data-sw-versi') || '';

            navigator.serviceWorker.getRegistration('/').then(function (daftar) {
                if (!daftar) {
                    // Belum ada? Daftarkan sekarang, jangan menuntut admin
                    // mengunjungi halaman depan dulu.
                    return navigator.serviceWorker.register('/sw.js?v=' + versiSw, { scope: '/' });
                }

                return daftar;
            }).then(function (daftar) {
                if (!daftar) {
                    tulis('Service worker tidak terpasang. Muat ulang halaman ini.', false);
                    return null;
                }

                return navigator.serviceWorker.ready.then(function () {
                    return daftar.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: keUint8Array(kunci),
                    });
                });
            }).then(function (langganan) {
                if (!langganan) return;

                var kunci = langganan.toJSON();

                if (!wire) {
                    tulis('Langganan dibuat di peramban, tetapi panel tidak menerimanya. Muat ulang lalu coba lagi.', false);
                    return;
                }

                wire.daftar(
                    kunci.endpoint,
                    kunci.keys.p256dh,
                    kunci.keys.auth,
                    namaPeramban()
                ).then(function () {
                    tulis('Perangkat ini akan menerima notifikasi.', true);
                });
            }).catch(function (galat) {
                // Salam NotFoundError punya arti khusus: izin ditolak, atau
                // service worker tidak menerima push. Yang lain dibiarkan
                // terbaca apa adanya daripada disimpulkan.
                var teks = 'Gagal: ' + (galat && galat.message ? galat.message : galat);

                if (galat && galat.name === 'NotAllowedError') {
                    teks = 'Izin notifikasi ditolak. Aktifkan di pengaturan peramban untuk situs ini.';
                }

                tulis(teks, false);
            });
        });

        function namaPeramban() {
            var ua = navigator.userAgent;

            if (/Firefox/.test(ua)) return 'Firefox';
            if (/Edg/.test(ua)) return 'Edge';
            if (/OPR/.test(ua)) return 'Opera';
            if (/Chrome/.test(ua)) return 'Chrome';
            if (/Safari/.test(ua)) return 'Safari';

            return 'lain';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', siapkan);
    } else {
        siapkan();
    }

    document.addEventListener('livewire:navigated', siapkan);
})();

/* ── Hero builder: seret-lepas urutan slide (panel admin) ──────────────
 *
 * Didaftarkan sebagai Alpine.data(), bukan x-data inline, supaya daftar id
 * ikut sebagai parameter. Dengan begitu urutan yang dikirim ke server adalah
 * urutan yang terlihat, bukan yang mungkin sudah berubah karena pembaruan
 * Livewire lain — tidak perlu membaca ulang DOM.
 *
 * Peringatan yang pernah menimpa halaman ini ("$wire.sunting is not a
 * function") jangan salah tempatkan: itu bukan soal scope x-data. Livewire
 * menyalin wire:click="sunting(N)" menjadi ekspresi Alpine "$wire.sunting(N)",
 * sementara proxy $wire memeriksa PROPERTI data sebelum method. Properti data
 * yang bernama sama dengan method membuat $wire.sunting menjadi angka, bukan
 * fungsi. Karena itu properti di HeroBuilder bernama suntingId.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('heroUrutan', (wire, ids) => ({
        menyeret: null,
        atas: null,

        taruh(posisi) {
            if (this.menyeret === null || this.menyeret === posisi) return;

            const daftar = ids.slice();
            const dipindah = daftar.splice(this.menyeret, 1)[0];
            daftar.splice(posisi, 0, dipindah);

            wire.terapkanUrutan(daftar);

            this.menyeret = null;
            this.atas = null;
        },
    }));
});


/**
 * Top navbar melayang + hide-on-scroll.
 *
 * Wujud sidebar KIRI sudah dihapus: blueprint redesign memakai bilah mendatar
 * (logo · menu · pencarian · wishlist · keranjang), dan sidebar ikut menutupi
 * hero. Sekarang hanya ada SATU wujud navbar, yaitu bilah di atas.
 *
 * Perilaku gulir:
 *   Y ≤ 50px        bilah tembus pandang di atas hero (belum melayang tegas)
 *   Y > 50px        bilah menjadi melayang: fixed top-0 w-full z-50 + blur
 *   gulir turun     bilah naik keluar (-translate-y-full) agar area baca luas
 *   gulir naik      bilah turun lagi
 *
 * Keadaan ditulis ke <html> supaya CSS tidak perlu membaca posisi gulir:
 *   data-bilah-tampil="1"   bilah sedang melayang
 *   data-gulir-arah         "naik" | "turun" | "netral"
 *
 * Selisih arah dihitung dari Y sebelumnya dengan ambang 6px: gulir 1–2px
 * karena trackpad atau jari yang berhenti tidak boleh membuat bilah berkedip.
 *
 * Tanpa JS: header tetap `sticky` seperti biasa — navigasi tidak pernah hilang.
 */
function pasangNavigasi() {
    const header = document.querySelector('header.race-navbar');
    if (!header) return;

    let yTerakhir = window.scrollY;
    const AMBANG = 30;

    const onScroll = () => {
        const y = window.scrollY;
        const selisih = y - yTerakhir;

        // Apakah sudah lewat puncak
        if (y > AMBANG) {
            header.classList.add('navbar-melayang');
            
            // Scroll ke bawah: sembunyikan navbar
            if (selisih > 5 && y > 100) {
                header.classList.add('navbar-sembunyi');
            } 
            // Scroll ke atas: tampilkan navbar
            else if (selisih < -5) {
                header.classList.remove('navbar-sembunyi');
            }
        } else {
            // Kembali di puncak
            header.classList.remove('navbar-melayang');
            header.classList.remove('navbar-sembunyi');
        }

        yTerakhir = y;
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

document.addEventListener('DOMContentLoaded', pasangNavigasi);
document.addEventListener('livewire:navigated', () => {
    // Livewire mengganti isi halaman tanpa memuat ulang dokumen; keadaan gulir
    // dan tinggi hero bisa berbeda, jadi atributnya dibersihkan lalu dihitung
    // ulang.
    document.documentElement.removeAttribute('data-bilah-tampil');
    pasangNavigasi();
});

/**
 * Angka pada tombol hero (suka & keranjang).
 *
 * Kedua tombol ada di baris atas hero, sementara hitungannya sudah dikerjakan
 * komponen Livewire yang ada:
 *
 *   keranjang  livewire:front.jumlah-keranjang  (ikut `keranjang-berubah`)
 *   suka       livewire:front.suka-produk       (ikut `suka-berubah`)
 *
 * Menyalin angkanya lebih murah daripada membuat komponen baru: satu kueri
 * untuk seluruh halaman, dan perubahan langsung terlihat karena komponen asli
 * sudah menulis ulang angkanya sendiri lalu memicu event.
 *
 * Bila penanda tidak ada (halaman tanpa hero), fungsi ini tidak melakukan
 * apa pun.
 */
function pasangAngkaHero() {
    const angkaKeranjang = document.querySelector('[data-hero-keranjang]');
    const angkaSuka = document.querySelector('[data-hero-suka]');
    const sumberKeranjang = document.querySelector('[data-jumlah-keranjang]');

    const salinKeranjang = () => {
        if (!angkaKeranjang) return;

        const teks = (sumberKeranjang?.textContent || '').replace(/\D/g, '');
        const jumlah = Number(teks || 0);

        angkaKeranjang.textContent = jumlah > 99 ? '99+' : String(jumlah);
        angkaKeranjang.hidden = jumlah === 0;
    };

    // Suka: tombol di hero menampilkan berapa produk yang disukai pengunjung
    // ini. Angkanya TIDAK dihitung ulang dari DOM setiap saat (sebelumnya
    // salah: menghitung `[data-suka-aktif]` mengukur produk yang disukai di
    // halaman ini, bukan seluruh toko). Nilai awalnya dibaca dari penanda
    // `data-jumlah-suka` yang disiapkan Blade, lalu diperbarui lewat event
    // `suka-berubah` yang membawa `detail.disukai`.
    let jumlahSuka = Number(document.querySelector('[data-jumlah-suka]')?.dataset.jumlahSuka || 0);

    const tulisSuka = (jumlah) => {
        if (!angkaSuka) return;
        angkaSuka.textContent = jumlah > 99 ? '99+' : String(jumlah);
        angkaSuka.hidden = jumlah === 0;
    };

    tulisSuka(jumlahSuka);
    salinKeranjang();

    // Komponen Livewire menulis ulang angkanya lewat DOM; mengamati teksnya
    // adalah cara paling sederhana untuk ikut tanpa mengubah komponennya.
    if (sumberKeranjang && 'MutationObserver' in window) {
        new MutationObserver(salinKeranjang).observe(sumberKeranjang, {
            subtree: true,
            childList: true,
            characterData: true,
        });
    }

    window.addEventListener('keranjang-berubah', salinKeranjang);
    window.addEventListener('suka-berubah', (e) => {
        if (typeof e.detail?.disukai !== 'boolean') return;

        jumlahSuka = Math.max(0, jumlahSuka + (e.detail.disukai ? 1 : -1));
        tulisSuka(jumlahSuka);
    });
}

document.addEventListener('DOMContentLoaded', pasangAngkaHero);
document.addEventListener('livewire:navigated', pasangAngkaHero);

/* Ukuran teks brand navbar menyesuaikan panjang nama toko (nama panjang
   mengecil sampai muat, bukan terpotong elipsis). Berkas terpisah supaya
   app.js tetap ringan; logika spesifik navbar satu tanggung jawab. */
import './ukuran-brand.js';
