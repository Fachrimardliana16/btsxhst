<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Produk\PengunggahMediaProduk;
use App\Support\CacheKatalog;

/**
 * Tab 3 — media produk: unggah, urutan, gambar utama, dan teks alt
 * (checklist F3-05).
 *
 * DIREKAT dari ProdukForm tanpa mengubah logikanya — hanya dipindahkan supaya
 * satu berkas tidak memuat tiga tanggung jawab sekaligus. Trait ini TIDAK
 * berdiri sendiri: ia mengisi properti dan method pada komponen induk, dan
 * mengandalkan properti publik milik induk ($produkId, $pesan, $pesanTab, dst).
 */
trait MengelolaMedia
{
    // ── Tab 3: media ────────────────────────────────────────────────

    public function diperbaruiBerkasBaru(): void
    {
        $this->pesanTab = null;
        $this->ditolak = [];
        $this->sedangUnggah = false;
    }

    public function unggah(): void
    {
        $pengunggah = app(PengunggahMediaProduk::class);

        if ($this->produkId === null) {
            $this->addError('berkasBaru', 'Simpan informasi dasar dulu sebelum mengunggah gambar.');

            return;
        }

        if ($this->berkasBaru === []) {
            return;
        }

        $this->validate([
            'berkasBaru' => ['array', 'max:20'],
            'berkasBaru.*' => ['file', 'max:5120'],
        ], [
            'berkasBaru.max' => 'Maksimum 20 berkas sekaligus. Ulangi untuk sisanya.',
            'berkasBaru.*.max' => 'Setiap berkas maksimum 5 MB.',
            'berkasBaru.*.file' => 'Berkas tidak dapat dibaca.',
        ]);

        $produk = Product::query()->findOrFail($this->produkId);

        $hasil = $pengunggah->unggahBanyak($produk, $this->berkasBaru);

        $this->berkasBaru = [];
        $this->ditolak = $hasil['ditolak'];

        $this->pesan = $hasil['antre'].' berkas masuk antrean pemrosesan.';
        $this->pesanTab = 'media';

        // Berkas selesai diproses oleh pekerja antrean, bukan di sini. Muat
        // ulang daftar supaya yang sudah selesai langsung tampak.
        $this->muatUlangUrutanMedia();
    }

    public function hapusMedia(int $id): void
    {
        $pengunggah = app(PengunggahMediaProduk::class);

        $media = ProductMedia::query()->where('product_id', $this->produkId)->findOrFail($id);

        // Gambar yang sudah dipakai di baris pesanan tetap boleh dihapus —
        // pesanan menyimpan jalur gambarnya sendiri saat dicetak. Yang dicegah
        // adalah menghapus gambar utama tanpa menunjuk penggantinya.
        $pengunggah->hapusBerkas($media);
        $media->delete();

        $this->rapikanMedia();
        $this->muatUlangUrutanMedia();

        $this->pesan = 'Gambar dihapus.';
        $this->pesanTab = 'media';

        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'media dihapus');
    }

    public function jadikanUtama(int $id): void
    {
        DB::transaction(function () use ($id): void {
            ProductMedia::query()->where('product_id', $this->produkId)->update(['is_primary' => false]);

            ProductMedia::query()
                ->where('product_id', $this->produkId)
                ->whereKey($id)
                ->update(['is_primary' => true]);
        });

        $this->pesan = 'Gambar utama diperbarui.';
        $this->pesanTab = 'media';

        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'gambar utama berubah');
    }

    public function geserMedia(int $id, string $arah): void
    {
        $urutan = $this->urutanMedia;
        $posisi = array_search($id, $urutan, true);

        if ($posisi === false) {
            return;
        }

        $tujuan = $arah === 'naik' ? $posisi - 1 : $posisi + 1;

        if ($tujuan < 0 || $tujuan >= count($urutan)) {
            return;
        }

        [$urutan[$posisi], $urutan[$tujuan]] = [$urutan[$tujuan], $urutan[$posisi]];
        $this->urutanMedia = $urutan;

        $this->simpanUrutanMedia();
    }

    /** Dipanggil dari sisi klien setelah pengguna selesai menyeret gambar. */
    public function simpanUrutanDariKlien(array $urutan): void
    {
        $sah = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->pluck('id')
            ->all();

        $bersih = array_values(array_filter(
            array_map('intval', $urutan),
            fn (int $id) => in_array($id, $sah, true),
        ));

        if ($bersih === []) {
            return;
        }

        $this->urutanMedia = $bersih;
        $this->simpanUrutanMedia();

        $this->pesan = 'Urutan gambar disimpan.';
        $this->pesanTab = 'media';
    }

    private function simpanUrutanMedia(): void
    {
        DB::transaction(function (): void {
            foreach ($this->urutanMedia as $nomor => $id) {
                ProductMedia::query()
                    ->where('product_id', $this->produkId)
                    ->whereKey($id)
                    ->update(['display_order' => $nomor + 1]);
            }
        });
    }

    public function simpanAlt(int $id, string $teks, string $bahasa): void
    {
        $kolom = $bahasa === 'en' ? 'alt_text_en' : 'alt_text_id';

        ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->whereKey($id)
            ->update([$kolom => $teks === '' ? null : $teks]);

        $this->pesan = 'Teks alternatif disimpan.';
        $this->pesanTab = 'media';
    }

    /** Pastikan selalu ada tepat satu gambar utama selama ada gambar. */
    private function rapikanMedia(): void
    {
        if ($this->produkId === null) {
            return;
        }

        $adaUtama = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->where('is_primary', true)
            ->exists();

        if ($adaUtama) {
            return;
        }

        $pertama = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->orderBy('display_order')
            ->first();

        $pertama?->update(['is_primary' => true]);
    }

    private function muatUlangUrutanMedia(): void
    {
        if ($this->produkId === null) {
            return;
        }

        $this->rapikanMedia();

        $this->urutanMedia = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->orderBy('display_order')
            ->pluck('id')
            ->all();
    }
}
