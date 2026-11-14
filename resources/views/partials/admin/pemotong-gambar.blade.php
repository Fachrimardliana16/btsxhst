{{-- Pemotong gambar sebelum unggah.

     Berkas yang dipilih lewat input ber-atribut `data-crop` ditahan lebih dulu
     di peramban, ditampilkan di sini, lalu dipotong ke rasio yang dipakai
     halaman tujuan. Tanpa langkah ini foto potret masuk apa adanya dan
     `object-cover` memotongnya sendiri — hasilnya bagian penting hilang dan
     terlihat ruang kosong di kartu produk.

     Seluruh kendali ada di resources/js/app.js; berkas ini hanya kerangka.
     Tidak ada skrip sebaris supaya tetap lolos CSP. --}}

<div id="pemotong" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-3 sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="pemotong-judul">
    <div class="plate w-full max-w-xl space-y-3 p-4">
        <div class="flex items-center justify-between gap-3">
            <p id="pemotong-judul" class="font-display text-lg font-semibold">Crop image</p>
            <span id="pemotong-hitung" class="tech text-xs text-dim"></span>
        </div>

        <p id="pemotong-hint" class="text-xs text-muted">
            Drag to reposition and use the slider to zoom. The frame is the exact shape buyers will see.
        </p>

        <div id="pemotong-bingkai"
             class="relative mx-auto w-full overflow-hidden rounded-[var(--r-kecil)] bg-black touch-none select-none"
             style="aspect-ratio: 4 / 3; max-height: 55vh;">
            <canvas id="pemotong-kanvas" class="block h-full w-full cursor-grab active:cursor-grabbing"></canvas>
        </div>

        <label class="flex items-center gap-3 text-sm">
            <span class="part-no flex-none">Zoom</span>
            <input id="pemotong-zoom" type="range" min="1" max="3" step="0.01" value="1" class="w-full">
        </label>

        <div class="flex justify-end gap-2 pt-1">
            <button type="button" id="pemotong-batal" class="btn btn-outline">Cancel</button>
            <button type="button" id="pemotong-pakai" class="btn btn-primary">Crop &amp; use</button>
        </div>
    </div>
</div>
