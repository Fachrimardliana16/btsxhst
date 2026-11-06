<div class="space-y-5" wire:key="setelan-{{ $tab }}-{{ $versi }}">

    {{-- ── Pemilih tab ─────────────────────────────────────────────── --}}
    <div class="-mx-3 overflow-x-auto px-3 lg:mx-0 lg:px-0">
        <div class="flex min-w-max gap-1 border-b border-line-soft" role="tablist">
            @foreach ($daftarTab as $t)
                <button type="button" role="tab"
                        wire:click="pilihTab('{{ $t['nilai'] }}')"
                        @if ($t['nilai'] === $tab) aria-current="page" @endif
                        class="flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-[length:var(--t-sm)] whitespace-nowrap
                               {{ $t['nilai'] === $tab
                                    ? 'border-accent font-medium text-accent'
                                    : 'border-transparent text-muted hover:text-ink' }}">
                    <x-ikon :name="$t['ikon']" :size="17" />
                    {{ $t['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── Pesan hasil ─────────────────────────────────────────────── --}}
    @if ($pesanSukses)
        <div class="plate flex items-start gap-3 border-l-2 border-ok p-3 text-sm" role="status">
            <x-ikon name="check" :size="18" class="mt-0.5 flex-none text-ok" />
            <p class="flex-1">{{ $pesanSukses }}</p>
        </div>
    @endif

    @if ($galat)
        <div class="plate flex items-start gap-3 border-l-2 border-danger p-3 text-sm" role="alert">
            <x-ikon name="alert" :size="18" class="mt-0.5 flex-none text-danger" />
            <p class="flex-1">{{ $galat }}</p>
        </div>
    @endif

    @error('berkas.*')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    <form wire:submit="simpan" class="space-y-5">

        {{-- ── Hasil uji koneksi PayPal ────────────────────────────── --}}
        @if ($tab === 'payment' && $hasilPaypal !== null)
            <div class="plate p-4 text-sm {{ $hasilPaypal['ok'] ? 'border-l-2 border-ok' : 'border-l-2 border-danger' }}"
                 role="status">
                <div class="flex items-start gap-3">
                    <x-ikon :name="$hasilPaypal['ok'] ? 'check' : 'alert'" :size="18"
                            class="mt-0.5 flex-none {{ $hasilPaypal['ok'] ? 'text-ok' : 'text-danger' }}" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $hasilPaypal['pesan'] }}</p>

                        @if (! empty($hasilPaypal['detail']))
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-muted">
                                @foreach ($hasilPaypal['detail'] as $k => $v)
                                    <dt class="part-no">{{ $k }}</dt>
                                    <dd class="tech">{{ is_scalar($v) ? $v : json_encode($v) }}</dd>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Kolom pengaturan ────────────────────────────────────── --}}
        <div class="plate divide-y divide-line-soft">
            @foreach ($baris as $b)
                @php
                    // Tab notifikasi dipisah dua kelompok: butir yang tampil di
                    // lonceng panel, dan notifikasi yang keluar ke luar.
                    $kelompokNotif = $tab !== 'notification'
                        ? null
                        : (\Illuminate\Support\Str::startsWith($b['kunci'], 'notif_item_')
                            ? 'Butir lonceng panel'
                            : 'Notifikasi yang dikirim');

                    $kelompokTerakhir ??= null;
                @endphp

                @if ($kelompokNotif !== null && $kelompokNotif !== $kelompokTerakhir)
                    <div class="bg-sunken px-4 py-2 lg:px-5">
                        <p class="part-no">{{ $kelompokNotif }}</p>
                    </div>
                @endif

                @php($kelompokTerakhir = $kelompokNotif)

                <div class="p-4 lg:p-5" wire:key="kolom-{{ $b['kunci'] }}">

                    <div class="flex items-baseline justify-between gap-3">
                        <label for="f-{{ $b['kunci'] }}" class="text-sm font-medium">
                            {{ $b['label'] }}
                            @if ($b['rahasia'])
                                <span class="ml-1 align-middle text-dim"><x-ikon name="lock" :size="13" /></span>
                            @endif
                        </label>

                        <code class="part-no flex-none">{{ $b['kunci'] }}</code>
                    </div>

                    @if ($b['rahasia'])
                        {{-- Kredensial: {{ __('site.admin.pengaturan.kosong') }} berarti "jangan ubah". Nilai
                             {{ __('site.admin.pengaturan.tersimpan') }} tidak pernah dikirim ke peramban. --}}
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <input id="f-{{ $b['kunci'] }}"
                                   type="password"
                                   autocomplete="new-password"
                                   wire:model="nilai.{{ $b['kunci'] }}"
                                   placeholder="{{ $b['terisi'] ? '•••••••• {{ __('site.admin.pengaturan.tersimpan') }} — ketik untuk mengganti' : 'belum diisi' }}"
                                   class="field tech min-w-0 flex-1" />

                            @if ($b['terisi'])
                                <span class="badge badge-ok flex-none">{{ __('site.admin.pengaturan.tersimpan') }}</span>

                                <button type="button"
                                        wire:click="hapusKredensial('{{ $b['kunci'] }}')"
                                        wire:confirm="Hapus kredensial ini? Tindakan ini tidak dapat dibatalkan."
                                        class="btn-icon flex-none text-danger"
                                        title="Hapus kredensial {{ __('site.admin.pengaturan.tersimpan') }}">
                                    <x-ikon name="trash" :size="17" />
                                    <span class="visually-hidden">Hapus {{ $b['label'] }}</span>
                                </button>
                            @else
                                <span class="badge badge-neutral flex-none">{{ __('site.admin.pengaturan.kosong') }}</span>
                            @endif
                        </div>

                    @elseif ($b['tipe'] === 'boolean')
                        <label class="mt-3 flex min-h-[44px] cursor-pointer items-center gap-2">
                            <span class="switch">
                                <input type="checkbox" wire:model="nilai.{{ $b['kunci'] }}" />
                                <span></span>
                            </span>
                            <span class="text-sm text-muted">{{ __('site.admin.pengaturan.aktifkan') }}</span>
                        </label>

                    @elseif ($b['tipe'] === 'select')
                        <select id="f-{{ $b['kunci'] }}"
                                wire:model="nilai.{{ $b['kunci'] }}"
                                class="field mt-2">
                            <option value="">{{ __('site.admin.pengaturan.belum_dipilih') }}</option>
                            @foreach ($b['opsi'] as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>

                    @elseif ($b['tipe'] === 'textarea')
                        <textarea id="f-{{ $b['kunci'] }}"
                                  wire:model="nilai.{{ $b['kunci'] }}"
                                  rows="3"
                                  class="field mt-2"></textarea>

                    @elseif ($b['tipe'] === 'image')
                        {{-- Berkas diunggah saat simpan, bukan saat dipilih,
                             supaya tidak ada berkas tayang sebelum disetujui. --}}
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            @if (filled($nilai[$b['kunci']] ?? null))
                                <img src="{{ \App\Models\ProductMedia::urlMedia($nilai[$b['kunci']]) }}"
                                     alt="{{ $b['label'] }}"
                                     class="h-12 w-auto flex-none rounded border border-line-soft bg-sunken object-contain p-1" />
                            @else
                                <span class="badge badge-neutral flex-none">{{ __('site.admin.pengaturan.belum_ada_berkas') }}</span>
                            @endif

                            <input id="f-{{ $b['kunci'] }}"
                                   type="file"
                                   accept="image/jpeg,image/png,image/webp,image/avif"
                                   wire:model="berkas.{{ $b['kunci'] }}"
                                   class="min-w-0 flex-1 text-sm" />
                        </div>

                        @if ($berkas[$b['kunci']] ?? null)
                            <p class="tech mt-2 text-xs text-muted" wire:loading.remove
                               wire:target="berkas.{{ $b['kunci'] }}">
                                Siap diunggah: {{ ukuran_berkas($berkas[$b['kunci']]->getSize()) }}
                            </p>
                            <p class="tech mt-2 text-xs text-dim" wire:loading wire:target="berkas.{{ $b['kunci'] }}">
                                {{ __('site.admin.pengaturan.mengunggah') }}
                            </p>
                        @endif

                    @elseif ($b['tipe'] === 'json')
                        <textarea id="f-{{ $b['kunci'] }}"
                                  wire:model="nilai.{{ $b['kunci'] }}"
                                  rows="3"
                                  spellcheck="false"
                                  class="field tech mt-2 text-xs"></textarea>

                    @else
                        <input id="f-{{ $b['kunci'] }}"
                               type="{{ $b['tipe'] === 'email' ? 'email' : ($b['tipe'] === 'number' ? 'number' : ($b['tipe'] === 'url' ? 'url' : 'text')) }}"
                               wire:model="nilai.{{ $b['kunci'] }}"
                               class="field mt-2 {{ $b['tipe'] === 'url' ? 'tech text-xs' : '' }}" />
                    @endif

                    @error('nilai.'.$b['kunci'])
                        <p class="field-error">{{ $message }}</p>
                    @enderror

                    @if ($b['bantuan'])
                        <p class="mt-2 text-xs text-dim">{{ $b['bantuan'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ── Tindakan ────────────────────────────────────────────── --}}
        <div class="plate-up sticky bottom-[72px] z-10 flex flex-wrap items-center gap-3 p-3 lg:bottom-4">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpan">
                <x-ikon name="check" :size="17" />
                <span wire:loading.remove wire:target="simpan">{{ __('site.admin.pengaturan.simpan') }} {{ $grup->label() }}</span>
                <span wire:loading wire:target="simpan">{{ __('site.admin.pengaturan.menyimpan') }}</span>
            </button>

            @if ($tab === 'email')
                {{-- Tombol uji diletakkan SEBELAH {{ __('site.admin.pengaturan.simpan') }}, bukan di bawahnya:
                     pengguna mengetik isian lalu langsung ingin tahu apakah
                     berhasil, tanpa harus menyimpan dulu. --}}
                <button type="button" class="btn btn-outline" wire:click="ujiSmtp"
                        wire:loading.attr="disabled" wire:target="ujiSmtp">
                    <span wire:loading.remove wire:target="ujiSmtp">
                        <x-ikon name="mail" :size="17" /> {{ __('site.admin.pengaturan.kirim_email_uji') }}
                    </span>
                    <span wire:loading wire:target="ujiSmtp">{{ __('site.admin.pengaturan.mengirim') }}</span>
                </button>
            @endif

            @if ($tab === 'payment')
                <button type="button" class="btn btn-outline" wire:click="ujiKoneksiPaypal"
                        wire:loading.attr="disabled" wire:target="ujiKoneksiPaypal"
                        @disabled($sedangUji)>
                    <x-ikon name="refresh" :size="17" />
                    <span wire:loading.remove wire:target="ujiKoneksiPaypal">{{ __('site.admin.pengaturan.uji_koneksi_paypal') }}</span>
                    <span wire:loading wire:target="ujiKoneksiPaypal">{{ __('site.admin.pengaturan.menghubungi_paypal') }}</span>
                </button>
            @endif

            @if ($tab === 'notification')
                <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline">
                    <x-ikon name="bell" :size="17" />
                    {{ __('site.admin.pengaturan.daftar_perangkat') }}
                </a>
            @endif

            <p class="text-xs text-dim">
                {{ __('site.admin.pengaturan.berlaku_10_detik') }}
            </p>
        </div>
    </form>
</div>
