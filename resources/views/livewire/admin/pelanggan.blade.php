{{-- {{ __('site.admin.pelanggan.judul') }} dan akun panel.

     {{ __('site.admin.pelanggan.peran') }} dan blokir diubah langsung dari baris tabel: keduanya tindakan yang
     sering dipakai saat menanggapi keluhan, dan membuka formulir untuk satu
     perubahan kecil memperlambat pekerjaan. --}}

<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.pelanggan.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.pelanggan.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua'], 0, ',', '.') }} accounts ·
                {{ number_format($jumlah['pelanggan'], 0, ',', '.') }} customers ·
                {{ number_format($jumlah['panel'], 0, ',', '.') }} panel users
                @if ($jumlah['diblokir'] > 0)
                    · <span class="text-danger">{{ $jumlah['diblokir'] }} blocked</span>
                @endif
            </p>
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    @if ($superAdminTerakhir <= 1)
        <div class="plate border-l-2 border-warn p-3 text-sm" role="status">
            There is only one super admin. That account cannot be blocked, demoted, or deleted —
            without a super admin, nobody can manage admins and payment credentials.
        </div>
    @endif

    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[200px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari" class="field pl-9"
                   placeholder="{{ __('site.admin.pelanggan.cari_placeholder') }}" aria-label="{{ __('site.admin.pelanggan.cari') }}">
        </div>

        <select wire:model.live="peran" class="field w-auto" aria-label="{{ __('site.admin.pelanggan.saring_peran') }}">
            <option value="semua">{{ __('site.admin.pelanggan.semua_peran') }}</option>
            <option value="customer">{{ __('site.admin.pelanggan.judul') }}</option>
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
        </select>

        <select wire:model.live="status" class="field w-auto" aria-label="{{ __('site.admin.pelanggan.saring_status') }}">
            <option value="semua">{{ __('site.admin.pelanggan.semua_status') }}</option>
            <option value="aktif">{{ __('site.admin.pelanggan.aktif') }}</option>
            <option value="diblokir">{{ __('site.admin.pelanggan.diblokir') }}</option>
        </select>

        @if ($cari !== '' || $peran !== 'semua' || $status !== 'semua')
            <a href="{{ route('admin.customers.index') }}" class="btn btn-ghost btn-sm">
                <x-ikon name="close" :size="14" />
                {{ __('site.admin.pelanggan.bersihkan') }}
            </a>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="users" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">{{ __('site.admin.pelanggan.tidak_ada_cocok') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">{{ __('site.admin.pelanggan.nama') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.pelanggan.peran') }}</th>
                                <th class="part-no p-3 text-right font-medium">{{ __('site.admin.pelanggan.pesanan') }}</th>
                                <th class="part-no p-3 font-medium">{{ __('site.admin.pelanggan.terakhir_masuk') }}</th>
                                <th class="p-3"><span class="visually-hidden">{{ __('site.admin.pelanggan.tindakan') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $u)
                                <tr wire:key="user-{{ $u->id }}"
                                    class="border-b border-line-soft last:border-0 hover:bg-sunken {{ $dipilih === $u->id ? 'bg-sunken' : '' }}">
                                    <td class="p-3">
                                        <button type="button" wire:click="buka({{ $u->id }})" class="text-left">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate font-medium">{{ $u->name }}</span>
                                                @if ($u->is_blocked)
                                                    <span class="rounded-full bg-danger/12 px-1.5 py-0.5 text-xs text-danger">blocked</span>
                                                @endif
                                                @if (auth()->id() === $u->id)
                                                    <span class="rounded-full bg-accent/12 px-1.5 py-0.5 text-xs text-accent">{{ __('site.admin.pelanggan.anda') }}</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block truncate text-xs text-dim">{{ $u->email }}</span>
                                        </button>
                                    </td>

                                    <td class="p-3">
                                        @if (auth()->user()?->role->bisaUbahKredensial() && auth()->id() !== $u->id)
                                            <select wire:change="ubahPeran({{ $u->id }}, $event.target.value)"
                                                    class="field w-auto py-1 text-[length:var(--t-xs)]" aria-label="{{ __('site.admin.pelanggan.peran') }} {{ $u->name }}">
                                                <option value="customer" @selected($u->role->value === 'customer')>{{ __('site.admin.pelanggan.judul') }}</option>
                                                <option value="admin" @selected($u->role->value === 'admin')>Admin</option>
                                                <option value="super_admin" @selected($u->role->value === 'super_admin')>Super Admin</option>
                                            </select>
                                        @else
                                            <span class="text-xs text-muted">{{ $u->role->label() }}</span>
                                        @endif
                                    </td>

                                    <td class="p-3 text-right">
                                        @if ($u->orders_count > 0)
                                            <span class="tech">{{ $u->orders_count }}</span>
                                            <span class="block text-xs text-dim">{{ dolar($u->total_belanja ?? 0) }}</span>
                                        @else
                                            <span class="tech text-dim">—</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($u->last_login_at)
                                            <span class="text-xs text-muted">{{ $u->last_login_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-xs text-dim">{{ __('site.admin.pelanggan.belum_pernah') }}</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @unless (auth()->id() === $u->id)
                                                <button type="button" wire:click="ubahBlokir({{ $u->id }})"
                                                        wire:confirm="{{ $u->is_blocked ? 'Unblock' : 'Block' }} {{ $u->name }}?"
                                                        class="btn-icon btn-icon-sm {{ $u->is_blocked ? 'text-danger' : '' }}"
                                                        title="{{ $u->is_blocked ? 'Unblock' : 'Block' }}">
                                                    <x-ikon name="{{ $u->is_blocked ? 'lock' : 'shield' }}" :size="15" />
                                                    <span class="visually-hidden">{{ $u->is_blocked ? 'Unblock' : 'Block' }}</span>
                                                </button>
                                                <button type="button" wire:click="hapus({{ $u->id }})"
                                                        wire:confirm="{{ __('site.admin.pelanggan.hapus') }} account {{ $u->name }}?"
                                                        class="btn-icon btn-icon-sm hover:text-danger" title="{{ __('site.admin.pelanggan.hapus') }}">
                                                    <x-ikon name="trash" :size="15" />
                                                    <span class="visually-hidden">{{ __('site.admin.pelanggan.hapus') }}</span>
                                                </button>
                                            @endunless
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

        {{-- Rincian --}}
        <div class="plate h-fit p-4 lg:sticky lg:top-20">
            @if ($rinci === null)
                <div class="py-10 text-center">
                    <x-ikon name="users" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">{{ __('site.admin.pelanggan.pilih_akun') }}</p>
                </div>
            @else
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="part-no">Account #{{ $rinci->id }}</p>
                        <h2 class="mt-1 font-display text-lg font-semibold">{{ $rinci->name }}</h2>
                        <p class="mt-0.5 text-xs text-dim">{{ $rinci->email }}</p>
                    </div>
                    <button type="button" wire:click="tutup" class="btn-icon btn-icon-sm" title="{{ __('site.admin.pelanggan.tutup') }}">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">{{ __('site.admin.pelanggan.tutup') }}</span>
                    </button>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">{{ __('site.admin.pelanggan.peran') }}</dt>
                        <dd>{{ $rinci->role->label() }}</dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">{{ __('site.admin.pelanggan.status') }}</dt>
                        <dd class="{{ $rinci->is_blocked ? 'text-danger' : 'text-ok' }}">
                            {{ $rinci->is_blocked ? __('site.admin.pelanggan.diblokir') : __('site.admin.pelanggan.aktif') }}
                        </dd>
                    </div>
                    @if ($rinci->phone_number)
                        <div class="flex gap-3">
                            <dt class="part-no w-24 flex-none pt-0.5">{{ __('site.admin.pelanggan.telepon') }}</dt>
                            <dd>
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $rinci->phone_number) }}"
                                   target="_blank" rel="noopener" class="text-accent hover:underline">
                                    {{ $rinci->phone_number }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">{{ __('site.admin.pelanggan.dua_faktor') }}</dt>
                        <dd class="{{ $rinci->punyaDuaFaktor() ? 'text-ok' : 'text-dim' }}">
                            {{ $rinci->punyaDuaFaktor() ? __('site.admin.pelanggan.aktif') : 'Not enabled' }}
                        </dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">{{ __('site.admin.pelanggan.bergabung') }}</dt>
                        <dd class="text-muted">{{ $rinci->created_at?->translatedFormat('d F Y') }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-line-soft pt-4">
                    <p class="part-no">{{ __('site.admin.pelanggan.pesanan') }} — recent</p>

                    @if ($pesananRinci->isEmpty())
                        <p class="mt-2 text-sm text-dim">{{ __('site.admin.pelanggan.belum_pernah_memesan') }}</p>
                    @else
                        <ul class="mt-2 space-y-2">
                            @foreach ($pesananRinci as $p)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <a href="{{ route('admin.orders.show', $p->ulid) }}"
                                       class="tech text-accent hover:underline">{{ $p->order_number }}</a>
                                    <span class="text-xs text-dim">{{ $p->created_at?->format('d/m/Y') }}</span>
                                    <span class="tech">{{ dolar($p->grand_total) }}</span>
                                    <span class="rounded-full px-1.5 py-0.5 text-xs
                                                 {{ $p->status->value === 'completed' ? 'bg-ok/12 text-ok' : 'bg-sunken text-muted' }}">
                                        {{ $p->status->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
