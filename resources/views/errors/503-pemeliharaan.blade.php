{{-- Halaman pemeliharaan dengan pesan dari panel.

     Berdiri sendiri, tidak memakai layout front: layout itu memuat navbar,
     keranjang, dan pemanggilan Livewire yang justru ingin dihindari selama
     situs ditutup. Yang dipakai hanya aset build (CSS) supaya tetap bergaya.

     Tanpa JavaScript: halaman ini harus tampil benar walau skrip gagal
     dimuat — dan selama pemeliharaan, itu lebih mungkin terjadi. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Sedang pemeliharaan · {{ \App\Support\Pengaturan::instance()->teks('site_name', 'Bites Exhaust') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-bg text-ink antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="plate w-full max-w-md p-8 text-center" role="status" aria-live="polite">
            <span class="part-no">503</span>

            <h1 class="mt-3 font-display font-semibold leading-tight" style="font-size: var(--t-h3)">
                Sedang pemeliharaan
            </h1>

            <p class="mt-3 text-sm leading-relaxed text-muted">{{ $pesan }}</p>

            <div class="measure-line my-6"></div>

            <p class="text-xs text-dim">
                {{ \App\Support\Pengaturan::instance()->teks('site_name', 'Bites Exhaust') }}
                @php $telp = \App\Support\Pengaturan::instance()->teks('phone_number', ''); @endphp
                @if (filled($telp)) · {{ $telp }} @endif
            </p>
        </div>
    </main>
</body>
</html>
