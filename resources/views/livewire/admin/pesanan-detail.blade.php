<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted" aria-label="{{ __('site.admin.pesanan_detail.jejak_navigasi') }}">
                <a href="{{ route('admin.orders.index') }}" wire:navigate class="hover:text-ink">{{ __('site.admin.pesanan_detail.judul') }}</a>
                <x-ikon name="chevron-right" :size="13" />
                <span class="tech">{{ $order->order_number }}</span>
            </nav>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <h1 class="font-display text-2xl font-semibold">{{ $order->order_number }}</h1>
                <span class="badge badge-{{ $order->status->nada() }}">{{ $order->status->label() }}</span>
                <span class="badge {{ $order->payment_status === \App\Enums\Status{{ __('site.admin.pesanan_detail.pembayaran') }}::Paid ? 'badge-ok' : 'badge-warn' }}">
                    {{ $order->payment_status->label() }}
                </span>
            </div>

            <p class="mt-1 text-sm text-muted">
                Dibuat {{ $order->created_at->format('d M Y H:i') }}
                @if ($order->paid_at) · dibayar {{ $order->paid_at->format('d M Y H:i') }} @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.orders.receipt', $order->ulid) }}" target="_blank" rel="noopener" class="btn btn-primary">
                <x-ikon name="printer" :size="17" />
                {{ __('site.admin.pesanan_detail.cetak_resi') }}
            </a>
            <a href="{{ route('admin.orders.invoice', $order->ulid) }}" target="_blank" rel="noopener" class="btn btn-outline">
                <x-ikon name="receipt" :size="17" />
                {{ __('site.admin.pesanan_detail.invoice') }}
            </a>
            <a href="{{ route('admin.orders.delivery', $order->ulid) }}" target="_blank" rel="noopener" class="btn btn-outline">
                <x-ikon name="box" :size="17" />
                {{ __('site.admin.pesanan_detail.surat_jalan') }}
            </a>

            {{-- {{ __('site.admin.pesanan_detail.kirim_ulang') }} notifikasi (checklist F8-10). Ditaruh di sebelah
                 cetak karena keduanya dipakai untuk hal yang sama: pembeli
                 mengaku belum menerima apa pun. --}}
            <div class="flex items-center gap-2">
                <select wire:model="kejadianKirimUlang" class="field py-1.5 text-sm" aria-label="{{ __('site.admin.pesanan_detail.jenis_notifikasi') }}">
                    <option value="dibuat">{{ __('site.admin.pesanan_detail.judul') }} diterima</option>
                    <option value="dibayar">{{ __('site.admin.pesanan_detail.pembayaran') }} diterima</option>
                    <option value="dikirim">{{ __('site.admin.pesanan_detail.judul') }} dikirim</option>
                </select>
                <button type="button" class="btn btn-outline"
                        wire:click="kirimUlangNotifikasi"
                        wire:loading.attr="disabled" wire:target="kirimUlangNotifikasi">
                    <x-ikon name="mail" :size="17" />
                    <span wire:loading.remove wire:target="kirimUlangNotifikasi">{{ __('site.admin.pesanan_detail.kirim_ulang') }}</span>
                    <span wire:loading wire:target="kirimUlangNotifikasi">{{ __('site.admin.pesanan_detail.mengirim') }}</span>
                </button>
            </div>
        </div>
    </div>

    @if (session('pesan-admin'))
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ session('pesan-admin') }}</div>
    @endif

    @if (session('galat-admin'))
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ session('galat-admin') }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1.6fr_1fr]">

        {{-- ── Kiri: isi pesanan ───────────────────────────────────── --}}
        <div class="space-y-5">

            {{-- Barang --}}
            <div class="plate">
                <div class="border-b border-line-soft p-4">
                    <p class="part-no">{{ __('site.admin.pesanan_detail.barang_dipesan') }}</p>
                </div>

                <ul class="divide-y divide-line-soft">
                    @foreach ($order->items as $item)
                        <li class="flex items-start gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">{{ $item->product_name }}</p>
                                <p class="tech mt-0.5 text-xs text-dim">
                                    {{ $item->sku }}
                                    @if ($item->variant_name) · {{ $item->variant_name }} @endif
                                    · {{ $item->weight_gram }} g
                                </p>

                                @php $varian = $item->variant; @endphp
                                @if ($varian)
                                    <p class="mt-1 text-xs {{ $varian->stokTersedia() < $item->quantity ? 'text-danger' : 'text-muted' }}">
                                        Stok tersedia sekarang: {{ $varian->stokTersedia() }}
                                        @if ($varian->stokTersedia() < $item->quantity)
                                            — kurang dari jumlah dipesan
                                        @endif
                                    </p>
                                @endif
                            </div>

                            <div class="flex-none text-right">
                                <p class="tech text-sm">{{ $item->quantity }} × {{ rupiah($item->unit_price) }}</p>
                                <p class="tech mt-0.5 font-medium">{{ rupiah($item->subtotal) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <dl class="space-y-1.5 border-t border-line-soft p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted">{{ __('site.admin.pesanan_detail.subtotal') }}</dt>
                        <dd class="tech">{{ rupiah($order->subtotal) }}</dd>
                    </div>

                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between">
                            <dt class="text-muted">
                                Diskon
                                @if ($order->coupon) <span class="tech text-xs">({{ $order->coupon->code }})</span> @endif
                            </dt>
                            <dd class="tech text-ok">−{{ rupiah($order->discount_amount) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <dt class="text-muted">{{ __('site.admin.pesanan_detail.ongkos_kirim') }}</dt>
                        <dd class="tech">{{ rupiah($order->shipping_cost) }}</dd>
                    </div>

                    @if ((float) $order->tax_amount > 0)
                        <div class="flex justify-between">
                            <dt class="text-muted">{{ __('site.admin.pesanan_detail.pajak') }}</dt>
                            <dd class="tech">{{ rupiah($order->tax_amount) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between border-t border-line-soft pt-2 text-base">
                        <dt class="font-semibold">{{ __('site.admin.pesanan_detail.total') }}</dt>
                        <dd class="tech font-semibold">{{ rupiah($order->grand_total) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Perpindahan status --}}
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.pesanan_detail.ubah_status') }}</p>

                @if ($tujuanSah === [])
                    <p class="mt-3 text-sm text-muted">
                        {{ __('site.admin.pesanan_detail.judul') }} ini sudah pada status akhir. Tidak ada perpindahan yang tersedia.
                    </p>
                @else
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach ($tujuanSah as $t)
                            <button type="button"
                                    wire:click="$set('statusTujuan', '{{ $t->value }}')"
                                    class="btn {{ $statusTujuan === $t->value ? 'btn-primary' : 'btn-outline' }}">
                                {{ $t->label() }}
                            </button>
                        @endforeach
                    </div>

                    <label class="mt-3 block">
                        <span class="part-no">{{ __('site.admin.pesanan_detail.catatan_perpindahan') }}</span>
                        <input type="text" wire:model="catatanStatus" class="field mt-1.5"
                               placeholder="{{ __('site.admin.pesanan_detail.contoh_catatan') }}" maxlength="200" />
                    </label>

                    <button type="button" class="btn btn-primary mt-3 w-full sm:w-auto"
                            wire:click="pindahStatus"
                            wire:loading.attr="disabled" wire:target="pindahStatus"
                            @disabled($statusTujuan === '')>
                        <x-ikon name="check" :size="16" />
                        <span wire:loading.remove wire:target="pindahStatus">{{ __('site.admin.pesanan_detail.terapkan_status') }}</span>
                        <span wire:loading wire:target="pindahStatus">{{ __('site.admin.pesanan_detail.memproses') }}</span>
                    </button>

                    <p class="mt-2 text-xs text-dim">
                        Menandai selesai mengurangi stok fisik. Membatalkan melepas stok yang
                        tertahan dan mengembalikannya ke penjualan.
                    </p>
                @endif
            </div>

            {{-- {{ __('site.admin.pesanan_detail.riwayat') }} status --}}
            <div class="plate">
                <div class="border-b border-line-soft p-4">
                    <p class="part-no">{{ __('site.admin.pesanan_detail.riwayat') }}</p>
                </div>

                <ol class="divide-y divide-line-soft">
                    @forelse ($order->statusHistory->sortByDesc('created_at') as $h)
                        <li class="flex items-start gap-3 p-4 text-sm">
                            <span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-accent" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p>
                                    <span class="text-muted">{{ $h->from_status ?: 'awal' }}</span>
                                    <span class="text-dim"> → </span>
                                    <span class="font-medium">{{ $h->to_status }}</span>
                                </p>
                                @if ($h->note)
                                    <p class="mt-0.5 text-xs text-muted">{{ $h->note }}</p>
                                @endif
                                <p class="tech mt-0.5 text-xs text-dim">
                                    {{ $h->created_at->format('d M Y H:i') }}
                                    @if ($h->changed_by) · oleh #{{ $h->changed_by }} @endif
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="p-4 text-sm text-muted">{{ __('site.admin.pesanan_detail.belum_ada_perpindahan') }}</li>
                    @endforelse
                </ol>
            </div>
        </div>

        {{-- ── Kanan: pengiriman, pembeli, catatan ─────────────────── --}}
        <div class="space-y-5">

            {{-- {{ __('site.admin.pesanan_detail.pengiriman') }} --}}
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.pesanan_detail.pengiriman') }}</p>

                <div class="mt-3 space-y-3">
                    <label class="block">
                        <span class="text-xs text-muted">{{ __('site.admin.pesanan_detail.kurir') }}</span>
                        <input type="text" wire:model="kurir" class="field mt-1" placeholder="JNE, J&T, SiCepat" maxlength="60" />
                        @error('kurir') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">{{ __('site.admin.pesanan_detail.layanan') }}</span>
                        <input type="text" wire:model="layanan" class="field mt-1" placeholder="REG, YES" maxlength="60" />
                        @error('layanan') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">{{ __('site.admin.pesanan_detail.nomor_resi') }}</span>
                        <input type="text" wire:model="nomorResi" class="field tech mt-1" placeholder="{{ __('site.admin.pesanan_detail.nomor_lacak') }}" maxlength="60" />
                        @error('nomorResi') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">{{ __('site.admin.pesanan_detail.catatan_admin') }}</span>
                        <textarea wire:model="catatanAdmin" rows="2" class="field mt-1"
                                  placeholder="{{ __('site.admin.pesanan_detail.catatan_internal') }}" maxlength="2000"></textarea>
                        @error('catatanAdmin') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <button type="button" class="btn btn-outline w-full"
                            wire:click="simpan{{ __('site.admin.pesanan_detail.pengiriman') }}"
                            wire:loading.attr="disabled" wire:target="simpan{{ __('site.admin.pesanan_detail.pengiriman') }}">
                        <x-ikon name="check" :size="16" />
                        <span wire:loading.remove wire:target="simpan{{ __('site.admin.pesanan_detail.pengiriman') }}">{{ __('site.admin.pesanan_detail.simpan_pengiriman') }}</span>
                        <span wire:loading wire:target="simpan{{ __('site.admin.pesanan_detail.pengiriman') }}">{{ __('site.admin.pesanan_detail.menyimpan') }}</span>
                    </button>
                </div>
            </div>

            {{-- {{ __('site.admin.pesanan_detail.pembeli') }} --}}
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.pesanan_detail.pembeli') }}</p>

                <p class="mt-2 font-medium">{{ $order->namaPenerima() }}</p>

                <div class="mt-2 space-y-1 text-sm text-muted">
                    @if ($order->teleponPenerima())
                        <p class="tech">{{ $order->teleponPenerima() }}</p>
                    @endif
                    @if ($order->emailPenerima())
                        <p class="truncate">{{ $order->emailPenerima() }}</p>
                    @endif
                    @if ($order->user === null)
                        <p class="badge badge-neutral mt-1">{{ __('site.admin.pesanan_detail.checkout_tamu') }}</p>
                    @endif
                </div>
            </div>

            {{-- {{ __('site.admin.pesanan_detail.alamat_kirim') }} --}}
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.pesanan_detail.alamat_kirim') }}</p>

                @if (blank($alamat['address_line_1'] ?? null))
                    <p class="mt-2 text-sm text-muted">{{ __('site.admin.pesanan_detail.alamat_tidak_ada') }}</p>
                @else
                    <address class="mt-2 text-sm not-italic leading-relaxed text-muted">
                        {{ $alamat['recipient_name'] }}<br>
                        {{ $alamat['address_line_1'] }}<br>
                        @if (filled($alamat['address_line_2'] ?? null))
                            {{ $alamat['address_line_2'] }}<br>
                        @endif
                        {{ $alamat['city'] }}, {{ $alamat['state_province'] }} {{ $alamat['postal_code'] }}<br>
                        {{ $alamat['phone_number'] }}
                    </address>
                @endif
            </div>

            {{-- {{ __('site.admin.pesanan_detail.pembayaran') }} --}}
            <div class="plate p-4">
                <p class="part-no">{{ __('site.admin.pesanan_detail.pembayaran') }}</p>

                <p class="mt-2 text-sm">
                    {{ __('site.admin.pesanan_detail.metode') }}
                    <span class="font-medium">{{ $order->payment_method?->label() ?? 'belum dipilih' }}</span>
                </p>

                @forelse ($order->payments as $bayar)
                    <div class="mt-2 border-t border-line-soft pt-2 text-xs">
                        <p class="tech">{{ rupiah($bayar->amount) }}</p>
                        {{-- Tabel payments tidak punya kolom status sendiri;
                             yang ada provider_status dari PayPal dan verified_at
                             yang diisi admin saat memverifikasi transfer. --}}
                        <p class="text-muted">
                            {{ $bayar->provider }}
                            @if ($bayar->provider_status) · {{ $bayar->provider_status }} @endif
                            @if ($bayar->verified_at)
                                · diverifikasi {{ $bayar->verified_at->format('d M Y') }}
                            @else
                                · belum diverifikasi
                            @endif
                        </p>
                        @if ($bayar->provider_transaction_id)
                            <p class="tech truncate text-dim">{{ $bayar->provider_transaction_id }}</p>
                        @endif

                        {{-- Bukti transfer: hanya mungkin diambil lewat URL
                             bertanda tangan 15 menit. Berkasnya di disk privat
                             karena memuat nama pengirim dan nomor rekening. --}}
                        @if ($bayar->proof_path)
                            <a href="{{ app(\App\Services\{{ __('site.admin.pesanan_detail.pembayaran') }}\{{ __('site.admin.pesanan_detail.pembayaran') }}Manual::class)->urlBukti($bayar) }}"
                               target="_blank" rel="noopener"
                               class="mt-1 inline-flex items-center gap-1 text-accent hover:underline">
                                <x-ikon name="eye" :size="12" />
                                {{ __('site.admin.pesanan_detail.lihat_bukti') }}
                            </a>
                        @endif

                        @if ($bayar->payload_json['alasan_penolakan'] ?? null)
                            <p class="mt-1 text-danger">
                                Ditolak: {{ $bayar->payload_json['alasan_penolakan'] }}
                            </p>
                        @endif

                        {{-- Tindakan hanya muncul untuk bukti yang masih menunggu. --}}
                        {{-- provider ber-cast ke enum Metode{{ __('site.admin.pesanan_detail.pembayaran') }}, sehingga
                             perbandingan dengan string selalu salah dan tombol
                             verifikasi tidak pernah muncul. --}}
                        @if ($bayar->provider === \App\Enums\Metode{{ __('site.admin.pesanan_detail.pembayaran') }}::Manual && ! $bayar->terverifikasi() && $bayar->provider_status !== 'ditolak')
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="verifikasi{{ __('site.admin.pesanan_detail.pembayaran') }}({{ $bayar->id }})"
                                        wire:confirm="Verifikasi pembayaran ini dan tandai pesanan lunas?"
                                        class="btn btn-primary btn-sm">
                                    <x-ikon name="check" :size="13" />
                                    {{ __('site.admin.pesanan_detail.verifikasi_lunas') }}
                                </button>

                                <button type="button" wire:click="tolak{{ __('site.admin.pesanan_detail.pembayaran') }}({{ $bayar->id }})"
                                        class="btn btn-outline btn-sm">
                                    {{ __('site.admin.pesanan_detail.tolak') }}
                                </button>
                            </div>

                            <div class="mt-2">
                                <input type="text" wire:model="alasan{{ __('site.admin.pesanan_detail.tolak') }}" class="field py-1.5 text-[length:var(--t-xs)]"
                                       placeholder="{{ __('site.admin.pesanan_detail.alasan_penolakan') }}"
                                       aria-label="{{ __('site.admin.pesanan_detail.alasan_penolakan_bukti') }}">
                                @error('alasan{{ __('site.admin.pesanan_detail.tolak') }}') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="mt-2 text-sm text-muted">{{ __('site.admin.pesanan_detail.belum_ada_catatan_bayar') }}</p>
                @endforelse

                @error('pembayaran')
                    <p class="field-error mt-2">{{ $message }}</p>
                @enderror

                {{-- {{ __('site.admin.pesanan_detail.tautan_pembeli') }} mengunggah bukti, ditampilkan supaya
                     admin dapat mengirimkannya ulang bila pembeli kehilangan
                     halaman pesanannya. --}}
                @if ($order->payment_method?->value === 'manual' && $order->payment_status->value !== 'paid')
                    <div class="mt-3 border-t border-line-soft pt-2">
                        <p class="part-no">{{ __('site.admin.pesanan_detail.tautan_pembeli') }}</p>
                        <p class="mt-1 break-all text-xs text-dim">
                            {{ route('front.payment.upload', ['ulid' => $order->ulid]) }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- {{ __('site.admin.pesanan_detail.catatan_pembeli') }} --}}
            @if (filled($order->customer_note))
                <div class="plate p-4">
                    <p class="part-no">{{ __('site.admin.pesanan_detail.catatan_pembeli') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ $order->customer_note }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
