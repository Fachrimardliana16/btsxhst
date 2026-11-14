{{-- Riwayat kejadian keamanan (F10-12).

     Alamat IP tidak pernah disimpan asli, hanya sidik jarinya. Itu dinyatakan
     terus terang di halaman ini supaya tidak ada yang menebak-nebak mengapa
     yang tampil bukan bentuk 192.168.x.x. --}}

<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">{{ __('site.admin.keamanan.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('site.admin.keamanan.judul') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($ringkas['total']) }} events in the last {{ $hari }} days.
                Addresses are stored as fingerprints, not as the original address.
            </p>
        </div>

        @if ($percobaanTerkunci > 0)
            <div class="plate border-l-2 border-warn px-3 py-2 text-[length:var(--t-sm)]" role="status">
                <span class="tech text-warn">{{ $percobaanTerkunci }}</span> {{ __('site.admin.keamanan.terkunci_sementara') }}
            </div>
        @endif
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @if ($galatUmum)
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $galatUmum }}</div>
    @endif

    {{-- Angka ringkas --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => __('site.admin.keamanan.kejadian'), 'nilai' => $ringkas['total'], 'ikon' => 'activity'],
            ['label' => 'Failed sign-ins', 'nilai' => $ringkas['gagalMasuk'], 'ikon' => 'lock'],
            ['label' => __('site.admin.keamanan.blokir').' '.__('site.admin.keamanan.aktif'), 'nilai' => $ringkas['blokirAktif'], 'ikon' => 'shield'],
            ['label' => 'Unauthorized access', 'nilai' => $ringkas['aksesTidakSah'], 'ikon' => 'alert'],
        ] as $k)
            <div class="plate p-4">
                <div class="flex items-center justify-between">
                    <p class="part-no">{{ $k['label'] }}</p>
                    <x-ikon :name="$k['ikon']" :size="17" class="text-dim" />
                </div>
                <p class="tech mt-3 text-xl font-medium lg:text-2xl">{{ number_format($k['nilai'], 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    {{-- Grafik {{ __('site.admin.keamanan.jam_24') }} --}}
    <div class="plate p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium">{{ __('site.admin.keamanan.kejadian_per_jam') }}</p>
            <p class="part-no">peak {{ $puncakGrafik }}</p>
        </div>

        <div class="mt-3 flex h-24 items-end gap-0.5" role="img"
             aria-label="{{ __('site.admin.keamanan.grafik_judul') }}">
            @foreach ($grafik as $g)
                @php $tinggi = $g['jumlah'] === 0 ? 2 : max(6, (int) round($g['jumlah'] / $puncakGrafik * 100)); @endphp
                <div class="flex-1 rounded-t-sm {{ $g['jumlah'] > 0 ? 'bg-accent' : 'bg-line-soft' }}"
                     style="height: {{ $tinggi }}%"
                     title="{{ $g['jam'] }}:00 — {{ $g['jumlah'] }} events"></div>
            @endforeach
        </div>

        <div class="mt-1 flex justify-between">
            <span class="part-no">{{ $grafik->first()['jam'] }}:00</span>
            <span class="part-no">{{ __('site.admin.keamanan.sekarang') }}</span>
        </div>
    </div>

    {{-- {{ __('site.admin.keamanan.blokir') }} {{ __('site.admin.keamanan.aktif') }} --}}
    <div class="plate overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line-soft p-3">
            <div>
                <p class="text-sm font-medium">{{ __('site.admin.keamanan.alamat_diblokir') }}</p>
                <p class="text-xs text-dim">
                    {{ __('site.admin.keamanan.cara_blokir') }}
                </p>
            </div>

            <button type="button" wire:click="bersihkanPercobaanGagal" class="btn btn-ghost btn-sm">
                <x-ikon name="refresh" :size="14" />
                {{ __('site.admin.keamanan.bersihkan_percobaan') }}
            </button>
        </div>

        {{-- {{ __('site.admin.keamanan.blokir') }} manual: masukan berupa sidik jari yang di{{ __('site.admin.keamanan.salin') }} dari tabel. --}}
        <div class="border-b border-line-soft p-3">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" id="hash-blokir" class="field tech min-w-[240px] flex-1 text-xs"
                       placeholder="{{ __('site.admin.keamanan.sidik_jari_placeholder') }}"
                       x-data
                       x-on:input="$wire.set('hashBlokir', $event.target.value)"
                       aria-label="{{ __('site.admin.keamanan.sidik_jari_input') }}">
                <select class="field w-auto text-xs" x-data
                        x-on:change="$wire.set('jamBlokir', $event.target.value)"
                        aria-label="{{ __('site.admin.keamanan.lama_blokir') }}">
                    <option value="1">{{ __('site.admin.keamanan.jam_1') }}</option>
                    <option value="24" selected>{{ __('site.admin.keamanan.jam_24') }}</option>
                    <option value="168">{{ __('site.admin.keamanan.hari_7') }}</option>
                </select>
                <button type="button"
                        x-data
                        x-on:click="$wire.blokirManual($el.parentElement.querySelector('#hash-blokir').value, Number($el.parentElement.querySelector('select').value))"
                        class="btn btn-outline btn-sm">
                    <x-ikon name="shield" :size="14" />
                    {{ __('site.admin.keamanan.blokir') }}
                </button>
            </div>
        </div>

        @if ($blokir->isEmpty())
            <p class="p-6 text-center text-sm text-dim">{{ __('site.admin.keamanan.tidak_ada_diblokir') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line-soft text-left">
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.sidik_jari') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.alasan') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.sampai') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.status') }}</th>
                            <th class="p-3"><span class="visually-hidden">{{ __('site.admin.keamanan.tindakan') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blokir as $b)
                            <tr class="border-b border-line-soft last:border-0">
                                <td class="p-3">
                                    <code class="tech text-xs" title="{{ $b['hash'] }}">{{ substr($b['hash'], 0, 16) }}…</code>
                                </td>
                                <td class="p-3 text-muted">{{ $b['alasan'] ?: '—' }}</td>
                                <td class="p-3 text-xs text-muted">
                                    {{ $b['sampai']?->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="p-3">
                                    @if ($b['aktif'])
                                        <span class="rounded-full bg-danger/12 px-2 py-0.5 text-xs text-danger">{{ __('site.admin.keamanan.aktif') }}</span>
                                    @else
                                        <span class="rounded-full bg-sunken px-2 py-0.5 text-xs text-muted">{{ __('site.admin.keamanan.kedaluwarsa') }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right">
                                    @if ($b['aktif'])
                                        <button type="button" wire:click="bukaBlokir('{{ $b['hash'] }}')"
                                                wire:confirm="Unblock this address?"
                                                class="btn btn-outline btn-sm">{{ __('site.admin.keamanan.buka_blokir') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Saringan --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <span class="part-no">{{ __('site.admin.keamanan.rentang') }}</span>
        @foreach (['1' => __('site.admin.keamanan.jam_24'), '7' => __('site.admin.keamanan.hari_7'), '30' => '30 days', '90' => '90 days'] as $nilai => $label)
            <button type="button" wire:click="aturHari('{{ $nilai }}')"
                    class="btn {{ $hari === $nilai ? 'btn-primary' : 'btn-outline' }} btn-sm">{{ $label }}</button>
        @endforeach
    </div>

    <div class="-mx-3 overflow-x-auto px-3 lg:mx-0 lg:px-0">
        <div class="flex min-w-max gap-1 border-b border-line-soft">
            <button type="button" wire:click="aturTipe('semua')"
                    class="flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-[length:var(--t-sm)] whitespace-nowrap
                           {{ $tipe === 'semua' ? 'border-accent font-medium text-accent' : 'border-transparent text-muted hover:text-ink' }}">
                {{ __('site.admin.keamanan.semua') }}
                <span class="tech text-xs text-dim">{{ $perJenis->sum() }}</span>
            </button>

            @foreach ($jenis as $j)
                @php $n = (int) ($perJenis[$j->value] ?? 0); @endphp
                <button type="button" wire:click="aturTipe('{{ $j->value }}')"
                        class="flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-[length:var(--t-sm)] whitespace-nowrap
                               {{ $tipe === $j->value ? 'border-accent font-medium text-accent' : 'border-transparent text-muted hover:text-ink' }}">
                    {{ $j->label() }}
                    @if ($n > 0)
                        <span class="tech text-xs {{ $tipe === $j->value ? 'text-accent' : 'text-dim' }}">{{ $n }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Tabel kejadian --}}
    <div class="plate overflow-hidden">
        @if ($daftar->total() === 0)
            <div class="p-8 text-center">
                <x-ikon name="shield" :size="28" class="mx-auto text-dim" />
                <p class="mt-3 text-sm text-muted">{{ __('site.admin.keamanan.tidak_ada_kejadian') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line-soft text-left">
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.waktu') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.kejadian') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.sidik_jari') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.rute') }}</th>
                            <th class="part-no p-3 font-medium">{{ __('site.admin.keamanan.keterangan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $log)
                            <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                <td class="p-3">
                                    <span class="tech text-xs">{{ $log->created_at?->format('d/m H:i') }}</span>
                                    <span class="block text-xs text-dim">{{ $log->created_at?->diffForHumans() }}</span>
                                </td>

                                <td class="p-3">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-1.5 w-1.5 rounded-full
                                            {{ match ($log->event_type) {
                                                \App\Enums\TipeKejadianKeamanan::LoginGagal,
                                                \App\Enums\TipeKejadianKeamanan::BruteForceDiblokir,
                                                \App\Enums\TipeKejadianKeamanan::AksesTidakSah,
                                                \App\Enums\TipeKejadianKeamanan::DuaFaktorGagal => 'bg-danger',
                                                \App\Enums\TipeKejadianKeamanan::LoginBerhasil => 'bg-ok',
                                                default => 'bg-warn',
                                            } }}"></span>
                                        {{ $log->event_type->label() }}
                                    </span>
                                    @if ($log->email_attempted)
                                        <span class="block text-xs text-dim">{{ $log->email_attempted }}</span>
                                    @endif
                                </td>

                                <td class="p-3">
                                    <code class="tech text-xs text-dim" title="{{ $log->ip_hash }}">
                                        {{ substr((string) $log->ip_hash, 0, 12) }}…
                                    </code>
                                    <button type="button"
                                            x-data
                                            x-on:click="navigator.clipboard.writeText('{{ $log->ip_hash }}').then(() => $el.textContent = 'copied').catch(() => $el.textContent = 'failed')"
                                            class="ml-1 text-[length:var(--t-xs)] text-accent hover:underline">{{ __('site.admin.keamanan.salin') }}</button>
                                </td>

                                <td class="p-3">
                                    <code class="tech text-xs text-muted">{{ $log->route ?: '—' }}</code>
                                </td>

                                <td class="p-3 text-xs text-muted">
                                    {{ $log->payload_summary ?: '—' }}
                                    @if ($log->user)
                                        <span class="block text-dim">by {{ $log->user->name }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line-soft p-3">
                <p class="tech text-xs text-dim">
                    Showing {{ $daftar->firstItem() }}–{{ $daftar->lastItem() }} of {{ $daftar->total() }} events
                </p>

                @if ($daftar->hasPages())
                    {{ $daftar->links() }}
                @endif
            </div>
        @endif
    </div>

    <p class="text-xs text-dim">
        25 events are shown per page. IP addresses are not stored in raw form:
        only their HMAC is kept, so they cannot be reversed into an address. Unblocking still
        works because it uses the same fingerprint.
    </p>
</div>
