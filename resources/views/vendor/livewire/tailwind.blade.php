@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';

    // Salinan tema "tailwind" milik Livewire, dengan tiga perbaikan yang
    // ditemukan lewat audit Lighthouse (desktop, halaman /produk dan /kategori):
    //
    //   1. `aria-prohibited-attr` — versi vendor menaruh `aria-label` pada
    //      <span aria-disabled="true"> yang tidak punya role sah. Di sini
    //      tombol mati tetap <button disabled>, jadi tetap punya nama aksesibel
    //      tanpa melanggar aturan ARIA, dan tidak masuk urutan fokus.
    //   2. `color-contrast` — panah "sebelumnya" memakai text-gray-500
    //      (#6b7280) di atas putih = 4.83:1 (lolos), tetapi keadaan aktif
    //      memakai #6b7280 di atas #f7f8fa = 4.27:1 (gagal). Sekarang memakai
    //      token situs (--c-text-muted) yang lolos di dua latar.
    //   3. varian `dark:` dibuang. Situs ini hanya punya tema terang; varian
    //      gelap bawaan vendor menyala kalau OS pembeli memakai mode gelap dan
    //      menghasilkan teks abu di atas latar putih.
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
            {{-- Tampilan ponsel: tombol teks penuh. --}}
            <div class="flex justify-between flex-1 sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <button type="button" disabled
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md cursor-not-allowed text-[color:var(--c-text-muted)] bg-surface border border-line">
                            {!! __('pagination.previous') !!}
                        </button>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-[color:var(--c-text)] bg-surface border border-line hover:text-[color:var(--c-text-muted)] focus:outline-none focus:ring focus:border-[color:var(--c-accent)] transition ease-in-out duration-150">
                            {!! __('pagination.previous') !!}
                        </button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled"
                                class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium rounded-md text-[color:var(--c-text)] bg-surface border border-line hover:text-[color:var(--c-text-muted)] focus:outline-none focus:ring focus:border-[color:var(--c-accent)] transition ease-in-out duration-150">
                            {!! __('pagination.next') !!}
                        </button>
                    @else
                        <button type="button" disabled
                                class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium rounded-md cursor-not-allowed text-[color:var(--c-text-muted)] bg-surface border border-line">
                            {!! __('pagination.next') !!}
                        </button>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm leading-5 text-[color:var(--c-text)]">
                        <span>{!! __('Showing') !!}</span>
                        <span class="font-medium">{{ $paginator->firstItem() }}</span>
                        <span>{!! __('to') !!}</span>
                        <span class="font-medium">{{ $paginator->lastItem() }}</span>
                        <span>{!! __('of') !!}</span>
                        <span class="font-medium">{{ $paginator->total() }}</span>
                        <span>{!! __('results') !!}</span>
                    </p>
                </div>

                <div>
                    <span class="relative z-0 inline-flex rtl:flex-row-reverse rounded-md shadow-sm">
                        <span>
                            {{-- Previous Page Link --}}
                            @if ($paginator->onFirstPage())
                                <button type="button" disabled aria-label="{{ __('pagination.previous') }}"
                                        class="relative inline-flex items-center px-2 py-2 text-sm font-medium cursor-not-allowed rounded-l-md leading-5 text-[color:var(--c-text-muted)] bg-surface border border-line">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @else
                                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                        class="relative inline-flex items-center px-2 py-2 text-sm font-medium rounded-l-md leading-5 text-[color:var(--c-text-muted)] bg-surface border border-line hover:text-[color:var(--c-text)] focus:z-10 focus:outline-none focus:border-[color:var(--c-accent)] focus:ring active:bg-[color:var(--c-surface-sunken)] transition ease-in-out duration-150"
                                        aria-label="{{ __('pagination.previous') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>

                        {{-- Pagination Elements --}}
                        @foreach ($elements as $element)
                            {{-- "Three Dots" Separator --}}
                            @if (is_string($element))
                                <span aria-disabled="true">
                                    <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium cursor-default leading-5 text-[color:var(--c-text)] bg-surface border border-line">{{ $element }}</span>
                                </span>
                            @endif

                            {{-- Array Of Links --}}
                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page">
                                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium cursor-default leading-5 text-[color:var(--c-text-muted)] bg-surface border border-line">{{ $page }}</span>
                                            </span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                                    class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium leading-5 text-[color:var(--c-text)] bg-surface border border-line hover:text-[color:var(--c-text-muted)] focus:z-10 focus:outline-none focus:border-[color:var(--c-accent)] focus:ring active:bg-[color:var(--c-surface-sunken)] transition ease-in-out duration-150"
                                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        <span>
                            {{-- Next Page Link --}}
                            @if ($paginator->hasMorePages())
                                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                        class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium rounded-r-md leading-5 text-[color:var(--c-text-muted)] bg-surface border border-line hover:text-[color:var(--c-text)] focus:z-10 focus:outline-none focus:border-[color:var(--c-accent)] focus:ring active:bg-[color:var(--c-surface-sunken)] transition ease-in-out duration-150"
                                        aria-label="{{ __('pagination.next') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @else
                                <button type="button" disabled aria-label="{{ __('pagination.next') }}"
                                        class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium cursor-not-allowed rounded-r-md leading-5 text-[color:var(--c-text-muted)] bg-surface border border-line">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
