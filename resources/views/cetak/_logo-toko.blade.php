{{--
    Logo toko untuk kepala dokumen cetak (label resi, invoice, surat jalan).

    Satu sumber supaya ketiga dokumen memakai gambar yang sama bila logo di
    Site Settings diganti. Urutannya: logo unggahan, lalu logo bawaan di
    public/brand, lalu nama toko sebagai teks — dokumen tidak boleh tercetak
    tanpa identitas toko hanya karena belum ada logo yang diunggah.

    Kelas .logo-toko/.merek diatur di cetak/_gaya.blade.php.
--}}
@php
    /** @var \App\Support\Pengaturan $toko */

    $logoJalur = $toko->teks('site_logo_light') ?: $toko->teks('site_logo_dark');
    $logoBerkas = null;

    if (filled($logoJalur)) {
        $kandidat = storage_path('app/public/'.ltrim((string) $logoJalur, '/'));

        if (is_file($kandidat)) {
            $logoBerkas = $kandidat;
        }
    }

    if ($logoBerkas === null && is_file(public_path('brand/logo-gelap.png'))) {
        $logoBerkas = public_path('brand/logo-gelap.png');
    }
@endphp

@if ($logoBerkas !== null)
    <img class="logo-toko" src="{{ $logoBerkas }}" alt="{{ $toko->teks('site_name', 'Bites Exhaust') }}">
@else
    <span class="merek">{{ $toko->teks('site_name', 'Bites Exhaust') }}</span>
@endif
