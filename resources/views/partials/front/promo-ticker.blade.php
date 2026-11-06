{{--
    Bar promo di bawah navbar.

    Isinya satu kalimat dari bahasa aplikasi (`site.footer.promo`), jadi teks
    kampanye bisa diganti tanpa menyentuh template — cukup ubah kunci terjemahan
    atau, nanti, kunci Pengaturan bila admin perlu mengubahnya sendiri.

    Gerak: satu kali berjalan dari kanan ke kiri, lalu diam. Ticker yang terus
    berputar memaksa mata mengikuti teks yang tidak penting dan membuat halaman
    terasa gelisah; setelah kalimatnya terbaca, tugasnya selesai.

    Dibuat sebagai partial terpisah supaya navbar tidak makin panjang, dan
    supaya bisa dicabut hanya dengan menghapus satu `@include`.
--}}
<div class="promo-ticker border-b border-line-soft bg-[var(--c-promo)] text-[var(--c-promo-fg)]"
     role="status" aria-live="off">
    <div class="container-page flex items-center gap-2 overflow-hidden py-1.5">
        <span class="promo-ticker__ikon flex-none" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>
            </svg>
        </span>

        <p class="promo-ticker__teks truncate text-[length:var(--t-xs)] font-semibold uppercase tracking-[0.08em] sm:text-xs">
            {{ __('site.footer.promo') }}
        </p>
    </div>
</div>
