{{--
    KARTU PEMILIH KENDARAAN ("My Garage") — blueprint bagian 5B.

    Kartu ini DUDUK DI ALUR, tidak lagi menimpa hero. Versi sebelumnya memakai
    margin negatif (-mt-8/-mt-12/-mt-16) sehingga menutupi bagian bawah hero —
    termasuk kendali slide — dan di ponsel tertimpa bar navigasi bawah. Audit
    tata letak mengukurnya: irisan 32px (ponsel), 48px (tablet), 64px (desktop).

    Tampilannya sudah siap, tetapi
    DATANYA BELUM TERSAMBUNG: tabel kendaraan + relasi part-kendaraan adalah
    Tahap 2. Karena itu:

      - kartu ini hanya dirender bila diaktifkan lewat Pengaturan
        `hero_garage_aktif` = 1, supaya halaman tidak memuat janji fitur
        yang belum berfungsi;
      - isi pemilihnya memakai <select> kategori yang SUDAH ada, sehingga
        tombolnya benar-benar menyaring katalog hari ini;
      - begitu Tahap 2 selesai, isi kartu ini cukup diganti pemilih
        merek→model→tahun tanpa mengubah tata letak.

    Teksnya sengaja tidak menyebut "garansi pas" — itu klaim yang baru bisa
    diberikan setelah relasi part-kendaraan ada.
--}}
@php
    /** @var \Illuminate\Support\Collection $kategori */
    $kategoriGarage = $kategori ?? collect();
    $aktif = (string) \App\Support\Pengaturan::instance()->teks('hero_garage_aktif') === '1';
@endphp

@if ($aktif && $kategoriGarage->isNotEmpty())
    <div class="container-page">
        <div class="garage-card mt-6 md:mt-8">
            <form action="{{ route('front.products.index') }}" method="get"
                  class="flex flex-col gap-3 md:flex-row md:items-end md:gap-4">

                <div class="flex-1">
                    <label for="garage-kategori" class="part-no block">
                        {{ __('site.home.garage_label') }}
                    </label>
                    <select id="garage-kategori" name="kategori"
                            class="field mt-2 w-full !min-h-[44px]">
                        <option value="">{{ __('site.home.garage_semua') }}</option>
                        @foreach ($kategoriGarage as $kat)
                            <option value="{{ $kat->slug }}">{{ $kat->nama() }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary md:w-auto">
                    {{ __('site.home.garage_tombol') }}
                    <x-ikon name="arrow-right" :size="16" />
                </button>
            </form>
        </div>
    </div>
@endif
