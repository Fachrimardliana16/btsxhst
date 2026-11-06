/* ══════════════════════════════════════════════════════════════════════
 * Ukuran brand navbar menyesuaikan panjang nama toko.
 *
 * Nama pendek  → tetap besar (2xl). Nama panjang → mengecil bertahap
 * sampai muat, BUKAN terpotong elipsis. Tanpa library: ukur lebar teks
 * dengan canvas 2D, turunkan font-size sampai muat di slot kolom kiri,
 * lalu tulis sebagai `--brand-fs` (dibaca inline style di blade).
 *
 * Slot yang dipakai BUKAN clientWidth kolom kiri — kolom kiri grid 1fr
 * melar mengikuti konten, jadi kalau kontennya yang diukur, hasilnya
 * lingkaran setan (kolom melar → teks dianggap muat → kolom makin lar).
 * Slot sejati = bilah − menu − utilitas − gap kolom.
 *
 * Berjalan saat muat, saat navigasi Livewire, saat font selesai dimuat,
 * dan saat bilah berubah lebar (ResizeObserver).
 */
function pasangUkuranBrand() {
    const merek = document.querySelector('.navbar-merek');
    if (!merek) return;

    const teksEl = merek.querySelector('span.truncate') || merek;
    const bilah = merek.closest('.navbar-bilah');
    if (!bilah) return;

    /* MENGUKUR DENGAN scrollWidth, BUKAN getBoundingClientRect.
     *
     * Riwayat dua kesalahan pengukuran, keduanya terbukti di layar 390px:
     *
     * 1. Canvas 2D (`measureText` + letter-spacing manual):
     *      perkiraan 211px, lebar nyata 239px → selisih 28px.
     *      Font web belum termuat saat diukur, dan letter-spacing tidak
     *      bertambah secara linear seperti yang diasumsikan.
     *
     * 2. `getBoundingClientRect()` pada span di dalam flex:
     *      terbaca 218px, padahal teksnya 239px.
     *      Rect mengembalikan lebar yang SUDAH TERBATAS wadah (230px dikurangi
     *      dot 6px dan gap 6px), bukan lebar teks. Akibatnya skrip selalu
     *      menyimpulkan "muat" dan tidak pernah mengecilkan font.
     *
     * `scrollWidth` adalah lebar isi sesungguhnya, tanpa dipotong wadah —
     * itulah yang harus dibandingkan dengan ruang tersedia. Pengukurannya
     * dilakukan di `lebarPada()` di dalam fit(), karena nilainya bergantung
     * pada ukuran font yang sedang diuji. */

    const fit = () => {
        const menu = bilah.querySelector('.navbar-menu');
        const util = bilah.querySelector('.navbar-utilitas');
        const dot = merek.querySelector('.bg-accent');
        const gapKolom = 8; // column-gap .navbar-bilah 0.5rem

        let slot = bilah.clientWidth - gapKolom * 2 - 2;
        if (menu && menu.offsetParent !== null) slot -= menu.getBoundingClientRect().width;
        if (util) slot -= util.getBoundingClientRect().width;
        if (dot) slot -= 12; // dot 6px + gap 6px
        // padding horizontal bilah, kalau ada
        const csb = getComputedStyle(bilah);
        slot -= (parseFloat(csb.paddingLeft) || 0) + (parseFloat(csb.paddingRight) || 0);

        // Sisakan 8px napas supaya teks tidak menempel ke tombol pencarian.
        slot -= 8;

        if (slot <= 24) {
            // Layar sangat sempit: jangan biarkan teks meluber, potong saja.
            merek.style.setProperty('--brand-fs', '12px');
            teksEl.style.minWidth = '0';
            teksEl.style.maxWidth = '';
            teksEl.style.whiteSpace = 'nowrap';
            teksEl.style.overflow = 'hidden';
            teksEl.style.textOverflow = 'ellipsis';
            return;
        }

        const dasar = parseFloat(getComputedStyle(bilah).getPropertyValue('--brand-fs-dasar'))
                   || parseFloat(getComputedStyle(merek).fontSize)
                   || 24;
        const terkecil = 12;

        /* Ukur lebar teks pada ukuran font TERTENTU.
         *
         * `scrollWidth` hanya memberi lebar isi bila overflow BUKAN visible.
         * Karena itu ukuran diambil dengan `overflow: hidden` + `text-overflow:
         * clip` sementara, supaya tidak ada ellipsis yang mengubah hasil ukur,
         * lalu gaya aslinya dipulihkan. */
        const lebarPada = (fs) => {
            merek.style.setProperty('--brand-fs', fs + 'px');
            teksEl.style.overflow = 'hidden';
            teksEl.style.textOverflow = 'clip';
            const w = teksEl.scrollWidth;
            teksEl.style.textOverflow = '';
            return w;
        };

        // Cari ukuran terbesar yang muat, dari besar ke kecil.
        //
        // Kelonggaran 3% + 2px, bukan angka keramat. Dua sumber selisih yang
        // sudah terbukti saat pengujian:
        //
        //   1. scrollWidth vs lebar render akhir bisa berbeda 1-2px karena
        //      pembulatan subpiksel saat font di-hinting.
        //   2. Ruang yang benar-benar tersedia untuk teks lebih kecil dari
        //      `slot`: dot (6px) dan gap flex (6px) memakan tempat di dalam
        //      .navbar-merek, dan itu tidak selalu terhitung di `slot`.
        //
        // Tanpa kelonggaran, teks lolos uji "muat" tetapi tetap terpotong
        // 4px secara visual (terbukti pada layar 320px).
        const batas = Math.floor(slot * 0.97) - 2;

        let fs = dasar;
        let lebar = lebarPada(fs);
        while (fs > terkecil && lebar > batas) {
            fs = Math.max(terkecil, fs - 1);
            lebar = lebarPada(fs);
        }

        const muat = lebar <= batas;

        if (!muat) {
            // Batas terkecil tercapai tetapi masih tidak muat (nama sangat
            // panjang atau layar sangat sempit). Ellipsis adalah pilihan yang
            // benar di sini: teks terpotong rapi jauh lebih baik daripada teks
            // yang menimpa tombol pencarian di sebelahnya.
            teksEl.style.minWidth = '0';
            teksEl.style.maxWidth = '';
            teksEl.style.whiteSpace = 'nowrap';
            teksEl.style.overflow = 'hidden';
            teksEl.style.textOverflow = 'ellipsis';
        } else {
            // Muat: tampilkan utuh tanpa ellipsis.
            teksEl.style.minWidth = '0';
            teksEl.style.maxWidth = 'none';
            teksEl.style.whiteSpace = 'nowrap';
            teksEl.style.overflow = 'visible';
            teksEl.style.textOverflow = '';
        }
    };

    fit();

    if ('ResizeObserver' in window) {
        pasangUkuranBrand._ro?.disconnect();
        pasangUkuranBrand._ro = new ResizeObserver(() => fit());
        pasangUkuranBrand._ro.observe(bilah);
    }
    // Font web mungkin selesai dimuat setelah pengukuran pertama → ukur lagi.
    if (document.fonts?.ready) {
        document.fonts.ready.then(fit);
    }
}

/* MODULE: `import` di-hoist, jadi kode di sini berjalan SEBELUM
 * DOMContentLoaded pada sebagian besar kasus — tetapi skrip `type="module"`
 * juga bisa dieksekusi SETELAH DOMContentLoaded (mis. saat dimuat dari cache
 * atau setelah hydrate). Kalau itu terjadi, listener di bawah tidak pernah
 * terpanggil dan brand tidak pernah diukur sama sekali.
 *
 * Karena itu: kalau dokumen sudah siap, jalankan langsung. */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pasangUkuranBrand);
} else {
    pasangUkuranBrand();
}
document.addEventListener('livewire:navigated', pasangUkuranBrand);

// Terekspos untuk pengujian konsol & panel admin yang mengubah nama toko
// via Livewire tanpa navigasi penuh.
window.pasangUkuranBrand = pasangUkuranBrand;
