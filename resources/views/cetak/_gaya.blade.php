{{--
    Gaya dasar untuk dokumen cetak (label, resi, invoice, surat jalan).

    Dipisah sebagai partial agar SATU sumber untuk semua dokumen cetak, dan
    supaya dokumen yang dirangkai dalam satu PDF juga memakai gaya yang sama.

    Variabel:
      $lebar  — lebar halaman dalam CSS (mis. "100mm" untuk label, "210mm" A4)
      $tinggi — tinggi halaman dalam CSS (mis. "150mm" untuk label, "297mm" A4)
      $margin — margin halaman dalam CSS (mis. "4mm")

    Kenapa tidak memuat CSS aplikasi (resources/css/app.css): berkas itu
    berisi aturan untuk layar — variabel tema, media query, utility Tailwind.
    Dompdf memprosesnya sebagian, mengabaikan sisanya tanpa pesan, dan hasilnya
    berubah setiap kali CSS aplikasi disunting. Gaya di sini sengaja sedikit
    dan mandiri.

    Tata letak label memakai <table>, bukan flexbox. dompdf tidak mengenal
    flex: `display:flex` diabaikan tanpa pesan, dan dua kolom yang seharusnya
    berdampingan tercetak bertumpuk. Table adalah satu-satunya cara menaruh
    dua blok bersebelahan yang benar-benar didukung dompdf.
--}}
<style>
    @page { size: {{ $lebar ?? '100mm' }} {{ $tinggi ?? '150mm' }}; margin: {{ $margin ?? '4mm' }}; }

    * { box-sizing: border-box; }

    /* Margin halaman ditulis pada @page, bukan pada html.
       dompdf memakai margin elemen html sebagai pengganti margin @page:
       `html { margin: 0 }` menghapus margin 4mm dan seluruh label bergeser
       ke tepi kertas — terukur, isi mulai dari piksel 0. Body saja. */
    body {
        margin: 0;
        padding: 0;
        font-family: 'DejaVu Sans', ui-monospace, monospace;
        font-size: 10px;
        line-height: 1.3;
        color: #000;
        background: #fff;
        /* Alamat panjang tanpa spasi (nomor resi, kode pos) tidak boleh
           melebarkan tabel melewati lebar kertas. Tanpa ini dompdf membiarkan
           baris memanjang keluar halaman, dan tepi kanan label terpotong. */
        overflow-wrap: break-word;
        word-wrap: break-word;
    }

    /*
     * Satu label per halaman. Ditulis pada wadah, bukan pada baris pemisah
     * terpisah: dua page-break untuk satu label menghasilkan halaman kosong
     * di tengah rangkaian, dan label yang dicetak jadi dua kali lipat
     * jumlah pesanan yang dipilih.
     *
     * .label-terakhir memutus break untuk label paling akhir. Dipakai kelas
     * eksplisit, bukan :last-child: dompdf mengabaikan :last-child pada aturan
     * ini tanpa pesan, dan label terakhir yang tetap memaksa break menghasilkan
     * satu halaman kosong sesudahnya. Terukur: 4 label -> 5 halaman, dan
     * halaman kelima kosong (646 byte, hanya latar).
     */
    .label {
        page-break-after: always;
        break-after: page;
    }

    .label-terakhir {
        page-break-after: auto !important;
        break-after: auto !important;
    }

    .tengah { text-align: center; }
    .kanan { text-align: right; }
    .rapat { margin: 0; }
    .besar { font-size: 14px; font-weight: 700; }
    .kecil { font-size: 9px; }
    .alamat { font-size: 10px; line-height: 1.3; }

    .putus { border-top: 1px dashed #000; margin: 6px 0; }
    .tebal { border-top: 2px solid #000; margin: 6px 0; }

    .baris { display: flex; justify-content: space-between; gap: 8px; }

    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }

    .kotak-resi {
        border: 1.5px solid #000;
        padding: 4px;
        margin: 5px 0;
        text-align: center;
    }
    .nomor-resi { font-size: 13px; font-weight: 700; letter-spacing: 0.06em; }

    /* ── Label pengiriman ─────────────────────────────────────────────
       Susunan mengikuti label kurir yang biasa dilihat penerima: nama
       kurir paling atas, barcode, lalu penerima sebagai blok terbesar.
       Kurir memindai barcode dan membaca nama/kota penerima; dua hal itu
       yang harus paling jelas, bukan nama toko. */

    .kepala-label { width: 100%; border-bottom: 2px solid #000; table-layout: fixed; }
    .kepala-label td { padding: 0 0 1.6mm; vertical-align: middle; }

    .kurir {
        font-size: 19px;
        font-weight: 700;
        line-height: 1;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .layanan {
        display: inline-block;
        border: 1.5px solid #000;
        padding: 0.8mm 1.8mm;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .cod {
        display: inline-block;
        margin-top: 1mm;
        border: 1px solid #000;
        padding: 0.5mm 1.5mm;
        font-size: 7.5px;
        font-weight: 700;
        letter-spacing: 0.08em;
    }

    .barcode { text-align: center; padding: 1.2mm 0 0.6mm; }
    .barcode img { width: 84mm; height: 12mm; display: block; margin: 0 auto; }

    .resi-teks {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin-top: 0.8mm;
    }

    .blok { padding: 1.4mm 0; border-bottom: 1px dashed #000; }
    .blok-akhir { border-bottom: 0; }

    .judul-kecil {
        font-size: 7.5px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .nama-penerima { font-size: 14px; font-weight: 700; line-height: 1.15; margin-top: 0.4mm; }
    .telp-penerima { font-size: 10.5px; font-weight: 700; margin-top: 0.4mm; }
    .alamat-penerima { font-size: 9.5px; line-height: 1.35; margin-top: 0.6mm; }
    .kota-penerima { font-size: 10.5px; font-weight: 700; text-transform: uppercase; margin-top: 0.6mm; }

    .pengirim-label { font-size: 8.5px; line-height: 1.35; margin-top: 0.3mm; }

    .meta-label { width: 100%; font-size: 8.5px; table-layout: fixed; }
    .meta-label td { padding: 0.6mm 0; vertical-align: top; }

    .isi-baris { font-size: 8.5px; line-height: 1.45; }
    .isi-qty { display: inline-block; width: 7mm; font-weight: 700; }

    .kaki-label {
        font-size: 7.5px;
        text-align: center;
        margin-top: 1mm;
        line-height: 1.35;
    }
</style>
