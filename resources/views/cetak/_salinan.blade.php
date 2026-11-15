{{--
    Tanda air dokumen batal (checklist F8-09).

    Variabelnya opsional: `$salinan ?? false`. Halaman detail pesanan
    menampilkan komponen-komponen cetak sebagai pratinjau, dan di sana
    variabel ini tidak dikirim — sehingga tanpa nilai cadangan, Blade melempar
    galat dan seluruh halaman detail ikut gagal.

    SEBUAH blok, sependek mungkin, dan berada DI DALAM aliran dokumen.

    Terukur pada label A5: dua blok berturut-turut (pita merah lalu
    cakram "SALINAN") cukup mendorong label yang sudah berisi empat barang
    meluap menjadi dua halaman. Dalam berkas resi banyak, itu berarti setiap
    pesanan batal menghasilkan satu halaman berisi label dan satu halaman
    berisi sisa aliran yang terdorong — label terbelah, menempel di paket
    yang salah. Satu blok tetap membuat dokumen tidak terbaca sebagai dokumen
    sah; yang dibutuhkan bukan ukuran huruf besar, melainkan penanda yang
    terbaca dan tidak merusak tata letak.
--}}
@if ($salinan ?? false)
    {{-- Satu baris tipis, bukan kotak tinggi: label A5 sudah penuh
         oleh blok penerima, dan penanda setinggi 9 mm mendorong catatan serta
         kalimat penutup ke halaman kedua — label terbelah, lalu ditempel di
         paket yang salah. --}}
    <div style="border:1px solid #b42318;color:#b42318;padding:1px 4px;margin:0 0 1.5mm;font-size:8.5px;font-weight:700;letter-spacing:.04em;text-align:center">
        SALINAN &middot; PESANAN INI SUDAH DIBATALKAN
    </div>
@endif
