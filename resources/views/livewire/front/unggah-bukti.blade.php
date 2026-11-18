{{-- Transfer receipt upload page.

     One page, three states: unpaid (show account + form), awaiting verification
     (show status), and paid (no form). Showing the form in a state that will
     certainly be rejected only makes the buyer upload a file that gets refused. --}}

<div class="container-page py-8">

    <div class="mx-auto max-w-2xl space-y-5">

        <div>
            <p class="part-no">{{ __('site.proof.eyebrow') }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">
                {{ __('site.proof.title') }}
            </h1>
            <p class="mt-1 text-sm text-muted">
                {{ __('site.order.number') }} <span class="tech">{{ $pesanan->order_number }}</span> ·
                {{ dolar($pesanan->grand_total) }}
            </p>
        </div>

        @if ($pesan)
            <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
        @endif

        @if ($galat)
            <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $galat }}</div>
        @endif

        {{-- State: already paid --}}
        @if ($sudahLunas)
            <div class="plate p-5 text-center">
                <x-ikon name="check" :size="32" class="mx-auto text-ok" />
                <p class="mt-3 font-display text-lg font-semibold">{{ __('site.proof.paid') }}</p>
                <p class="mt-1 text-sm text-muted">
                    {{ __('site.proof.paid_body') }}
                </p>
                <a href="{{ route('front.order.track', ['kode' => $pesanan->order_number]) }}"
                   class="btn btn-primary mt-4">{{ __('site.proof.track') }}</a>
            </div>

        {{-- State: awaiting verification --}}
        @elseif ($menunggu)
            <div class="plate border-l-2 border-warn p-4">
                <p class="font-medium">{{ __('site.proof.received') }}</p>
                <p class="mt-1 text-sm text-muted">
                    {{ __('site.proof.received_body') }}
                </p>
            </div>

            <div class="plate p-4">
                <p class="part-no">{{ __('site.proof.uploaded') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($pembayaran as $p)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-muted">
                                {{ $p->created_at?->translatedFormat('d M Y, H:i') }}
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs
                                         {{ $p->provider_status === 'terverifikasi' ? 'bg-ok/12 text-ok' : 'bg-warn/12 text-warn' }}">
                                {{ $p->provider_status === 'terverifikasi' ? __('site.proof.status_verified') : __('site.proof.status_pending') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="plate p-4 text-sm">
                <p class="text-muted">
                    {{ __('site.proof.reupload_hint') }}
                </p>
            </div>
        @endif

        {{-- State: unpaid --}}
        @unless ($sudahLunas)
            <div class="plate p-5">
                <p class="part-no">{{ __('site.proof.transfer_to') }}</p>

                @if ($rekeningLengkap)
                    <div class="mt-3 space-y-3">
                        @foreach ($rekening as $r)
                            <div class="space-y-1">
                                <p class="font-display text-lg font-semibold">{{ $r['bank'] }}</p>
                                <p class="tech text-xl">{{ $r['nomor'] }}</p>
                                <p class="text-sm text-muted">{{ __('site.proof.account_name', ['nama' => $r['atas_nama']]) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-lg bg-sunken p-3 text-sm">
                        <p class="flex items-center justify-between">
                            <span class="text-muted">{{ __('site.proof.amount') }}</span>
                            <span class="tech font-medium">{{ dolar($pesanan->grand_total) }}</span>
                        </p>
                        <p class="mt-2 text-xs text-dim">
                            {{ __('site.proof.amount_hint') }}
                        </p>
                    </div>
                @else
                    <div class="mt-3 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                        <p class="font-medium text-danger">{{ __('site.proof.no_account') }}</p>
                        <p class="mt-1 text-muted">
                            {{ __('site.proof.no_account_body') }}
                        </p>
                        <a href="{{ route('front.contact') }}" class="btn btn-outline btn-sm mt-3">{{ __('site.proof.contact') }}</a>
                    </div>
                @endif
            </div>

            <form wire:submit="unggah" class="plate p-5">
                <p class="font-display text-lg font-semibold">{{ __('site.proof.upload_title') }}</p>
                <p class="mt-1 text-sm text-muted">
                    {{ __('site.proof.upload_hint') }}
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="bukti" class="part-no">{{ __('site.proof.file_label') }} <span class="text-danger">*</span></label>
                        <x-file-upload model="bukti" id="bukti"
                                       accept="image/jpeg,image/png,image/webp,application/pdf"
                                       title="{{ __('site.proof.pick_file') }}"
                                       hint="{{ __('site.proof.file_hint') }}"
                                       loading="{{ __('site.proof.uploading') }}"
                                       class="mt-1" />
                        @error('bukti') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="catatan" class="part-no">{{ __('site.proof.note_label') }}</label>
                        <textarea id="catatan" wire:model="catatan" class="field mt-1" rows="2"
                                  placeholder="{{ __('site.proof.note_placeholder') }}"></textarea>
                        @error('catatan') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-4 w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="unggah">
                        <x-ikon name="upload" :size="16" class="inline" />
                        {{ __('site.proof.submit') }}
                    </span>
                    <span wire:loading wire:target="unggah">{{ __('site.proof.submitting') }}</span>
                </button>
            </form>

            <p class="text-center text-xs text-dim">
                {{ __('site.proof.help') }}
                <a href="{{ route('front.contact') }}" class="text-accent hover:underline">{{ __('site.proof.contact') }}</a>
                ·
                <a href="{{ route('front.order.track', ['kode' => $pesanan->order_number]) }}"
                   class="text-accent hover:underline">{{ __('site.proof.help_track') }}</a>
            </p>
        @endunless
    </div>
</div>
