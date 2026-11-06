{{-- Lonceng pesan panel.

     Daftar sengaja pendek: lonceng ini pintu masuk ke halaman Pesan, bukan
     penggantinya. Yang perlu daftar lengkap, saringan, dan riwayat percakapan
     membuka halamannya lewat "Lihat semua" di kaki panel.

     Pesan yang sudah dibaca tetap tampil, tetapi redup — menyembunyikannya
     membuat admin mengira pesannya hilang. --}}
<div class="relative" wire:key="lonceng-pesan">

    @php
        // Label lonceng dipakai di dua tempat (title & aria-label) dan harus
        // menyebut jumlah pesan belum dibaca. Dihitung sekali di sini supaya
        // teksnya tidak mungkin berbeda antara tooltip dan pembaca layar.
        $labelLonceng = $belum > 0
            ? __('site.admin.lonceng_pesan.belum_dibaca', ['jumlah' => $belum])
            : __('site.admin.lonceng_pesan.pesan');
    @endphp

    <button type="button" class="btn-icon relative" wire:click="alihkan"
            aria-expanded="{{ $buka ? 'true' : 'false' }}"
            title="{{ $labelLonceng }}"
            aria-label="{{ $labelLonceng }}">
        <x-ikon name="mail" :size="19" />

        @if ($belum > 0)
            <span class="dot-count" aria-hidden="true">{{ $belum > 9 ? '9+' : $belum }}</span>
        @endif
    </button>

    @if ($buka)
        <div class="fixed inset-0 z-40" wire:click="alihkan" aria-hidden="true"></div>

        {{-- Posisi FIXED, bukan absolute + right-0: tombol lonceng ada di tengah
             header, jadi panel absolute akan diposisikan relatif tombol dan
             meluber ke kiri tepi layar pada ponsel. Lihat catatan panjang di
             lonceng-notifikasi.blade.php. --}}
        <div class="fixed inset-x-0 top-full z-50 mx-auto mt-2 w-[min(92vw,22rem)] px-4 sm:px-0"
             role="dialog" aria-label="{{ __('site.admin.lonceng_pesan.daftar') }}">
            <div class="plate-up">

            <div class="flex items-center justify-between gap-2 border-b border-line-soft px-4 py-3">
                <p class="part-no">
                    {{ __('site.admin.lonceng_pesan.judul') }}
                    @if ($belum > 0)
                        <span class="text-dim">· {{ $belum }} {{ __('site.admin.umum.belum_dibaca') }}</span>
                    @endif
                </p>
            </div>

            @if ($daftar->isEmpty())
                <div class="p-6 text-center">
                    <x-ikon name="mail" :size="26" class="mx-auto text-dim" />
                    <p class="mt-2 text-sm font-medium">{{ __('site.admin.lonceng_pesan.kosong') }}</p>
                    <p class="mt-1 text-xs text-muted">
                        {{ __('site.admin.lonceng_pesan.kosong_hint') }}
                    </p>
                </div>
            @else
                <ul class="max-h-[70vh] divide-y divide-line-soft overflow-y-auto">
                    @foreach ($daftar as $p)
                        <li @class(['bg-sunken/40' => $p->is_read]) wire:key="pesan-{{ $p->id }}">
                            <button type="button" wire:click="bukaPesan({{ $p->id }})"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-sunken">

                                <span class="mt-0.5 flex-none rounded-[var(--r-kecil)] p-1.5
                                    @if ($p->is_read)
                                        bg-line-soft/60 text-dim
                                    @else
                                        bg-accent/12 text-accent
                                    @endif">
                                    <x-ikon name="mail" :size="17" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium @if ($p->is_read) text-muted @endif">
                                        {{ $p->subject ?: __('site.admin.umum.tanpa_subjek') }}
                                    </span>

                                    <span class="mt-0.5 block truncate text-xs text-muted">
                                        {{ $p->name }}
                                        @if ($p->replied_at !== null)
                                            · {{ __('site.admin.lonceng_pesan.sudah_dibalas') }}
                                        @endif
                                    </span>
                                </span>

                                @if ($p->is_read)
                                    <x-ikon name="check" :size="15" class="mt-1 flex-none text-ok" />
                                @else
                                    <span class="mt-2 h-2 w-2 flex-none rounded-full bg-accent"
                                          aria-label="{{ __('site.admin.umum.belum_dibaca') }}"></span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-line-soft p-2">
                    <a href="{{ route('admin.messages.index') }}" wire:navigate wire:click="alihkan"
                       class="btn btn-ghost w-full justify-center text-sm">
                        {{ __('site.admin.lonceng_pesan.lihat_semua') }}
                        <x-ikon name="arrow-right" :size="16" />
                    </a>
                </div>
            @endif
            </div>
        </div>
    @endif
</div>
