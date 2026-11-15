{{-- Master ekspedisi.

     Satu halaman: tabel untuk melihat, mengurutkan, dan menandai kurir bawaan;
     panel samping untuk menambah atau menyunting. Checkout, footer, dan FAQ
     beranda membaca daftar yang sama, jadi menambah kurir di sini langsung
     terlihat di seluruh situs. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Shipping</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Couriers</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah) }} couriers, {{ number_format($jumlahAktif) }} active.
                The default courier is preselected at checkout.
            </p>
        </div>

        <button type="button" wire:click="bukaForm" class="btn btn-primary">
            <x-ikon name="plus" :size="16" />
            New courier
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
                   class="field pl-9" placeholder="Search by name, code, or URL"
                   aria-label="Search couriers">
        </div>

        @if ($cari !== '')
            <button type="button" wire:click="$set('cari', '')" class="btn btn-ghost">
                <x-ikon name="close" :size="15" />
                Clear
            </button>
        @endif
    </div>

    {{-- Kolom kedua hanya dipakai saat panel form terbuka. Tanpa syarat ini,
         tabel berhenti di 1fr dan menyisakan 380px kosong di kanannya. --}}
    <div @class(['grid gap-5', 'lg:grid-cols-[1fr_380px]' => $formTerbuka])>

        {{-- ── Tabel ───────────────────────────────────────────────── --}}
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="truck" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">
                        {{ $cari !== '' ? 'No couriers match this search.' : 'No couriers yet. Add the first one.' }}
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Order</th>
                                <th class="part-no p-3 font-medium">Name</th>
                                <th class="part-no p-3 font-medium">Code</th>
                                <th class="part-no p-3 font-medium">Tracking URL</th>
                                <th class="part-no p-3 font-medium">Status</th>
                                <th class="p-3"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $kurir)
                                <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="tech text-xs text-dim">{{ $kurir->display_order }}</span>
                                            <button type="button" wire:click="geser({{ $kurir->id }}, 'naik')"
                                                    class="btn-icon h-7 w-7" title="Move up"
                                                    {{ $loop->first ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" class="rotate-180" />
                                                <span class="visually-hidden">Move up</span>
                                            </button>
                                            <button type="button" wire:click="geser({{ $kurir->id }}, 'turun')"
                                                    class="btn-icon h-7 w-7" title="Move down"
                                                    {{ $loop->last ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" />
                                                <span class="visually-hidden">Move down</span>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <p class="font-medium">{{ $kurir->name }}</p>
                                        @if ($kurir->is_default)
                                            <span class="mt-1 inline-flex items-center rounded-full bg-accent/12 px-2 py-0.5 text-[length:var(--t-xs)] text-accent">
                                                Checkout default
                                            </span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <code class="tech text-xs text-muted">{{ $kurir->code }}</code>
                                    </td>

                                    <td class="p-3">
                                        @if (filled($kurir->tracking_url))
                                            <span class="tech break-all text-xs text-muted">{{ $kurir->tracking_url }}</span>
                                        @else
                                            <span class="text-xs text-dim">Not set</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <button type="button" wire:click="ubahAktif({{ $kurir->id }})"
                                                wire:confirm="{{ $kurir->is_active ? 'Deactivate' : 'Activate' }} courier &quot;{{ $kurir->name }}&quot;?"
                                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[length:var(--t-xs)]
                                                       {{ $kurir->is_active ? 'bg-ok/12 text-ok' : 'bg-danger/12 text-danger' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $kurir->is_active ? 'bg-ok' : 'bg-danger' }}"></span>
                                            {{ $kurir->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @unless ($kurir->is_default)
                                                <button type="button" wire:click="jadikanBawaan({{ $kurir->id }})"
                                                        class="btn-icon btn-icon-sm" title="Make default"
                                                        {{ ! $kurir->is_active ? 'disabled' : '' }}>
                                                    <x-ikon name="check" :size="15" />
                                                    <span class="visually-hidden">Make default</span>
                                                </button>
                                            @endunless
                                            <button type="button" wire:click="sunting({{ $kurir->id }})"
                                                    class="btn-icon btn-icon-sm" title="Edit">
                                                <x-ikon name="sliders" :size="15" />
                                                <span class="visually-hidden">Edit</span>
                                            </button>
                                            <button type="button" wire:click="hapus({{ $kurir->id }})"
                                                    wire:confirm="Delete courier &quot;{{ $kurir->name }}&quot;?"
                                                    class="btn-icon btn-icon-sm hover:text-danger" title="Delete">
                                                <x-ikon name="trash" :size="15" />
                                                <span class="visually-hidden">Delete</span>
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
                        <p class="part-no">{{ $suntingId ? 'Edit' : 'New' }}</p>
                        <h2 class="font-display text-lg font-semibold">
                            {{ $suntingId ? 'Edit courier' : 'Add courier' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutupForm" class="btn-icon btn-icon-sm" title="Close">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">Close</span>
                    </button>
                </div>

                <form wire:submit="simpan" class="mt-4 space-y-3">
                    <div>
                        <label for="nama" class="part-no">Name</label>
                        <input id="nama" type="text" wire:model.live.debounce.500ms="nama"
                               class="field mt-1" aria-invalid="{{ $errors->has('nama') ? 'true' : 'false' }}"
                               placeholder="JNE">
                        @error('nama') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="kode" class="part-no">Code</label>
                        <input id="kode" type="text" wire:model.live="kode"
                               class="field tech mt-1" aria-invalid="{{ $errors->has('kode') ? 'true' : 'false' }}">
                        <p class="mt-1 text-xs text-dim">
                            Used for matching old orders and tracking links. Generated from the name.
                        </p>
                        @error('kode') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tautan" class="part-no">Tracking URL</label>
                        <input id="tautan" type="text" wire:model="tautan"
                               class="field tech mt-1" aria-invalid="{{ $errors->has('tautan') ? 'true' : 'false' }}"
                               placeholder="https://www.jne.co.id/id/tracking/trace/%s">
                        <p class="mt-1 text-xs text-dim">
                            Put %s where the tracking number goes. Leave empty to use a web search.
                        </p>
                        @error('tautan') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="aktif">
                            <span></span>
                        </span>
                        <span class="text-sm">Show at checkout</span>
                    </label>
                    @error('aktif') <p class="field-error">{{ $message }}</p> @enderror

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="bawaan">
                            <span></span>
                        </span>
                        <span class="text-sm">Default courier</span>
                    </label>
                    @error('bawaan') <p class="field-error">{{ $message }}</p> @enderror

                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="simpan">{{ $suntingId ? 'Save changes' : 'Add' }}</span>
                            <span wire:loading wire:target="simpan">Saving…</span>
                        </button>
                        <button type="button" wire:click="tutupForm" class="btn btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
