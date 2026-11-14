{{-- CRUD kategori (F3-01).

     Satu halaman: tabel untuk melihat dan mengubah urutan, panel samping untuk
     menambah atau menyunting. Formnya tidak berpindah halaman, karena mengubah
     urutan dan menambah kategori biasanya dilakukan berurutan. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.kategori.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.kategori.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah) }} categories, {{ number_format($totalProduk) }} products.
                Order here determines the menu order on the homepage.
            </p>
        </div>

        <button type="button" wire:click="bukaForm" class="btn btn-primary">
            <x-ikon name="plus" :size="16" />
            {{ __('site.admin.kategori.baru') }}
        </button>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Pencarian ───────────────────────────────────────────────── --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[200px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari"
                   class="field pl-9" placeholder="{{ __('site.admin.kategori.cari_placeholder') }}"
                   aria-label="{{ __('site.admin.kategori.cari') }}">
        </div>

        @if ($cari !== '')
            <button type="button" wire:click="$set('cari', '')" class="btn btn-ghost">
                <x-ikon name="close" :size="15" />
                {{ __('site.admin.kategori.bersihkan') }}
            </button>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_380px]">

        {{-- ── Tabel ───────────────────────────────────────────────── --}}
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="tag" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">
                        {{ $cari !== '' ? 'No categories match this search.' : 'No categories yet. Add the first one.' }}
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Order</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kategori.nama') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kategori.slug') }}</th>
                                <th class="part-no p-3 text-right font-medium">{{ __('site.admin.kategori.produk') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kategori.status') }}</th>
                                <th class="p-3"><span class="visually-hidden">{{ __('site.admin.kategori.tindakan') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $kategori)
                                <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="tech text-xs text-dim">{{ $kategori->display_order }}</span>
                                            <button type="button" wire:click="geser({{ $kategori->id }}, 'naik')"
                                                    class="btn-icon h-7 w-7" title="{{ __('site.admin.kategori.naikkan') }}"
                                                    {{ $loop->first ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" class="rotate-180" />
                                                <span class="visually-hidden">{{ __('site.admin.kategori.naikkan') }}</span>
                                            </button>
                                            <button type="button" wire:click="geser({{ $kategori->id }}, 'turun')"
                                                    class="btn-icon h-7 w-7" title="{{ __('site.admin.kategori.turunkan') }}"
                                                    {{ $loop->last ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" />
                                                <span class="visually-hidden">{{ __('site.admin.kategori.turunkan') }}</span>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <p class="font-medium">{{ $kategori->nama() }}</p>
                                    </td>

                                    <td class="p-3">
                                        <code class="tech text-xs text-muted">{{ $kategori->slug }}</code>
                                    </td>

                                    <td class="p-3 text-right">
                                        @if ($kategori->products_count > 0)
                                            <a href="{{ route('admin.products.index', ['kategori' => $kategori->id]) }}"
                                               class="tech text-accent hover:underline">
                                                {{ $kategori->products_count }}
                                            </a>
                                        @else
                                            <span class="tech text-dim">0</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <button type="button" wire:click="ubahAktif({{ $kategori->id }})"
                                                wire:confirm="{{ $kategori->is_active ? 'Deactivate' : 'Activate' }} category &quot;{{ $kategori->nama() }}&quot;?"
                                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[length:var(--t-xs)]
                                                       {{ $kategori->is_active ? 'bg-ok/12 text-ok' : 'bg-danger/12 text-danger' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $kategori->is_active ? 'bg-ok' : 'bg-danger' }}"></span>
                                            {{ $kategori->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="sunting({{ $kategori->id }})"
                                                    class="btn-icon btn-icon-sm" title="{{ __('site.admin.kategori.sunting') }}">
                                                <x-ikon name="sliders" :size="15" />
                                                <span class="visually-hidden">{{ __('site.admin.kategori.sunting') }}</span>
                                            </button>
                                            <button type="button" wire:click="hapus({{ $kategori->id }})"
                                                    wire:confirm="{{ __('site.admin.kategori.hapus') }} category &quot;{{ $kategori->name_en ?: $kategori->name_id }}&quot;?"
                                                    class="btn-icon btn-icon-sm hover:text-danger" title="{{ __('site.admin.kategori.hapus') }}">
                                                <x-ikon name="trash" :size="15" />
                                                <span class="visually-hidden">{{ __('site.admin.kategori.hapus') }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($daftar->hasPages())
                    <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
                @endif
            @endif
        </div>

        {{-- ── Panel form ──────────────────────────────────────────── --}}
        @if ($formTerbuka)
            <div class="plate h-fit p-4 lg:sticky lg:top-20">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="part-no">{{ $suntingId ? __('site.admin.kategori.sunting') : 'New' }}</p>
                        <h2 class="font-display text-lg font-semibold">
                            {{ $suntingId ? 'Edit category' : 'Add category' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutupForm" class="btn-icon btn-icon-sm" title="{{ __('site.admin.kategori.tutup') }}">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">{{ __('site.admin.kategori.tutup') }}</span>
                    </button>
                </div>

                <form wire:submit="simpan" class="mt-4 space-y-3">
                    <div>
                        <label for="nama_id" class="part-no">{{ __('site.admin.kategori.nama') }}</label>
                        <input id="nama_id" type="text" wire:model.live.debounce.500ms="nama_id"
                               class="field mt-1" aria-invalid="{{ $errors->has('nama_id') ? 'true' : 'false' }}">
                        @error('nama_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="part-no">{{ __('site.admin.kategori.slug') }}</label>
                        <input id="slug" type="text" wire:model.live="slug"
                               class="field tech mt-1" aria-invalid="{{ $errors->has('slug') ? 'true' : 'false' }}">
                        <p class="mt-1 text-xs text-dim">{{ __('site.admin.kategori.slug_hint') }}</p>
                        @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="deskripsi_id" class="part-no">{{ __('site.admin.kategori.deskripsi_id') }}</label>
                        <textarea id="deskripsi_id" wire:model="deskripsi_id" class="field mt-1" rows="3"></textarea>
                        @error('deskripsi_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="aktif">
                            <span></span>
                        </span>
                        <span class="text-sm">{{ __('site.admin.kategori.tampil_menu') }}</span>
                    </label>
                    @error('aktif') <p class="field-error">{{ $message }}</p> @enderror

                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="simpan">{{ $suntingId ? 'Save changes' : 'Add' }}</span>
                            <span wire:loading wire:target="simpan">{{ __('site.admin.kategori.menyimpan') }}</span>
                        </button>
                        <button type="button" wire:click="tutupForm" class="btn btn-outline">{{ __('site.admin.kategori.batal') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
