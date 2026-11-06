{{-- {{ __('site.admin.keranjang_terbuka.judul') }}.

     Halaman ini menjawab satu pertanyaan: barang apa yang masuk keranjang
     lalu ditinggalkan, dan oleh siapa. Karena itu isinya DAFTAR, bukan
     papan angka — empat kartu statistik versi lama (keranjang aktif, nilai
     tertahan, baru, mengendap) menjawab pertanyaan yang tidak punya tindakan
     lanjut di halaman ini. Angka yang masih berguna kini satu baris di kepala,
     dan daftar produk yang paling sering tertinggal pindah ke atas supaya
     terbaca tanpa menggulir.

     Nama halaman diganti dari "Keranjang Terbuka": istilah itu dipakai juga
     untuk keranjang tamu yang bisa dilihat siapa saja, sementara halaman ini
     khusus barang yang BELUM dibayar. "Ditinggalkan" mengatakan apa adanya. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.keranjang_terbuka.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.keranjang_terbuka.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ __('site.admin.keranjang_terbuka.keterangan') }}
                {{ number_format($ringkas['total'], 0, ',', '.') }} keranjang ·
                nilai {{ rupiah($ringkas['nilai']) }} ·
                {{ number_format($ringkas['harian'], 0, ',', '.') }} {{ __('site.admin.keranjang_terbuka.bergerak') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-outline" wire:click="perbarui"
                    wire:loading.attr="disabled" wire:target="perbarui">
                <x-ikon name="refresh" :size="16" />
                <span wire:loading.remove wire:target="perbarui">{{ __('site.admin.keranjang_terbuka.segarkan') }}</span>
                <span wire:loading wire:target="perbarui">{{ __('site.admin.keranjang_terbuka.memuat') }}</span>
            </button>

            <button type="button" class="btn btn-outline text-danger"
                    wire:click="bersihkanKedaluwarsa"
                    wire:confirm="{{ __('site.admin.keranjang_terbuka.buang_tua') }}?"
                    title="{{ __('site.admin.keranjang_terbuka.buang_tua') }}">
                <x-ikon name="trash" :size="16" />
                {{ __('site.admin.keranjang_terbuka.bersihkan') }}
            </button>
        </div>
    </div>

    @if (session('pesan-admin'))
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ session('pesan-admin') }}</div>
    @endif

    {{-- ── Produk yang paling sering ditinggalkan ────────────────────── --}}
    @if ($ringkas['terpopuler']->isNotEmpty() || $ringkas['disukai']->isNotEmpty())
        <div class="grid gap-3 lg:grid-cols-2">
            @if ($ringkas['terpopuler']->isNotEmpty())
                <div class="plate p-4">
                    <p class="part-no">{{ __('site.admin.keranjang_terbuka.paling_ditinggalkan') }}</p>
                    <p class="mt-1 text-xs text-dim">
                        {{ __('site.admin.keranjang_terbuka.pembeli_berhenti') }}
                    </p>

                    <div class="mt-3 space-y-2">
                        @foreach ($ringkas['terpopuler'] as $p)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate">{{ $p->nama }}</span>
                                <span class="tech flex-none text-muted">
                                    {{ $p->jumlah }} pcs · {{ $p->keranjang }} keranjang
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Disukai: sinyal minat termurah, satu ketukan tanpa stok ditahan.
                 Dipakai sebagai parameter iklan — produk yang dibutuhkan pembeli,
                 bukan produk yang menurut toko bagus. --}}
            @if ($ringkas['disukai']->isNotEmpty())
                <div class="plate p-4">
                    <p class="part-no">{{ __('site.admin.keranjang_terbuka.paling_disukai') }}</p>
                    <p class="mt-1 text-xs text-dim">
                        {{ __('site.admin.keranjang_terbuka.keterangan') }}
                    </p>

                    <div class="mt-3 space-y-2">
                        @foreach ($ringkas['disukai'] as $p)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate">{{ $p->nama }}</span>
                                <span class="tech flex-none text-muted">{{ $p->jumlah }} suka</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Saringan ────────────────────────────────────────────────── --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="flex gap-1" role="group" aria-label="{{ __('site.admin.keranjang_terbuka.umur_keranjang') }}">
            @foreach (['semua' => 'Semua', 'harian' => 'Baru', 'tua' => 'Mengendap'] as $nilai => $label)
                <button type="button" wire:click="aturUmur('{{ $nilai }}')"
                        class="btn {{ $umur === $nilai ? 'btn-primary' : 'btn-ghost' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <label class="ml-auto flex min-w-[12rem] flex-1 items-center gap-2 lg:max-w-xs">
            <x-ikon name="search" :size="17" class="flex-none text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari"
                   class="field" placeholder="{{ __('site.admin.keranjang_terbuka.cari') }}" />
        </label>
    </div>

    {{-- ── Daftar keranjang ────────────────────────────────────────── --}}
    <div class="space-y-3">
        @forelse ($keranjang as $k)
            <article class="plate p-4" wire:key="keranjang-{{ $k->id }}">

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $this->pemilik($k) }}</p>

                            @if ($k->user === null)
                                <span class="badge badge-neutral">{{ __('site.admin.keranjang_terbuka.tanpa_akun') }}</span>
                            @endif

                            <span class="badge {{ $k->updated_at->gt(now()->subDay()) ? 'badge-ok' : 'badge-warn' }}">
                                {{ $this->sejak($k->updated_at) }}
                            </span>

                            @if ($this->perluPerhatian($k->items))
                                <span class="badge badge-danger">{{ __('site.admin.keranjang_terbuka.stok_tidak_cukup') }}</span>
                            @endif

                            @if ($k->coupon)
                                <span class="badge badge-accent">{{ $k->coupon->code }}</span>
                            @endif
                        </div>

                        <p class="tech mt-1.5 text-xs text-dim">
                            {{ $k->ulid }}
                            @if ($k->user)
                                · {{ $k->user->email }}
                            @endif
                            @if ($k->session_id)
                                · sesi {{ mb_substr($k->session_id, 0, 10) }}
                            @endif
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="part-no">{{ $k->jumlahItem() }} barang</p>
                        <p class="tech text-lg font-medium">{{ rupiah($k->subtotal()) }}</p>
                    </div>
                </div>

                {{-- Isi keranjang: produk dan jumlahnya. --}}
                <ul class="mt-3 divide-y divide-line-soft border-t border-line-soft">
                    @foreach ($k->items as $item)
                        @php
                            $varian = $item->variant;
                            $tersedia = $varian?->stokTersedia() ?? 0;
                        @endphp
                        <li class="flex items-center gap-3 py-2.5">
                            <div class="h-10 w-10 flex-none overflow-hidden rounded-[var(--r-kecil)] bg-sunken">
                                @php $media = $item->product?->gambarUtama(); @endphp
                                @if ($media)
                                    <img src="{{ $media->url('thumb') }}" alt=""
                                         class="h-full w-full object-cover" loading="lazy" />
                                @else
                                    <span class="flex h-full w-full items-center justify-center text-dim">
                                        <x-ikon name="image" :size="16" />
                                    </span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm">{{ $item->product?->judul() ?? '(produk dihapus)' }}</p>
                                <p class="tech text-xs text-dim">
                                    {{ $varian?->nama() ?? '(varian dihapus)' }}
                                    · stok tersedia {{ $tersedia }}
                                </p>
                            </div>

                            <div class="flex-none text-right">
                                <p class="tech text-sm">{{ $item->quantity }}×</p>
                                <p class="tech text-xs text-dim">
                                    {{ $varian ? rupiah($varian->hargaAkhir()) : '—' }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <div class="plate p-10 text-center">
                <x-ikon name="cart" :size="30" class="mx-auto text-dim" />
                <p class="mt-3 font-semibold">{{ __('site.admin.keranjang_terbuka.kosong') }}</p>
                <p class="mt-1 text-sm text-muted">
                    @if (filled($cari) || $umur !== 'semua')
                        Tidak ada yang cocok dengan saringan ini. Coba lebarkan saringan.
                    @else
                        {{ __('site.admin.keranjang_terbuka.kosong_hint') }}
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if ($keranjang->hasPages())
        <div>{{ $keranjang->links() }}</div>
    @endif
</div>
