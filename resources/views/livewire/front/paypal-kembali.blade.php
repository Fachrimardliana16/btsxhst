{{--
    The PayPal return page.

    Three states, and only three. The buyer must not see a blank screen or a
    technical message: all they need is "it worked" or "what is my order
    number so I can contact the store".
--}}
<div class="container-page py-10 lg:py-20">
    <div class="mx-auto max-w-lg">

        @if ($keadaan === 'memproses')
            <div class="plate p-6 text-center" role="status" aria-live="polite">
                <p class="part-no">{{ __('site.paypal.pending_eyebrow') }}</p>
                <h1 class="mt-2 font-display" style="font-size: var(--t-h3)">{{ __('site.paypal.pending_title') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('site.paypal.pending_body') }}</p>
            </div>
        @endif

        @if ($keadaan === 'lunas')
            <div class="plate p-6" role="status" aria-live="polite">
                <span class="inline-flex items-center gap-2 rounded-full bg-accent/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-accent">
                    <x-ikon name="box" :size="14" /> {{ __('site.paypal.paid_badge') }}
                </span>

                <h1 class="mt-3 font-display" style="font-size: var(--t-h3)">{{ __('site.paypal.paid_title') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ $pesan }}</p>

                @if ($pesanan)
                    <div class="measure-line my-4"></div>

                    <dl class="flex items-baseline justify-between text-sm">
                        <dt class="text-muted">{{ __('site.paypal.order_number') }}</dt>
                        <dd class="tech">{{ $pesanan->order_number }}</dd>
                    </dl>
                    <dl class="mt-2 flex items-baseline justify-between text-sm">
                        <dt class="text-muted">{{ __('site.paypal.total_paid') }}</dt>
                        <dd class="tech">{{ dolar($pesanan->grand_total) }}</dd>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <a href="{{ route('front.order.success', $pesanan) }}" wire:navigate class="btn btn-primary">
                            {{ __('site.paypal.view_order') }}
                        </a>
                        <a href="{{ route('front.products.index') }}" wire:navigate class="btn btn-outline">
                            {{ __('site.paypal.keep_shopping') }}
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @if ($keadaan === 'gagal')
            <div class="plate p-6" role="alert">
                <h1 class="font-display" style="font-size: var(--t-h3)">{{ __('site.paypal.failed_title') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ $pesan }}</p>

                @if ($pesanan)
                    <div class="measure-line my-4"></div>
                    <dl class="flex items-baseline justify-between text-sm">
                        <dt class="text-muted">{{ __('site.paypal.order_number') }}</dt>
                        <dd class="tech">{{ $pesanan->order_number }}</dd>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-2">
                        {{-- A clear second chance: the order already exists, only
                             the payment failed. Creating a new order would double
                             the stock reservation. --}}
                        <a href="{{ route('front.paypal.mulai', $pesanan) }}" class="btn btn-primary">
                            {{ __('site.paypal.retry') }}
                        </a>
                        <a href="{{ route('front.contact') }}" wire:navigate class="btn btn-outline">
                            {{ __('site.paypal.contact') }}
                        </a>
                    </div>
                @endif
            </div>
        @endif

    </div>
</div>
