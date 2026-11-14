{{-- {{ __('site.admin.kupon.judul') }} diskon.

     {{ __('site.admin.kupon.pratinjau') }} potongan ditampilkan memakai perhitungan yang sama dengan
     checkout, sehingga batas atas dan jenis kupon dapat langsung dilihat
     akibatnya sebelum disimpan. --}}

<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.kupon.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.kupon.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua']) }} coupons ·
                {{ number_format($jumlah['berjalan']) }} active
                @if ($jumlah['habis'] > 0) · <span class="text-warn">{{ $jumlah['habis'] }} {{ __('site.admin.kupon.kuota_habis') }}</span> @endif
            </p>
        </div>

        <button type="button" wire:click="bukaForm" class="btn btn-primary">
            <x-ikon name="plus" :size="16" />
            {{ __('site.admin.kupon.baru') }}
        </button>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[180px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari" class="field pl-9"
                   placeholder="{{ __('site.admin.kupon.cari_placeholder') }}" aria-label="{{ __('site.admin.kupon.cari') }}">
        </div>

        @foreach (['semua' => 'All', 'aktif' => 'Active', 'berjalan' => 'Running', 'habis' => 'Quota used up', 'kedaluwarsa' => 'Expired'] as $nilai => $label)
            <button type="button" wire:click="aturKeadaan('{{ $nilai }}')"
                    class="btn {{ $keadaan === $nilai ? 'btn-primary' : 'btn-outline' }} btn-sm">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">

        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="tag" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">{{ __('site.admin.kupon.belum_ada') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kupon.kode') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kupon.potongan') }}</th>
                                <th class="part-no p-3 text-right font-medium">{{ __('site.admin.kupon.min_belanja') }}</th>
                                <th class="part-no p-3 text-right font-medium">{{ __('site.admin.kupon.terpakai') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kupon.masa_berlaku') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kupon.pemilik_kol') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.kupon.status') }}</th>
                                <th class="p-3"><span class="visually-hidden">{{ __('site.admin.kupon.tindakan') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $k)
                                @php
                                    $kadaluarsa = $k->ends_at !== null && $k->ends_at->isPast();
                                    $habis = $k->quota !== null && $k->used_count >= $k->quota;
                                    $belumMulai = $k->starts_at !== null && $k->starts_at->isFuture();
                                @endphp

                                <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                    <td class="p-3">
                                        <code class="tech font-medium">{{ $k->code }}</code>
                                        @if ($k->type->value === 'free_shipping')
                                            <span class="ml-1 rounded-full bg-brass/12 px-1.5 py-0.5 text-xs text-brass">shipping</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($k->type->value === 'percent')
                                            <span class="tech">{{ rtrim(rtrim(number_format((float) $k->value, 2, '.', ''), '0'), '.') }}%</span>
                                            @if ($k->max_discount)
                                                <span class="block text-xs text-dim">cap {{ dolar(\App\Support\HargaDolar::dariRupiah($k->max_discount)) }}</span>
                                            @endif
                                        @elseif ($k->type->value === 'fixed')
                                            <span class="tech">{{ dolar(\App\Support\HargaDolar::dariRupiah($k->value)) }}</span>
                                        @else
                                            <span class="text-muted">{{ __('site.admin.kupon.gratis_ongkir') }}</span>
                                        @endif
                                    </td>

                                    <td class="tech p-3 text-right">
                                        {{ $k->min_spend ? dolar(\App\Support\HargaDolar::dariRupiah($k->min_spend)) : '—' }}
                                    </td>

                                    <td class="p-3 text-right">
                                        <span class="tech {{ $habis ? 'text-warn' : '' }}">{{ $k->used_count }}</span>
                                        <span class="text-xs text-dim">/ {{ $k->quota ?? '∞' }}</span>
                                        @if ($k->orders_count > 0)
                                            <span class="block text-xs text-dim">{{ $k->orders_count }} orders</span>
                                        @endif
                                    </td>

                                    <td class="p-3 text-xs text-muted">
                                        @if ($k->starts_at || $k->ends_at)
                                            {{ $k->starts_at?->format('M j, Y') ?? '—' }}
                                            <span class="text-dim">to</span>
                                            {{ $k->ends_at?->format('M j, Y') ?? '—' }}
                                        @else
                                            <span class="text-dim">{{ __('site.admin.kupon.tanpa_batas') }}</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($k->punyaKol())
                                            <p class="text-sm">{{ $k->kol_nama ?? $k->kol_slug }}</p>
                                            <p class="tech text-xs text-dim">{{ $k->kol_kontak }}</p>
                                        @else
                                            <span class="text-dim">—</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($kadaluarsa)
                                            <span class="rounded-full bg-danger/12 px-2 py-0.5 text-xs text-danger">{{ __('site.admin.kupon.kedaluwarsa') }}</span>
                                        @elseif ($habis)
                                            <span class="rounded-full bg-warn/12 px-2 py-0.5 text-xs text-warn">{{ __('site.admin.kupon.kuota_habis') }}</span>
                                        @elseif ($belumMulai)
                                            <span class="rounded-full bg-sunken px-2 py-0.5 text-xs text-muted">{{ __('site.admin.kupon.belum_mulai') }}</span>
                                        @elseif ($k->is_active)
                                            <span class="rounded-full bg-ok/12 px-2 py-0.5 text-xs text-ok">{{ __('site.admin.kupon.berjalan') }}</span>
                                        @else
                                            <span class="rounded-full bg-sunken px-2 py-0.5 text-xs text-muted">{{ __('site.admin.kupon.nonaktif') }}</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="ubahAktif({{ $k->id }})"
                                                    wire:confirm="{{ $k->is_active ? 'Deactivate' : 'Activate' }} coupon {{ $k->code }}?"
                                                    class="btn-icon btn-icon-sm" title="{{ $k->is_active ? 'Deactivate' : 'Activate' }}">
                                                <x-ikon name="{{ $k->is_active ? 'check' : 'close' }}" :size="15" />
                                                <span class="visually-hidden">{{ $k->is_active ? 'Deactivate' : 'Activate' }}</span>
                                            </button>
                                            <button type="button" wire:click="sunting({{ $k->id }})"
                                                    class="btn-icon btn-icon-sm" title="{{ __('site.admin.kupon.sunting') }}">
                                                <x-ikon name="sliders" :size="15" />
                                                <span class="visually-hidden">{{ __('site.admin.kupon.sunting') }}</span>
                                            </button>
                                            <button type="button" wire:click="hapus({{ $k->id }})"
                                                    wire:confirm="{{ __('site.admin.kupon.hapus') }} coupon {{ $k->code }}?"
                                                    class="btn-icon btn-icon-sm hover:text-danger" title="{{ __('site.admin.kupon.hapus') }}">
                                                <x-ikon name="trash" :size="15" />
                                                <span class="visually-hidden">{{ __('site.admin.kupon.hapus') }}</span>
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

        @if ($formTerbuka)
            <div class="plate h-fit p-4 lg:sticky lg:top-20">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="part-no">{{ $suntingId ? __('site.admin.kupon.sunting') : 'New' }}</p>
                        <h2 class="font-display text-lg font-semibold">
                            {{ $suntingId ? 'Edit coupon' : 'Add coupon' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutupForm" class="btn-icon btn-icon-sm" title="{{ __('site.admin.kupon.tutup') }}">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">{{ __('site.admin.kupon.tutup') }}</span>
                    </button>
                </div>

                <form wire:submit="simpan" class="mt-4 space-y-3">
                    <div>
                        <label for="code" class="part-no">{{ __('site.admin.kupon.kode') }} <span class="text-danger">*</span></label>
                        <input id="code" type="text" wire:model="code" class="field tech mt-1 uppercase"
                               placeholder="KNALPOT10" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                        <p class="mt-1 text-xs text-dim">{{ __('site.admin.kupon.kode_hint') }}</p>
                        @error('code') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="type" class="part-no">{{ __('site.admin.kupon.jenis_potongan') }}</label>
                        <select id="type" wire:model.live="type" class="field mt-1">
                            <option value="percent">{{ __('site.admin.kupon.potongan_persen') }}</option>
                            <option value="fixed">{{ __('site.admin.kupon.potongan_nominal') }}</option>
                            <option value="free_shipping">{{ __('site.admin.kupon.gratis_ongkir') }}</option>
                        </select>
                    </div>

                    @if ($type !== 'free_shipping')
                        <div>
                            <label for="value" class="part-no">
                                {{ $type === 'percent' ? 'Percentage (%)' : 'Amount (USD)' }} <span class="text-danger">*</span>
                            </label>
                            <input id="value" type="text" inputmode="decimal" wire:model.live.debounce.400ms="value_usd"
                                   class="field tech mt-1" aria-invalid="{{ $errors->has('value_usd') ? 'true' : 'false' }}">
                            @error('value_usd') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($type === 'percent')
                        <div>
                            <label for="max_discount" class="part-no">{{ __('site.admin.kupon.batas_potongan') }}</label>
                            <input id="max_discount" type="text" inputmode="decimal"
                                   wire:model.live.debounce.400ms="max_discount_usd" class="field tech mt-1">
                            <p class="mt-1 text-xs text-dim">
                                {{ __('site.admin.kupon.persentase_hint') }}
                            </p>
                            @error('max_discount_usd') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label for="min_spend" class="part-no">{{ __('site.admin.kupon.minimum_belanja') }}</label>
                        <input id="min_spend" type="text" inputmode="decimal" wire:model="min_spend_usd" class="field tech mt-1">
                        <p class="mt-1 text-xs text-dim">{{ __('site.admin.kupon.kosong_minimum') }}</p>
                        @error('min_spend_usd') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quota" class="part-no">{{ __('site.admin.kupon.kuota') }}</label>
                        <input id="quota" type="text" inputmode="numeric" wire:model="quota" class="field tech mt-1">
                        <p class="mt-1 text-xs text-dim">{{ __('site.admin.kupon.kosong_tanpa_batas') }}</p>
                        @error('quota') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="starts_at" class="part-no">{{ __('site.admin.kupon.mulai') }}</label>
                            <input id="starts_at" type="datetime-local" wire:model="starts_at" class="field mt-1 text-xs">
                            @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="ends_at" class="part-no">{{ __('site.admin.kupon.berakhir') }}</label>
                            <input id="ends_at" type="datetime-local" wire:model="ends_at" class="field mt-1 text-xs">
                            @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- ── {{ __('site.admin.kupon.pemilik_kol') }} ────────────────────────────────────
                         Kosong = kupon biasa. Terisi = kupon berbagi milik seorang
                         KOL: punya tautan sendiri dan penjualannya terhitung di
                         halaman Performa KOL. --}}
                    <div class="rounded-lg border border-line-soft p-3">
                        <p class="part-no">{{ __('site.admin.kupon.pemilik_kol') }} <span class="font-normal text-dim">{{ __('site.admin.kupon.opsional') }}</span></p>
                        <p class="mt-1 text-xs text-dim">
                            {{ __('site.admin.kupon.kol_hint') }}
                        </p>

                        <div class="mt-3">
                            <label for="kol_nama" class="part-no">{{ __('site.admin.kupon.nama_kol') }}</label>
                            <input id="kol_nama" type="text" wire:model.blur="kol_nama" class="field mt-1"
                                   placeholder="Budi Santoso" aria-invalid="{{ $errors->has('kol_nama') ? 'true' : 'false' }}">
                            @error('kol_nama') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-3">
                            <label for="kol_slug" class="part-no">{{ __('site.admin.kupon.slug_tautan') }}</label>
                            <input id="kol_slug" type="text" wire:model.blur="kol_slug" class="field tech mt-1"
                                   placeholder="budi-santoso" aria-invalid="{{ $errors->has('kol_slug') ? 'true' : 'false' }}">
                            <p class="mt-1 text-xs text-dim">
                                Generated from the name when left blank. Lowercase letters, numbers, hyphens.
                            </p>
                            @error('kol_slug') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-3">
                            <label for="kol_kontak" class="part-no">{{ __('site.admin.kupon.kontak_kol') }}</label>
                            <input id="kol_kontak" type="text" wire:model.blur="kol_kontak" class="field mt-1"
                                   placeholder="{{ __('site.admin.kupon.kontak_placeholder') }}" aria-invalid="{{ $errors->has('kol_kontak') ? 'true' : 'false' }}">
                            @error('kol_kontak') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        @if ($kol_slug !== '' || $kol_nama !== '')
                            <p class="mt-3 break-all text-xs text-muted">
                                {{ __('site.admin.kupon.tautan_berbagi') }} <span class="tech">{{ $tautanKol ?? url('/').'?kupon='.($code ?: '…') }}</span>
                            </p>
                        @endif
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="is_active">
                            <span></span>
                        </span>
                        <span class="text-sm">{{ __('site.admin.kupon.aktif') }}</span>
                    </label>

                    {{-- {{ __('site.admin.kupon.pratinjau') }} memakai perhitungan yang sama dengan checkout. --}}
                    <div class="rounded-lg bg-sunken p-3 text-sm">
                        <p class="part-no">{{ __('site.admin.kupon.pratinjau') }}</p>
                        <p class="mt-1 text-muted">
                            Spend {{ dolar($pratinjau['belanja']) }} + shipping {{ dolar($pratinjau['ongkir']) }}
                        </p>
                        <p class="tech mt-1">
                            {{ __('site.admin.kupon.potongan') }}: <span class="text-accent">{{ dolar($pratinjau['potongan']) }}</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="simpan">{{ $suntingId ? 'Save' : 'Add' }}</span>
                            <span wire:loading wire:target="simpan">{{ __('site.admin.kupon.menyimpan') }}</span>
                        </button>
                        <button type="button" wire:click="tutupForm" class="btn btn-outline">{{ __('site.admin.kupon.batal') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
