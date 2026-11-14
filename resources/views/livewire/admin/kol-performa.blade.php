{{-- {{ __('site.admin.kol_performa.judul') }}.

     Angka di sini berasal dari baris pesanan yang sudah memakai kupon, bukan
     dari hitungan klik. Bedanya penting: klik menunjukkan minat, pesanan
     menunjukkan uang yang benar-benar masuk — dan KOL {{ __('site.admin.kol_performa.dibayar') }} dari yang kedua.

     {{ __('site.admin.kol_performa.hanya_pesanan') }} paid {{ __('site.admin.kol_performa.atau') }} completed yang dihitung. Pesanan yang
     dibatalkan {{ __('site.admin.kol_performa.atau') }} kedaluwarsa ikut dihitung akan membuat KOL tampak
     menghasilkan padahal uangnya tidak pernah masuk. --}}
<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.kol_performa.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.kol_performa.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ __('site.admin.kol_performa.keterangan') }}
            </p>
        </div>

        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline">
            <x-ikon name="tag" :size="16" />
            {{ __('site.admin.kol_performa.kupon') }} page
        </a>
    </div>

    @if ($ringkas['kol'] === 0)
        <div class="plate p-8 text-center">
            <x-ikon name="share" :size="24" class="mx-auto text-dim" />
            <h2 class="mt-3 font-medium">{{ __('site.admin.kol_performa.belum_ada_kol') }}</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted">
                Add a KOL name to a coupon on the {{ __('site.admin.kol_performa.kupon') }} page. That {{ __('site.admin.kol_performa.kupon') }}
                then gets its own share link and its sales are counted here.
            </p>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-primary mt-4">
                Open the {{ __('site.admin.kol_performa.kupon') }} page
            </a>
        </div>
    @else
        {{-- ── Ringkasan ──────────────────────────────────────────── --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.kol_performa.kol_aktif') }}</p>
                <p class="tech mt-3 text-xl font-medium">{{ $ringkas['kol'] }}</p>
            </div>
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.kol_performa.pesanan_masuk') }}</p>
                <p class="tech mt-3 text-xl font-medium">{{ number_format($ringkas['pesanan'], 0, ',', '.') }}</p>
            </div>
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.kol_performa.penjualan') }}</p>
                <p class="tech mt-3 text-xl font-medium">{{ dolar($ringkas['penjualan']) }}</p>
            </div>
        </div>

        {{-- ── Saringan ───────────────────────────────────────────── --}}
        <div class="plate flex flex-wrap items-center gap-2 p-3" x-data="{bukaUrut: false}">
            <label class="flex min-w-[12rem] flex-1 items-center gap-2 lg:max-w-md">
                <x-ikon name="search" :size="17" class="flex-none text-dim" />
                <input type="search" wire:model.live.debounce.400ms="cari" class="field"
                       placeholder="{{ __('site.admin.kol_performa.cari') }}" />
            </label>

            <div class="relative">
                <button type="button" @click="bukaUrut = !bukaUrut"
                        class="btn btn-outline" aria-haspopup="true" :aria-expanded="bukaUrut">
                    <x-ikon name="sliders" :size="16" />
                    {{ match ($urut) {
                        'pesanan' => 'Most orders',
                        'nama' => 'Name A-Z',
                        default => __('site.admin.kol_performa.penjualan') . ' — largest',
                    } }}
                    <x-ikon name="chevron-down" :size="14" class="text-dim" />
                </button>

                <div x-show="bukaUrut" x-cloak @click.outside="bukaUrut = false"
                     class="plate absolute right-0 z-20 mt-1 w-52 overflow-hidden p-1 shadow-lg">
                    @foreach (['penjualan' => __('site.admin.kol_performa.penjualan') . ' — largest', 'pesanan' => 'Most orders', 'nama' => 'Name A-Z'] as $nilai => $label)
                        <button type="button" wire:click="aturUrut('{{ $nilai }}')" @click="bukaUrut = false"
                                class="flex w-full items-center justify-between gap-2 rounded-[var(--r-kecil)] px-3 py-2 text-left text-sm hover:bg-sunken">
                            {{ $label }}
                            @if ($urut === $nilai) <x-ikon name="check" :size="15" class="text-accent" /> @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Daftar KOL ─────────────────────────────────────────── --}}
        <div class="plate overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line-soft text-left">
                            <th class="part-no p-3 font-medium">KOL</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.kol_performa.kupon') }}</th>
                            <th class="part-no p-3 text-right font-medium">{{ __('site.admin.kol_performa.pesanan_sah') }}</th>
                            <th class="part-no p-3 text-right font-medium">{{ __('site.admin.kol_performa.penjualan') }}</th>
                            <th class="part-no p-3 text-right font-medium">{{ __('site.admin.kol_performa.potongan') }}</th>
                            <th class="p-3"><span class="visually-hidden">{{ __('site.admin.kol_performa.tindakan') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $kupon)
                            <tr class="border-b border-line-soft last:border-0 hover:bg-sunken" wire:key="kol-{{ $kupon->id }}">
                                <td class="p-3">
                                    <p class="font-medium">{{ $kupon->kol_nama ?? $kupon->kol_slug }}</p>
                                    <p class="tech text-xs text-dim">{{ $kupon->kol_slug }}</p>
                                </td>

                                <td class="p-3">
                                    <span class="tech text-xs">{{ $kupon->code }}</span>
                                    @if ($kupon->quota !== null)
                                        <p class="tech text-xs text-dim">
                                            quota {{ $kupon->used_count }}/{{ $kupon->quota }}
                                        </p>
                                    @endif
                                </td>

                                <td class="tech p-3 text-right">
                                    {{ number_format((int) $kupon->pesanan_sah, 0, ',', '.') }}
                                    @if ((int) $kupon->pesanan_total > (int) $kupon->pesanan_sah)
                                        <span class="block text-xs text-dim" title="{{ __('site.admin.kol_performa.termasuk_dibatalkan') }}">
                                            {{ (int) $kupon->pesanan_total }} used
                                        </span>
                                    @endif
                                </td>

                                <td class="tech p-3 text-right font-medium">{{ dolar((float) $kupon->nilai_penjualan) }}</td>

                                <td class="tech p-3 text-right text-muted">{{ dolar((float) $kupon->potongan_diberikan) }}</td>

                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button"
                                                x-data
                                                x-on:click="navigator.clipboard.writeText(@js($kupon->tautanKol())); \$el.setAttribute('data-salin', 'tersalin'); window.setTimeout(() => \$el.removeAttribute('data-salin'), 1200)"
                                                class="btn-icon btn-icon-sm"
                                                title="{{ __('site.admin.kol_performa.salin_tautan') }}"
                                                aria-label="{{ __('site.admin.kol_performa.salin_tautan') }} {{ $kupon->kol_nama }}">
                                            <x-ikon name="copy" :size="15" />
                                            <span class="visually-hidden">{{ __('site.admin.kol_performa.salin_tautan') }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-sm text-muted">
                                    {{ __('site.admin.kol_performa.tidak_ada_cocok') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftar->hasPages())
                <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
            @endif
        </div>

        <p class="text-xs text-dim">
            {{ __('site.admin.kol_performa.hanya_pesanan') }} <em>{{ __('site.admin.kol_performa.dibayar') }}</em> {{ __('site.admin.kol_performa.atau') }} <em>{{ __('site.admin.kol_performa.selesai') }}</em> are counted.
            Cancelled or expired orders are excluded, so the sales figure
            means money that actually came in.
        </p>
    @endif
</div>
