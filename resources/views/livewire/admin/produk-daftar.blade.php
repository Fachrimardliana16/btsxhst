{{-- Daftar produk (F3-02).

     Aksi massal memakai bilah yang muncul saat ada yang terpilih. Bilah itu
     menempel di bawah layar, sehingga tindakannya selalu terjangkau tanpa
     menggulir kembali ke atas setelah memilih produk di baris terakhir. --}}

<div class="space-y-5" x-data="{ bilah: false }"
     x-init="$watch('$wire.terpilih', v => bilah = v.length > 0)">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.produk_daftar.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.produk_daftar.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua'], 0, ',', '.') }} produk ·
                {{ number_format($jumlah['terbit'], 0, ',', '.') }} terbit ·
                {{ number_format($jumlah['draf'], 0, ',', '.') }} draf
                @if ($jumlah['{{ __('site.admin.produk_daftar.habis') }}'] > 0)
                    · <span class="text-danger">{{ number_format($jumlah['{{ __('site.admin.produk_daftar.habis') }}'], 0, ',', '.') }} stok {{ __('site.admin.produk_daftar.habis') }}</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline">
                <x-ikon name="tag" :size="16" />
                {{ __('site.admin.produk_daftar.kategori') }}
            </a>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                <x-ikon name="plus" :size="16" />
                {{ __('site.admin.produk_daftar.judul') }} baru
            </a>
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @if ($catatan !== [])
        <div class="plate border-l-2 border-warn p-3 text-sm" role="status">
            <p class="font-medium">{{ __('site.admin.produk_daftar.sebagian_dilewati') }}</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-muted">
                @foreach ((array) $catatan as $satu)
                    <li>{{ $satu }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Saringan ────────────────────────────────────────────────── --}}

    {{-- x-data di wadah TERLUAR, bukan di tombol.

         Versi pertama memasang x-data pada tombol itu sendiri dan memanggil
         $refs.saringan{{ __('site.admin.produk_daftar.judul') }} dari sana. Itu tidak pernah jalan: $refs hanya
         memuat ref di dalam subtree elemen yang memegang x-data, dan div
         saringan adalah SAUDARA tombol, bukan anaknya. Hasilnya
         $refs.saringan{{ __('site.admin.produk_daftar.judul') }} === undefined, dan kliknya diam tanpa galat —
         saringan di ponsel tidak pernah terbuka.

         Dengan x-data di .plate, $refs milik tombol melihat div di sebelahnya.
         aria-expanded ikut diperbarui supaya pembaca layar tahu keadaannya.

         `lebar` juga tinggal di sini, bukan di div saringan: x-data pada div
         itu akan membuat scope BARU, dan `lebar` milik induk tidak lagi
         terbaca dari sana. --}}
    <div class="plate space-y-3 p-3" x-data="{
            saringan: false,
            lebar: window.matchMedia('(min-width: 640px)').matches,
         }">
        <div class="flex items-center gap-2">
            <div class="relative min-w-0 flex-1">
                <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
                <input type="search" wire:model.live.debounce.400ms="cari"
                       class="field pl-9" placeholder="{{ __('site.admin.produk_daftar.cari_placeholder') }}" aria-label="{{ __('site.admin.produk_daftar.cari') }}">
            </div>

            <button type="button" class="btn btn-outline flex-none sm:hidden"
                    @click="saringan = !saringan"
                    :aria-expanded="saringan"
                    aria-label="{{ __('site.admin.produk_daftar.buka_saringan') }}">
                <x-ikon name="sliders" :size="16" />
            </button>
        </div>

        {{-- Baris saringan: DUA select, bukan empat tombol + empat select.

             Sebelumnya ada sembilan kontrol di sini — tombol {{ __('site.admin.produk_daftar.terbit') }}, {{ __('site.admin.produk_daftar.draf') }},
             {{ __('site.admin.produk_daftar.stok_{{ __('site.admin.produk_daftar.habis') }}') }}, Menipis, ditambah select kategori, stok, publikasi,
             urutan, dan {{ __('site.admin.produk_daftar.bersihkan') }}. Empat tombol itu SEPENUHNYA redundan:
             "{{ __('site.admin.produk_daftar.terbit') }}" sama dengan publikasi=terbit, "Menipis" sama dengan
             stok=menipis. Menghapusnya tidak menghilangkan satu pun
             kemampuan menyaring.

             Select publikasi dan stok juga digabung jadi satu pilihan
             Kondisi (6 nilai). Hasil akhirnya: Cari, {{ __('site.admin.produk_daftar.kategori') }}, Kondisi,
             Urutan, {{ __('site.admin.produk_daftar.bersihkan') }} — lima kontrol, dan yang paling sering dipakai
             (cari) tetap terbuka.

             Nilai `terbit` dan `stok` TETAP dipakai sebagai properti: tautan
             ?stok={{ __('site.admin.produk_daftar.habis') }}&terbit=draf dari lonceng notifikasi dan dari halaman
             {{ __('site.admin.produk_daftar.kategori') }} masih harus bekerja. Select Kondisi hanya menulis ke
             keduanya.

             Di ponsel barisnya dilipat di balik tombol saringan; x-show
             memasang style="display:none" sehingga penglihatannya dibalik
             lewat `saringan || lebar`, bukan lewat class responsif. --}}
        <div x-ref="saringan{{ __('site.admin.produk_daftar.judul') }}" x-show="saringan || lebar" x-cloak
             class="flex flex-wrap items-center gap-2">
            <select wire:model.live="kategori" class="field w-auto" aria-label="{{ __('site.admin.produk_daftar.saring_kategori') }}">
                <option value="semua">{{ __('site.admin.produk_daftar.semua_kategori') }}</option>
                @foreach ($daftar{{ __('site.admin.produk_daftar.kategori') }} as $k)
                    <option value="{{ $k->id }}">{{ $k->name_id }}</option>
                @endforeach
            </select>

            {{-- Kondisi: gabungan publikasi + stok.

                 Nilainya dipetakan ke dua properti di aturKondisi(); bentuk
                 "terbit:{{ __('site.admin.produk_daftar.habis') }}" dipakai supaya satu select bisa mewakili dua
                 dimensi tanpa kolom tersembunyi yang dapat menyimpang. --}}
            <select wire:model.live="kondisi" class="field w-auto" aria-label="{{ __('site.admin.produk_daftar.saring_kondisi') }}">
                <option value="semua">{{ __('site.admin.produk_daftar.semua_kondisi') }}</option>
                <option value="terbit">{{ __('site.admin.produk_daftar.terbit') }}</option>
                <option value="draf">{{ __('site.admin.produk_daftar.draf') }}</option>
                <option value="{{ __('site.admin.produk_daftar.habis') }}">{{ __('site.admin.produk_daftar.stok_{{ __('site.admin.produk_daftar.habis') }}') }}</option>
                <option value="menipis">{{ __('site.admin.produk_daftar.stok_menipis') }}</option>
                <option value="ada">{{ __('site.admin.produk_daftar.ada_stok') }}</option>
            </select>

            <select wire:model.live="urut" class="field w-auto" aria-label="{{ __('site.admin.produk_daftar.urutkan') }}">
                <option value="terbaru">{{ __('site.admin.produk_daftar.terbaru') }}</option>
                <option value="terlama">{{ __('site.admin.produk_daftar.terlama') }}</option>
                <option value="nama">{{ __('site.admin.produk_daftar.nama_az') }}</option>
                <option value="termahal">{{ __('site.admin.produk_daftar.termahal') }}</option>
                <option value="termurah">{{ __('site.admin.produk_daftar.termurah') }}</option>
                <option value="terlaris">{{ __('site.admin.produk_daftar.terlaris') }}</option>
                <option value="stok">{{ __('site.admin.produk_daftar.stok_tersedikit') }}</option>
            </select>

            @if ($cari !== '' || $kategori !== 'semua' || $stok !== 'semua' || $terbit !== 'semua' || $urut !== 'terbaru')
                <a href="{{ route('admin.products.index') }}" class="btn btn-ghost btn-sm">
                    <x-ikon name="close" :size="15" />
                    {{ __('site.admin.produk_daftar.bersihkan') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────── --}}
    <div class="plate overflow-hidden">
        @if ($daftar->total() === 0)
            <div class="p-8 text-center">
                <x-ikon name="box" :size="28" class="mx-auto text-dim" />
                <p class="mt-3 text-sm text-muted">
                    @if ($cari !== '' || $kategori !== 'semua' || $stok !== 'semua' || $terbit !== 'semua')
                        Tidak ada produk yang cocok dengan saringan ini.
                    @else
                        Belum ada produk. Mulai dengan menambahkan yang pertama.
                    @endif
                </p>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary mt-4">
                    <x-ikon name="plus" :size="16" />
                    {{ __('site.admin.produk_daftar.judul') }} baru
                </a>
            </div>
        @else
            <div class="flex items-center gap-3 border-b border-line-soft p-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox"
                           @checked(count($terpilih) > 0 && count($terpilih) === $daftar->count())
                           wire:click="{{ count($terpilih) > 0 ? 'kosongkanPilihan' : 'pilihSemua' }}"
                           class="h-4 w-4">
                    {{ __('site.admin.produk_daftar.pilih_semua') }}
                </label>

                @if (count($terpilih) > 0)
                    <span class="tech text-xs text-accent">{{ count($terpilih) }} terpilih</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 p-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($daftar as $produk)
                    @php
                        $stokBebas = (int) ($produk->stok_fisik ?? 0) - (int) ($produk->stok_ditahan ?? 0);
                        $menipis = $stokBebas > 0 && $stokBebas <= (int) config('iksan.low_stock_threshold', 3);
                        $gmbr = $produk->primaryMedia;
                    @endphp

                    {{-- Sudut r-card (14px), sama dengan .plate.

                         Versi lama memakai var(--r-sedang) yang TIDAK ADA di
                         tokens.css — skalanya adalah mikro/kecil/btn/card/panel.
                         Variabel yang tidak terdefinisi membuat properti
                         border-radius tidak berlaku sama sekali, jadi kartu
                         produk tampil bersudut siku sementara seluruh panel
                         lain melengkung. Tidak ada galat; hanya terlihat
                         berbeda tanpa sebab yang tertulis. --}}
                    <article class="group relative flex flex-col overflow-hidden rounded-[var(--r-card)] border border-line-soft bg-surface transition-colors hover:border-line"
                             wire:key="produk-{{ $produk->id }}">

                        {{-- Gambar: seluruh permukaannya membuka penyuntingan. --}}
                        <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                           class="relative block aspect-square bg-sunken" aria-label="{{ __('site.admin.produk_daftar.sunting') }} {{ $produk->title_id }}">
                            @if ($gmbr)
                                <img src="{{ $gmbr->url('thumb') }}" alt=""
                                     width="220" height="220" loading="lazy" decoding="async"
                                     class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-dim">
                                    <x-ikon name="image" :size="22" />
                                </div>
                            @endif

                            @if ($produk->is_featured)
                                <span class="absolute left-2 top-2 rounded-full bg-brass/90 px-2 py-0.5 text-[length:var(--t-xs)] font-medium text-white">
                                    unggulan
                                </span>
                            @endif

                            <span class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-surface/90 shadow-sm">
                                <input type="checkbox" value="{{ $produk->id }}" wire:model.live="terpilih"
                                       class="h-3.5 w-3.5" aria-label="Pilih {{ $produk->title_id }}">
                            </span>
                        </a>

                        <div class="flex flex-1 flex-col gap-2 p-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                                   class="line-clamp-2 block text-sm font-medium leading-snug hover:text-accent">
                                    {{ $produk->title_id }}
                                </a>
                                <p class="tech mt-1 truncate text-xs text-dim">
                                    {{ $produk->sku }} · {{ $produk->variants_count }} varian
                                </p>
                            </div>

                            <p class="truncate text-xs text-muted">{{ $produk->category?->name_id ?? '—' }}</p>

                            <div class="mt-auto flex items-end justify-between gap-2">
                                <div>
                                    <p class="tech text-sm font-medium">{{ rupiah($produk->base_price) }}</p>
                                    <p class="tech text-xs">
                                        @if ($stokBebas <= 0)
                                            <span class="text-danger">{{ __('site.admin.produk_daftar.habis') }}</span>
                                        @elseif ($menipis)
                                            <span class="text-warn">{{ $stokBebas }} sisa</span>
                                        @else
                                            <span class="text-muted">{{ $stokBebas }} stok</span>
                                        @endif

                                        @if ((int) ($produk->stok_ditahan ?? 0) > 0)
                                            <span class="text-dim" title="{{ __('site.admin.produk_daftar.ditahan') }}">· {{ $produk->stok_ditahan }} ditahan</span>
                                        @endif
                                    </p>
                                </div>

                                <button type="button" wire:click="ubah{{ __('site.admin.produk_daftar.terbit') }}Satu({{ $produk->id }})"
                                        class="inline-flex flex-none items-center gap-1.5 rounded-full px-2 py-0.5 text-xs
                                               {{ $produk->is_published ? 'bg-ok/12 text-ok' : 'bg-sunken text-muted' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $produk->is_published ? 'bg-ok' : 'bg-line' }}"></span>
                                    {{ $produk->is_published ? '{{ __('site.admin.produk_daftar.terbit') }}' : '{{ __('site.admin.produk_daftar.draf') }}' }}
                                </button>
                            </div>

                            {{-- Tindakan: muncul saat kartu disentuh penunjuk atau difokuskan.
                                 Di ponsel tidak ada hover, jadi selalu tampil di layar kecil. --}}
                            <div class="flex items-center justify-end gap-1 border-t border-line-soft pt-2
                                        opacity-100 transition-opacity lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100">
                                <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                                   class="btn-icon btn-icon-sm" title="{{ __('site.admin.produk_daftar.sunting') }}">
                                    <x-ikon name="sliders" :size="15" />
                                    <span class="visually-hidden">{{ __('site.admin.produk_daftar.sunting_produk') }}</span>
                                </a>
                                <button type="button" wire:click="duplikat({{ $produk->id }})"
                                        class="btn-icon btn-icon-sm" title="{{ __('site.admin.produk_daftar.duplikat') }}">
                                    <x-ikon name="copy" :size="15" />
                                    <span class="visually-hidden">{{ __('site.admin.produk_daftar.duplikat') }}</span>
                                </button>
                                <button type="button" wire:click="hapus({{ $produk->id }})"
                                        wire:confirm="{{ __('site.admin.produk_daftar.hapus') }} produk &quot;{{ $produk->title_id }}&quot; beserta varian dan gambarnya?"
                                        class="btn-icon btn-icon-sm hover:text-danger" title="{{ __('site.admin.produk_daftar.hapus') }}">
                                    <x-ikon name="trash" :size="15" />
                                    <span class="visually-hidden">{{ __('site.admin.produk_daftar.hapus') }}</span>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($daftar->hasPages())
                <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
            @endif
        @endif
    </div>

    {{-- ── Bilah aksi massal ───────────────────────────────────────── --}}
    @if (count($terpilih) > 0)
        <div class="sticky bottom-3 z-20 mx-auto w-fit max-w-full">
            <div class="plate plate-up flex flex-wrap items-center gap-2 p-2 shadow-float">
                <span class="part-no px-2">{{ count($terpilih) }} dipilih</span>

                <button type="button" wire:click="ubah{{ __('site.admin.produk_daftar.terbit') }}Massal(true)" class="btn btn-outline btn-sm">{{ __('site.admin.produk_daftar.terbitkan') }}</button>
                <button type="button" wire:click="ubah{{ __('site.admin.produk_daftar.terbit') }}Massal(false)" class="btn btn-outline btn-sm">{{ __('site.admin.produk_daftar.tarik') }}</button>
                <button type="button" wire:click="ubah{{ __('site.admin.produk_daftar.unggulan') }}Massal(true)" class="btn btn-outline btn-sm">{{ __('site.admin.produk_daftar.unggulan') }}</button>

                <select wire:change="pindah{{ __('site.admin.produk_daftar.kategori') }}Massal($event.target.value)" class="field w-auto py-1.5 text-sm"
                        aria-label="{{ __('site.admin.produk_daftar.pindahkan_kategori') }}">
                    <option value="">{{ __('site.admin.produk_daftar.pindah_ke') }}</option>
                    @foreach ($daftar{{ __('site.admin.produk_daftar.kategori') }} as $k)
                        <option value="{{ $k->id }}">{{ $k->name_id }}</option>
                    @endforeach
                </select>

                <button type="button"
                        wire:click="hapusMassal"
                        wire:confirm="{{ __('site.admin.produk_daftar.hapus') }} {{ count($terpilih) }} produk terpilih beserta gambarnya?"
                        class="btn btn-danger btn-sm">
                    <x-ikon name="trash" :size="14" />
                    {{ __('site.admin.produk_daftar.hapus') }}
                </button>

                <button type="button" wire:click="kosongkanPilihan" class="btn btn-ghost btn-sm">{{ __('site.admin.produk_daftar.batal') }}</button>
            </div>
        </div>
    @endif
</div>
