{{-- Form produk tiga tab (F3-03, F3-04, F3-05).

     Tabnya bukan halaman terpisah: perpindahan tab hanya menyembunyikan panel
     dengan CSS, sehingga isian yang belum disimpan tidak hilang saat pengguna
     menengok tab lain. Penyimpanan tetap per tab di server. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="part-no">Catalog · product {{ $produk ? '· '.$produk->sku : '· new' }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">
                {{ $produk ? $produk->judul() : 'New Product' }}
            </h1>
            @if ($produk)
                <p class="mt-1 text-sm text-muted">
                    {{ $produk->is_published ? 'Published' : 'Draft' }} ·
                    {{ $produk->variants->count() }} variants ·
                    {{ $media->count() }} images
                </p>
            @else
                <p class="mt-1 text-sm text-muted">
                    {{ __('site.admin.produk_form.isi_info_dulu') }}
                </p>
            @endif
        </div>

        {{-- {{ __('site.admin.produk_form.tindakan') }} kepala.

             Tiga tombol dipangkas jadi satu-satu yang benar-benar tindakan:

               "Kembali"     -> ikon panah, judul "{{ __('site.admin.produk_form.kembali_daftar') }}".
                                Ia navigasi, bukan tindakan atas produk, jadi
                                tidak layak memakan lebar seukuran tombol
                                bertuliskan di antara dua tombol tindakan.
               "Lihat"       -> ikon mata, hanya muncul bila produk terbit.
                                Sudah membuka tab baru; labelnya ada di title.
               "Tarik/Terbit" -> tetap tombol bertuliskan, karena inilah satu-
                                satunya tindakan yang mengubah keadaan produk
                                dan paling sering dicari.

             Di ponsel ketiganya jadi ikon 44px berjejer sebaris, tidak lagi
             tiga baris bertumpuk. --}}
        <div class="flex flex-none items-center gap-1">
            <a href="{{ route('admin.products.index') }}" class="btn-icon" title="{{ __('site.admin.produk_form.kembali_daftar') }}"
               aria-label="{{ __('site.admin.produk_form.kembali_daftar') }}">
                <x-ikon name="chevron-left" :size="18" />
            </a>

            @if ($produk)
                @if ($produk->is_published)
                    <a href="{{ route('front.products.show', $produk->slug) }}" target="_blank" rel="noopener"
                       class="btn-icon" title="{{ __('site.admin.produk_form.lihat_halaman') }}" aria-label="{{ __('site.admin.produk_form.lihat_halaman') }}">
                        <x-ikon name="eye" :size="18" />
                    </a>
                @endif

                <button type="button" wire:click="ubahTerbit"
                        wire:confirm="{{ $produk->is_published ? 'Remove this product from the catalog?' : 'Publish this product?' }}"
                        class="btn {{ $produk->is_published ? 'btn-outline' : 'btn-primary' }} btn-sm"
                        title="{{ $produk->is_published ? 'Remove from catalog' : 'Publish' }}">
                    <x-ikon name="{{ $produk->is_published ? 'close' : 'check' }}" :size="15" />
                    <span class="hidden sm:inline">
                        {{ $produk->is_published ? 'Remove from catalog' : 'Publish' }}
                    </span>
                    <span class="sr-only sm:hidden">
                        {{ $produk->is_published ? 'Remove from catalog' : 'Publish' }}
                    </span>
                </button>
            @endif
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Tab ─────────────────────────────────────────────────────── --}}
    <div class="-mx-3 overflow-x-auto px-3 lg:mx-0 lg:px-0">
        <div class="flex min-w-max gap-1 border-b border-line-soft" role="tablist">
            @foreach ([
                'info' => ['label' => 'Basic Information', 'ikon' => 'card', 'siap' => $produk !== null],
                'varian' => ['label' => 'Variants', 'ikon' => 'sliders', 'siap' => $produk !== null],
                'media' => ['label' => 'Images', 'ikon' => 'image', 'siap' => $produk !== null],
            ] as $kunci => $t)
                <button type="button" role="tab" wire:click="aturTab('{{ $kunci }}')"
                        @disabled(! $t['siap'])
                        aria-selected="{{ $tab === $kunci ? 'true' : 'false' }}"
                        class="flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-[length:var(--t-sm)] whitespace-nowrap
                               {{ $tab === $kunci ? 'border-accent font-medium text-accent' : 'border-transparent text-muted hover:text-ink' }}
                               disabled:cursor-not-allowed disabled:opacity-40">
                    <x-ikon :name="$t['ikon']" :size="15" />
                    {{ $t['label'] }}
                    @if ($kunci === 'varian' && count($varian) > 0)
                        <span class="tech text-xs text-dim">{{ count($varian) }}</span>
                    @endif
                    @if ($kunci === 'media' && $media->count() > 0)
                        <span class="tech text-xs text-dim">{{ $media->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══ TAB 1: INFORMASI DASAR ═══════════════════════════════════ --}}
    @if ($tab === 'info')
        <form wire:submit="simpanInfo" class="space-y-5">
            <div class="grid gap-5 lg:grid-cols-[1fr_320px]">

                <div class="space-y-5">
                    {{-- Nama dan slug --}}
                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.nama_produk') }}</h2>

                        <div>
                            <label for="title_id" class="part-no">{{ __('site.admin.produk_form.nama_produk') }} <span class="text-danger">*</span></label>
                            <input id="title_id" type="text" wire:model.live.debounce.500ms="title_id"
                                   class="field mt-1" placeholder="Jupiter MX Racing Exhaust"
                                   aria-invalid="{{ $errors->has('title_id') ? 'true' : 'false' }}">
                            @error('title_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="slug" class="part-no">{{ __('site.admin.produk_form.slug') }} <span class="text-danger">*</span></label>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="tech hidden text-xs text-dim sm:inline">/produk/</span>
                                <input id="slug" type="text" wire:model.live="slug"
                                       class="field tech" aria-invalid="{{ $errors->has('slug') ? 'true' : 'false' }}">
                            </div>
                            <p class="mt-1 text-xs text-dim">{{ __('site.admin.produk_form.slug_hint') }}</p>
                            @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div class="plate space-y-4 p-4">
                        <div>
                            <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.ringkasan') }}</h2>
                            <p class="mt-0.5 text-xs text-dim">{{ __('site.admin.produk_form.ringkasan_hint') }}</p>
                        </div>

                        <div>
                            <label for="excerpt_id" class="part-no">{{ __('site.admin.produk_form.ringkasan') }}</label>
                            <textarea id="excerpt_id" wire:model="excerpt_id" class="field mt-1" rows="2"
                                      maxlength="300"></textarea>
                            @error('excerpt_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.deskripsi_lengkap') }}</h2>

                        <div>
                            <label for="description_id" class="part-no">{{ __('site.admin.produk_form.deskripsi_lengkap') }}</label>
                            <textarea id="description_id" wire:model="description_id" class="field mt-1" rows="8"></textarea>
                            @error('description_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Kolom samping --}}
                <div class="space-y-5 lg:sticky lg:top-20 lg:h-fit">
                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.harga_dan_berat') }}</h2>

                        <div>
                            <label for="base_price_usd" class="part-no">Base price (USD) <span class="text-danger">*</span></label>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="tech text-sm text-dim">$</span>
                                <input id="base_price_usd" type="text" inputmode="decimal" wire:model="base_price_usd"
                                       class="field tech" aria-invalid="{{ $errors->has('base_price_usd') ? 'true' : 'false' }}">
                            </div>
                            <p class="mt-1 text-xs text-dim">The price buyers see, in US dollars.</p>
                            @error('base_price_usd') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="weight_gram" class="part-no">{{ __('site.admin.produk_form.berat_gram') }} <span class="text-danger">*</span></label>
                            <input id="weight_gram" type="text" inputmode="numeric" wire:model="weight_gram"
                                   class="field tech mt-1" aria-invalid="{{ $errors->has('weight_gram') ? 'true' : 'false' }}">
                            <p class="mt-1 text-xs text-dim">{{ __('site.admin.produk_form.berat_hint') }}</p>
                            @error('weight_gram') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.pengelompokan') }}</h2>

                        <div>
                            <label for="category_id" class="part-no">{{ __('site.admin.produk_form.kategori') }} <span class="text-danger">*</span></label>
                            <select id="category_id" wire:model="category_id" class="field mt-1"
                                    aria-invalid="{{ $errors->has('category_id') ? 'true' : 'false' }}">
                                <option value="">{{ __('site.admin.produk_form.pilih_kategori') }}</option>
                                @foreach ($kategori as $k)
                                    <option value="{{ $k->id }}">{{ $k->nama() }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="sku" class="part-no">SKU <span class="text-danger">*</span></label>
                            <input id="sku" type="text" wire:model="sku" class="field tech mt-1"
                                   aria-invalid="{{ $errors->has('sku') ? 'true' : 'false' }}">
                            @error('sku') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Spesifikasi teknis (checklist F6-04). {{ __('site.admin.produk_form.urutan') }}nya adalah
                         urutan tampil di halaman produk, jadi ada tombol naik dan
                         turun. Baris tanpa nama dibuang saat disimpan. --}}
                    <div class="plate space-y-4 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-display text-lg font-semibold">{{ __('site.product.specification') }}</h2>
                            <button type="button" wire:click="tambahSpesifikasi" class="btn btn-outline !min-h-[36px] text-xs">
                                <x-ikon name="plus" :size="14" />
                                {{ __('site.product.spec_add') }}
                            </button>
                        </div>
                        <p class="text-xs text-dim">{{ __('site.product.spec_hint') }}</p>

                        @if ($spesifikasi === [])
                            <p class="text-sm text-muted">{{ __('site.product.spec_empty') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($spesifikasi as $i => $baris)
                                    {{-- Kotak sudut tipis, bukan pil.

                                         Versi sebelumnya memakai
                                         rounded-[var(--r-btn)] — radius penuh
                                         (999px) — pada wadah yang setinggi
                                         beberapa baris. Radius sebesar itu
                                         hanya masuk akal pada tombol 44px dan
                                         lencana; pada kotak form ia membuat
                                         ujungnya melengkung seperti pastil dan
                                         tepi dalamnya tidak lagi sejajar tepi
                                         luar panel.

                                         Sudut sekarang r-kecil (8px), sama
                                         dengan .field dan .btn-icon — satu
                                         skala untuk seluruh komponen kecil. --}}
                                    <div class="rounded-[var(--r-kecil)] border border-line-soft p-3" wire:key="spesifikasi-{{ $i }}">
                                        <div class="flex flex-wrap items-end gap-2">
                                            <div class="min-w-0 flex-1">
                                                <label class="part-no" for="spec-label-{{ $i }}">{{ __('site.product.spec_label') }}</label>
                                                <input id="spec-label-{{ $i }}" type="text" wire:model="spesifikasi.{{ $i }}.label_id"
                                                       class="field mt-1 py-2" placeholder="{{ __('site.admin.produk_form.material') }}">
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <label class="part-no" for="spec-nilai-{{ $i }}">{{ __('site.product.spec_value') }}</label>
                                                <input id="spec-nilai-{{ $i }}" type="text" wire:model="spesifikasi.{{ $i }}.nilai_id"
                                                       class="field mt-1 py-2" placeholder="Stainless 304">
                                            </div>

                                            <div class="flex flex-none items-center gap-1">
                                                <button type="button" wire:click="geserSpesifikasi({{ $i }}, 'naik')"
                                                        class="btn-icon btn-icon-sm" title="{{ __('site.admin.produk_form.naikkan') }}" {{ $i === 0 ? 'disabled' : '' }}>
                                                    <x-ikon name="arrow-up" :size="13" />
                                                    <span class="visually-hidden">{{ __('site.admin.produk_form.naikkan') }}</span>
                                                </button>
                                                <button type="button" wire:click="geserSpesifikasi({{ $i }}, 'turun')"
                                                        class="btn-icon btn-icon-sm" title="{{ __('site.admin.produk_form.turunkan') }}" {{ $loop->last ? 'disabled' : '' }}>
                                                    <x-ikon name="arrow-right" :size="13" class="rotate-90" />
                                                    <span class="visually-hidden">{{ __('site.admin.produk_form.turunkan') }}</span>
                                                </button>
                                                <button type="button" wire:click="hapusSpesifikasi({{ $i }})"
                                                        class="btn-icon btn-icon-sm hover:text-danger" title="{{ __('site.product.spec_remove') }}">
                                                    <x-ikon name="trash" :size="13" />
                                                    <span class="visually-hidden">{{ __('site.product.spec_remove') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('spesifikasi') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                </div>

                <div class="space-y-5">
                    <div class="plate space-y-3 p-4">
                        <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.tampilan') }}</h2>

                        <label class="flex items-center gap-2">
                            <span class="switch">
                                <input type="checkbox" wire:model="is_published">
                                <span></span>
                            </span>
                            <span class="text-sm">{{ __('site.admin.produk_form.terbitkan') }}</span>
                        </label>
                        <p class="text-xs text-dim">
                            A product without variants cannot be published — buyers would reach a page they cannot purchase from.
                        </p>

                        <label class="flex items-center gap-2">
                            <span class="switch">
                                <input type="checkbox" wire:model="is_featured">
                                <span></span>
                            </span>
                            <span class="text-sm">{{ __('site.admin.produk_form.tandai_unggulan') }}</span>
                        </label>
                        <p class="text-xs text-dim">{{ __('site.admin.produk_form.unggulan_hint') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanInfo">{{ __('site.admin.produk_form.simpan_varian') }}</span>
                    <span wire:loading wire:target="simpanInfo">{{ __('site.admin.produk_form.menyimpan') }}</span>
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline">{{ __('site.admin.produk_form.batal') }}</a>
            </div>
        </form>
    @endif

    {{-- ══ TAB 2: VARIAN ════════════════════════════════════════════ --}}
    @if ($tab === 'varian')
        <div class="space-y-4">
            <div class="plate flex flex-wrap items-center justify-between gap-3 p-3">
                <div>
                    <p class="text-sm font-medium">{{ __('site.admin.produk_form.varian_judul') }}</p>
                    <p class="text-xs text-dim">
                        Final price = base price {{ dolar($base_price_usd !== '' ? (float) $base_price_usd : \App\Support\HargaDolar::dariRupiah($base_price)) }} plus the variant adjustment.
                        The reserved column is filled automatically by open orders.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="tambahVarian" class="btn btn-outline btn-sm">
                        <x-ikon name="plus" :size="14" />
                        {{ __('site.admin.produk_form.tambah_varian') }}
                    </button>
                    <button type="button" wire:click="simpanVarian" class="btn btn-primary btn-sm"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simpanVarian">{{ __('site.admin.produk_form.simpan_varian_tombol') }}</span>
                        <span wire:loading wire:target="simpanVarian">{{ __('site.admin.produk_form.menyimpan') }}</span>
                    </button>
                </div>
            </div>

            @error('varian')
                <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
            @enderror

            <div class="plate overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.urutan') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.nama_varian') }} <span class="text-danger">*</span></th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.selisih_harga') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.stok') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.ditahan') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.berat') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.produk_form.aktif') }}</th>
                                <th class="p-3"><span class="visually-hidden">{{ __('site.admin.produk_form.tindakan') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($varian as $i => $v)
                                @php
                                    $dasarUsd = $base_price_usd !== ''
                                        ? (float) $base_price_usd
                                        : (float) (\App\Support\HargaDolar::dariRupiah($base_price) ?? 0);
                                    $selisihUsd = (string) ($v['selisih_usd'] ?? '') !== ''
                                        ? (float) $v['selisih_usd']
                                        : (float) (\App\Support\HargaDolar::dariRupiah($v['selisih'] ?? 0) ?? 0);
                                    $hargaAkhir = $dasarUsd + $selisihUsd;
                                @endphp

                                <tr class="border-b border-line-soft last:border-0" wire:key="varian-{{ $i }}-{{ $v['id'] ?? 'baru' }}">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="tech text-xs text-dim">{{ $i + 1 }}</span>
                                            <button type="button" wire:click="geserVarian({{ $i }}, 'naik')"
                                                    class="btn-icon h-7 w-7" title="{{ __('site.admin.produk_form.naikkan') }}"
                                                    {{ $loop->first ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" class="rotate-180" />
                                                <span class="visually-hidden">{{ __('site.admin.produk_form.naikkan') }}</span>
                                            </button>
                                            <button type="button" wire:click="geserVarian({{ $i }}, 'turun')"
                                                    class="btn-icon h-7 w-7" title="{{ __('site.admin.produk_form.turunkan') }}"
                                                    {{ $loop->last ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" />
                                                <span class="visually-hidden">{{ __('site.admin.produk_form.turunkan') }}</span>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <input type="text" wire:model="varian.{{ $i }}.nama_id"
                                               class="field py-2" placeholder="{{ __('site.admin.produk_form.contoh_varian') }}"
                                               aria-label="{{ __('site.admin.produk_form.nama_varian') }} row {{ $i + 1 }}"
                                               aria-invalid="{{ $errors->has('varian.'.$i.'.nama_id') ? 'true' : 'false' }}">
                                        @error("varian.$i.nama_id") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>

                                    <td class="p-3">
                                        <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="varian.{{ $i }}.selisih_usd"
                                               class="field tech w-28 py-2"
                                               aria-label="{{ __('site.admin.produk_form.selisih_harga') }} row {{ $i + 1 }}"
                                               aria-invalid="{{ $errors->has('varian.'.$i.'.selisih_usd') ? 'true' : 'false' }}">
                                        <p class="tech mt-1 text-xs text-dim">= {{ dolar($hargaAkhir) }}</p>
                                        @error("varian.$i.selisih_usd") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>

                                    <td class="p-3">
                                        <input type="text" inputmode="numeric" wire:model="varian.{{ $i }}.stok"
                                               class="field tech w-20 py-2"
                                               aria-label="{{ __('site.admin.produk_form.stok') }} row {{ $i + 1 }}"
                                               aria-invalid="{{ $errors->has('varian.'.$i.'.stok') ? 'true' : 'false' }}">
                                        @error("varian.$i.stok") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>

                                    <td class="p-3">
                                        <span class="tech text-xs {{ ($v['ditahan'] ?? 0) > 0 ? 'text-warn' : 'text-dim' }}">
                                            {{ $v['ditahan'] ?? 0 }}
                                        </span>
                                    </td>

                                    <td class="p-3">
                                        <input type="text" inputmode="numeric" wire:model="varian.{{ $i }}.berat"
                                               class="field tech w-24 py-2" placeholder="{{ $weight_gram }}"
                                               aria-label="Weight row {{ $i + 1 }}">
                                    </td>

                                    <td class="p-3">
                                        <span class="switch">
                                            <input type="checkbox" wire:model="varian.{{ $i }}.aktif"
                                                   aria-label="Variant row {{ $i + 1 }} active">
                                            <span></span>
                                        </span>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="salinVarian({{ $i }})"
                                                    class="btn-icon btn-icon-sm" title="{{ __('site.admin.produk_form.salin_varian') }}">
                                                <x-ikon name="copy" :size="15" />
                                                <span class="visually-hidden">{{ __('site.admin.produk_form.salin_varian') }}</span>
                                            </button>
                                            <button type="button" wire:click="hapusVarian({{ $i }})"
                                                    wire:confirm="Delete variant row {{ $i + 1 }}?"
                                                    class="btn-icon btn-icon-sm hover:text-danger" title="{{ __('site.admin.produk_form.hapus_varian') }}">
                                                <x-ikon name="trash" :size="15" />
                                                <span class="visually-hidden">{{ __('site.admin.produk_form.hapus_varian') }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-8 text-center">
                                        <x-ikon name="sliders" :size="28" class="mx-auto text-dim" />
                                        <p class="mt-3 text-sm text-muted">
                                            No variants yet. A product needs at least one to be purchasable.
                                        </p>
                                        <button type="button" wire:click="tambahVarian" class="btn btn-primary mt-4">
                                            <x-ikon name="plus" :size="16" />
                                            Add first variant
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="simpanVarian" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanVarian">{{ __('site.admin.produk_form.simpan_gambar') }}</span>
                    <span wire:loading wire:target="simpanVarian">{{ __('site.admin.produk_form.menyimpan') }}</span>
                </button>
                <button type="button" wire:click="aturTab('info')" class="btn btn-outline">{{ __('site.admin.produk_form.kembali_info') }}</button>
            </div>
        </div>
    @endif

    {{-- ══ TAB 3: GAMBAR ════════════════════════════════════════════ --}}
    @if ($tab === 'media')
        <div class="space-y-4">

            {{-- Unggah --}}
            <div class="plate p-4">
                <h2 class="font-display text-lg font-semibold">{{ __('site.admin.produk_form.unggah_gambar') }}</h2>
                <p class="mt-0.5 text-xs text-dim">
                    JPG, PNG, WebP, or AVIF. Maximum 5 MB per file, 200 files and 50 MB per product.
                    Each image is cropped to 4:3 before upload, so portrait photos fill the card without empty space.
                    Files are processed in the background; new uploads appear after a moment.
                </p>

                <x-file-upload model="berkasBaru" multiple crop="product"
                               accept="image/jpeg,image/png,image/webp,image/avif"
                               title="Click to choose images or drop them here"
                               class="mt-3">
                    JPG, PNG, WebP, or AVIF · up to 5 MB per file
                </x-file-upload>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="unggah" class="btn btn-primary"
                            wire:loading.attr="disabled" @disabled($berkasBaru === [])>
                        <x-ikon name="upload" :size="16" />
                        <span wire:loading.remove wire:target="unggah">Upload</span>
                        <span wire:loading wire:target="unggah">{{ __('site.admin.produk_form.mengunggah') }}</span>
                    </button>

                    <span wire:loading wire:target="berkasBaru" class="tech text-xs text-accent">
                        {{ __('site.admin.produk_form.membaca_berkas') }}
                    </span>
                </div>

                @error('berkasBaru') <p class="field-error mt-2">{{ $message }}</p> @enderror
                @error('berkasBaru.*') <p class="field-error mt-2">{{ $message }}</p> @enderror

                @if (count($berkasBaru) > 0)
                    <p class="tech mt-2 text-xs text-muted">{{ count($berkasBaru) }} files ready to upload</p>
                @endif
            </div>

            {{-- Yang ditolak --}}
            @if ($ditolak !== [])
                <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">
                    <p class="font-medium">{{ count($ditolak) }} files rejected:</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-muted">
                        @foreach ($ditolak as $alasan)
                            <li>{{ $alasan }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-dim">
                        Full reasons are kept in the upload log, including files sent through the direct endpoint.
                    </p>
                </div>
            @endif

            {{-- Sedang diproses --}}
            @if ($sedangDiproses > 0)
                <div class="plate border-l-2 border-accent p-3 text-sm" role="status">
                    <p>
                        <span class="tech text-accent">{{ $sedangDiproses }}</span> {{ __('site.admin.produk_form.antrean') }}
                        <button type="button" wire:click="periksaAntrean" class="ml-1 text-accent underline">{{ __('site.admin.produk_form.periksa_lagi') }}</button>
                    </p>
                    <p class="mt-1 text-xs text-dim">
                        Processing runs in the background. If this number does not change, make sure the queue worker is running:
                        <code class="tech">php artisan queue:work</code>
                    </p>
                </div>
            @endif

            {{-- Daftar gambar --}}
            @if ($media->isEmpty())
                <div class="plate p-8 text-center">
                    <x-ikon name="image" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">{{ __('site.admin.produk_form.belum_ada_gambar') }}</p>
                </div>
            @else
                <div class="plate p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-semibold">
                            {{ $media->count() }} images
                        </h2>
                        <p class="text-xs text-dim">{{ __('site.admin.produk_form.urutan') }} here determines the order on the product page. The primary image is shown in the catalog.</p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($media as $g)
                            <div class="plate overflow-hidden" wire:key="media-{{ $g->id }}">
                                <div class="relative aspect-[4/3] bg-sunken">
                                    <img src="{{ $g->url('card') }}" alt="{{ $g->alt() }}"
                                         width="{{ $g->width }}" height="{{ $g->height }}"
                                         loading="lazy" decoding="async"
                                         class="h-full w-full object-cover">

                                    @if ($g->is_primary)
                                        <span class="absolute left-1.5 top-1.5 rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-fg">
                                            {{ __('site.admin.produk_form.utama') }}
                                        </span>
                                    @endif

                                    <span class="tech absolute right-1.5 top-1.5 rounded-md bg-black/60 px-1.5 py-0.5 text-xs text-white">
                                        {{ $loop->iteration }}
                                    </span>
                                </div>

                                <div class="space-y-2 p-2">
                                    <p class="truncate text-xs text-muted" title="{{ $g->original_filename }}">
                                        {{ $g->original_filename ?: 'untitled' }}
                                    </p>
                                    <p class="tech text-xs text-dim">
                                        {{ $g->width }}×{{ $g->height }} · {{ ukuran_berkas($g->byte_size) }}
                                    </p>

                                    <div>
                                        <label for="alt-{{ $g->id }}" class="part-no">{{ __('site.admin.produk_form.teks_alternatif') }}</label>
                                        <input id="alt-{{ $g->id }}" type="text"
                                               class="field mt-1 py-1.5 text-[length:var(--t-xs)]"
                                               placeholder="{{ $produk->judul() }}"
                                               value="{{ $g->alt_text_en ?: $g->alt_text_id }}"
                                               wire:blur="simpanAlt({{ $g->id }}, $event.target.value, 'en')">
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="geserMedia({{ $g->id }}, 'naik')"
                                                class="btn-icon h-7 w-7" title="{{ __('site.admin.produk_form.geser_kiri') }}"
                                                {{ $loop->first ? 'disabled' : '' }}>
                                            <x-ikon name="chevron-left" :size="13" />
                                            <span class="visually-hidden">{{ __('site.admin.produk_form.geser_kiri') }}</span>
                                        </button>
                                        <button type="button" wire:click="geserMedia({{ $g->id }}, 'turun')"
                                                class="btn-icon h-7 w-7" title="{{ __('site.admin.produk_form.geser_kanan') }}"
                                                {{ $loop->last ? 'disabled' : '' }}>
                                            <x-ikon name="chevron-right" :size="13" />
                                            <span class="visually-hidden">{{ __('site.admin.produk_form.geser_kanan') }}</span>
                                        </button>

                                        @unless ($g->is_primary)
                                            <button type="button" wire:click="jadikanUtama({{ $g->id }})"
                                                    class="btn-icon h-7 w-7" title="{{ __('site.admin.produk_form.jadikan_utama') }}">
                                                <x-ikon name="star" :size="14" />
                                                <span class="visually-hidden">{{ __('site.admin.produk_form.jadikan_utama') }}</span>
                                            </button>
                                        @endunless

                                        <button type="button" wire:click="hapusMedia({{ $g->id }})"
                                                wire:confirm="Delete this image?"
                                                class="btn-icon ml-auto h-7 w-7 hover:text-danger" title="{{ __('site.admin.produk_form.hapus_gambar') }}">
                                            <x-ikon name="trash" :size="14" />
                                            <span class="visually-hidden">{{ __('site.admin.produk_form.hapus_gambar') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" wire:click="aturTab('varian')" class="btn btn-outline">{{ __('site.admin.produk_form.kembali_varian') }}</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-primary">{{ __('site.admin.produk_form.selesai') }}</a>
            </div>
        </div>
    @endif
</div>
