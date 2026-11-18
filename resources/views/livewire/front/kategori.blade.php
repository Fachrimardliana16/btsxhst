<div>
    <div class="container-page py-8 lg:py-12">

        {{-- Breadcrumb: also the path for screen readers, not decoration. --}}
        <nav class="part-no mb-6" aria-label="Breadcrumb">
            <a href="{{ route('front.home') }}" wire:navigate class="hover:text-accent">{{ __('site.nav.home') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('front.products.index') }}" wire:navigate class="hover:text-accent">{{ __('site.nav.products') }}</a>
            <span class="mx-2">/</span>
            <span aria-current="page">{{ $kategori->nama() }}</span>
        </nav>

        {{-- Category header.
             The H1 here is the only <h1> on the page; previously product titles
             in the cards used h3, leaving the category page with no main heading
             at all and forcing search engines to guess the topic. --}}
        <header class="max-w-3xl">
            <h1 class="text-2xl font-semibold leading-tight lg:text-4xl">{{ $kategori->nama() }}</h1>

            <p class="mt-3 text-sm leading-relaxed text-muted lg:text-base">{{ $deskripsi }}</p>

            <p class="part-no mt-3">
                {{ __('site.catalog.result_count', ['count' => $jumlah]) }}
            </p>
        </header>

        <div class="mt-8 flex flex-wrap items-center gap-3">
            <label class="part-no" for="urut">{{ __('site.catalog.sort') }}</label>

            <select id="urut" class="field !w-auto !min-h-[44px]" wire:model.live="urut">
                <option value="terbaru">{{ __('site.catalog.sort_newest') }}</option>
                <option value="termurah">{{ __('site.catalog.sort_price_asc') }}</option>
                <option value="termahal">{{ __('site.catalog.sort_price_desc') }}</option>
                <option value="populer">{{ __('site.catalog.sort_popular') }}</option>
            </select>

            <a href="{{ route('front.products.index') }}" wire:navigate
               class="ml-auto text-sm text-muted underline underline-offset-4 hover:text-accent">
                {{ __('site.catalog.view_all') }}
            </a>
        </div>

        @if ($produk->isEmpty())
            <div class="plate mt-8 p-8 text-center">
                <p class="text-muted">{{ __('site.catalog.empty_category', ['kategori' => $kategori->nama()]) }}</p>
                <a href="{{ route('front.products.index') }}" wire:navigate class="btn btn-outline mt-4 inline-flex">
                    {{ __('site.catalog.title') }}
                </a>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3 lg:gap-6">
                @foreach ($produk as $p)
                    @include('components.kartu-produk', ['produk' => $p])
                @endforeach
            </div>

            <div class="mt-8">
                {{ $produk->links() }}
            </div>
        @endif
    </div>
</div>
