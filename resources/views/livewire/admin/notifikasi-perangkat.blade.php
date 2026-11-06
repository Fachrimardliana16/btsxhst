{{-- Pendaftaran notifikasi perangkat (web push).

     Sengaja berisi hanya tiga tombol: Izinkan, {{ __('site.admin.notifikasi_perangkat.matikan') }}, Uji. Menjelaskan
     cara kerja web push di sini tidak akan dibaca siapa pun — yang dibutuhkan
     admin adalah tombol yang bekerja dan pesan yang jujur ketika tidak. --}}
<div class="max-w-2xl space-y-5">

    <div>
        <p class="part-no">{{ __('site.admin.notifikasi_perangkat.eyebrow') }}</p>
        <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.notifikasi_perangkat.judul') }}</h1>
        <p class="mt-1 text-sm text-muted">
            {{ __('site.admin.notifikasi_perangkat.keterangan') }}
        </p>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-l-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @unless ($siap)
        <div class="plate border-l-2 border-l-warn p-4 text-sm">
            <p class="font-semibold">{{ __('site.admin.notifikasi_perangkat.kunci_belum') }}</p>
            <p class="mt-1 text-muted">
                {{ __('site.admin.notifikasi_perangkat.tambahkan') }} <code class="tech">VAPID_PUBLIC_KEY</code> dan
                <code class="tech">VAPID_PRIVATE_KEY</code> ke berkas
                <code class="tech">.env</code>, lalu jalankan
                <code class="tech">php artisan config:clear</code>.
                {{ __('site.admin.notifikasi_perangkat.kunci_cara') }}
            </p>
        </div>
    @endunless

    <div class="plate p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium">{{ $jumlahPerangkat }} perangkat terdaftar</p>
                <p class="mt-0.5 text-sm text-muted">
                    @if ($jumlahPerangkat > 0)
                        Notifikasi dikirim ke semua perangkat terdaftar. {{ __('site.admin.notifikasi_perangkat.matikan') }} akan menghapus milik akun Anda.
                    @else
                        Belum ada perangkat. Izinkan notifikasi dari peramban ini.
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="tombol-izin-push" class="btn btn-primary"
                        @disabled($aktif || ! $siap) data-kunci="{{ $kunciPublik }}">
                    <x-ikon name="bell" :size="16" />
                    {{ $aktif ? 'Sudah aktif' : 'Izinkan notifikasi' }}
                </button>

                @if ($aktif)
                    <button type="button" wire:click="matikan" class="btn btn-outline">{{ __('site.admin.notifikasi_perangkat.matikan') }}</button>

                    <button type="button" wire:click="ujiPush" class="btn btn-ghost">{{ __('site.admin.notifikasi_perangkat.kirim_uji') }}</button>
                @endif
            </div>
        </div>

        {{-- Kotak pesan untuk peramban: izin bisa ditolak, service worker bisa
             tidak terpasang, iOS di bawah 16.4 tidak mendukung sama sekali.
             Yang ditampilkan pesan yang bisa dibaca admin, bukan galat JS. --}}
        <p id="pesan-izin" class="mt-3 hidden text-sm" role="status"></p>
    </div>

    <div class="plate p-4 text-sm text-muted">
        <p class="font-medium text-ink">{{ __('site.admin.notifikasi_perangkat.kenapa_judul') }}</p>
        <ul class="mt-2 space-y-1">
            <li>{{ __('site.admin.notifikasi_perangkat.jendela_privat') }}</li>
            <li>{{ __('site.admin.notifikasi_perangkat.blokir_peramban') }}</li>
            <li>{{ __('site.admin.notifikasi_perangkat.ios_syarat') }}</li>
            <li>{{ __('site.admin.notifikasi_perangkat.android_baterai') }}</li>
        </ul>
    </div>
</div>
