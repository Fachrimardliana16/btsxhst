<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CacheKatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tab 2 — varian produk (checklist F3-04). Seluruh varian disimpan dalam
 * satu transaksi bersama produk, sehingga tidak mungkin ada produk dengan
 * sebagian varian tersimpan.
 *
 * DIREKAT dari ProdukForm tanpa mengubah logikanya — hanya dipindahkan supaya
 * satu berkas tidak memuat tiga tanggung jawab sekaligus. Trait ini TIDAK
 * berdiri sendiri: ia mengisi properti dan method pada komponen induk, dan
 * mengandalkan properti publik milik induk ($produkId, $pesan, $pesanTab, dst).
 */
trait MengelolaVarian
{
    // ── Tab 2: varian ───────────────────────────────────────────────

    public function tambahVarian(): void
    {
        $this->varian[] = $this->barisVarian();
        $this->pesanTab = null;
    }

    public function salinVarian(int $indeks): void
    {
        $sumber = $this->varian[$indeks] ?? null;

        if ($sumber === null) {
            return;
        }

        $this->varian[$indeks + 1] = array_merge($sumber, [
            'id' => null,
            'nama_id' => $sumber['nama_id'].' (salinan)',
            // Stok TIDAK disalin. Dua varian dengan stok yang sama akan
            // menjanjikan barang yang cuma ada satu.
            'stok' => 0,
            'ditahan' => 0,
        ]);

        $this->varian = array_values($this->varian);
        $this->pesanTab = null;
    }

    public function hapusVarian(int $indeks): void
    {
        $baris = $this->varian[$indeks] ?? null;

        if ($baris === null) {
            return;
        }

        // Varian yang sudah dipesan tidak boleh dihapus: baris pesanan lama
        // menunjuk ke sana, dan menghapusnya membuat riwayat pesanan kehilangan
        // keterangan barang apa yang dibeli.
        if ($baris['id'] !== null) {
            $terpakai = OrderItem::query()->where('product_variant_id', $baris['id'])->exists();

            if ($terpakai) {
                $this->addError('varian', 'Varian "'.$baris['nama_id'].'" sudah pernah dipesan, jadi tidak bisa dihapus. Nonaktifkan saja.');

                return;
            }
        }

        if (count($this->varian) <= 1) {
            $this->addError('varian', 'Produk harus punya minimal satu varian.');

            return;
        }

        unset($this->varian[$indeks]);
        $this->varian = array_values($this->varian);
        $this->pesanTab = null;
    }

    public function geserVarian(int $indeks, string $arah): void
    {
        $tujuan = $arah === 'naik' ? $indeks - 1 : $indeks + 1;

        if ($tujuan < 0 || $tujuan >= count($this->varian)) {
            return;
        }

        [$this->varian[$indeks], $this->varian[$tujuan]] = [$this->varian[$tujuan], $this->varian[$indeks]];
    }

    public function simpanVarian(): void
    {
        if ($this->produkId === null) {
            $this->addError('varian', 'Simpan informasi dasar dulu sebelum mengatur varian.');

            return;
        }

        if ($this->varian === []) {
            $this->addError('varian', 'Produk harus punya minimal satu varian.');

            return;
        }

        // Aturan diperiksa per baris dengan indeks, sehingga pesan galatnya
        // menunjuk ke baris yang salah — bukan satu pesan umum di atas tabel.
        $aturan = [];
        $pesan = [];

        foreach ($this->varian as $i => $baris) {
            $aturan["varian.$i.nama_id"] = ['required', 'string', 'min:1', 'max:80'];
            $aturan["varian.$i.selisih"] = ['required', 'numeric', 'min:-999999999', 'max:999999999'];
            $aturan["varian.$i.stok"] = ['required', 'integer', 'min:0', 'max:999999'];
            $aturan["varian.$i.berat"] = ['nullable', 'integer', 'min:1', 'max:300000'];
            $aturan["varian.$i.aktif"] = ['boolean'];

            $pesan["varian.$i.nama_id.required"] = 'Nama varian baris '.($i + 1).' belum diisi.';
            $pesan["varian.$i.selisih.required"] = 'Penyesuaian harga baris '.($i + 1).' belum diisi.';
            $pesan["varian.$i.selisih.numeric"] = 'Penyesuaian harga baris '.($i + 1).' harus berupa angka.';
            $pesan["varian.$i.stok.required"] = 'Stok baris '.($i + 1).' belum diisi.';
            $pesan["varian.$i.stok.min"] = 'Stok baris '.($i + 1).' tidak boleh negatif.';
        }

        $this->validate($aturan, $pesan);

        // Nama varian harus berbeda satu sama lain. Dua varian bernama sama
        // membuat pembeli tidak tahu mana yang dipilih, dan membuat baris
        // pesanan ambigu saat dibaca kembali.
        $nama = array_map(fn (array $b) => Str::lower(trim((string) $b['nama_id'])), $this->varian);
        $kembar = array_filter(array_count_values($nama), fn (int $n) => $n > 1);

        if ($kembar !== []) {
            $this->addError('varian', 'Nama varian tidak boleh kembar: "'.implode('", "', array_keys($kembar)).'".');

            return;
        }

        // Penyesuaian harga tidak boleh membuat harga akhir menjadi negatif.
        $hargaDasar = (float) ($this->base_price ?: 0);

        foreach ($this->varian as $i => $baris) {
            if ($hargaDasar + (float) $baris['selisih'] < 0) {
                $this->addError(
                    "varian.$i.selisih",
                    'Harga akhir varian baris '.($i + 1).' menjadi negatif.',
                );

                return;
            }
        }

        // Stok tidak boleh diturunkan di bawah jumlah yang sedang ditahan
        // pesanan berjalan. Kalau dilanggar, stok tersedia menjadi negatif dan
        // barang yang sudah dijanjikan ke pembeli lain ikut terjual.
        foreach ($this->varian as $i => $baris) {
            if ($baris['id'] === null) {
                continue;
            }

            $ditahan = (int) ($baris['ditahan'] ?? 0);

            if ((int) $baris['stok'] < $ditahan) {
                $this->addError(
                    "varian.$i.stok",
                    sprintf(
                        'Stok varian baris %d tidak boleh di bawah %d karena sebanyak itu sedang ditahan pesanan berjalan.',
                        $i + 1,
                        $ditahan,
                    ),
                );

                return;
            }
        }

        DB::transaction(function (): void {
            $produk = Product::query()->findOrFail($this->produkId);
            $idDipakai = [];

            foreach ($this->varian as $urutan => $baris) {
                $data = [
                    'variant_name_id' => $baris['nama_id'],
                    'variant_name_en' => $baris['nama_en'] ?: null,
                    'price_adjustment' => $baris['selisih'],
                    'stock_quantity' => (int) $baris['stok'],
                    'weight_gram' => $baris['berat'] === '' ? null : (int) $baris['berat'],
                    'display_order' => $urutan + 1,
                    'is_active' => $baris['aktif'],
                ];

                if ($baris['id'] === null) {
                    $data['sku_variant'] = $produk->sku.'-V'.($urutan + 1);
                    $varian = $produk->variants()->create($data);
                    $idDipakai[] = $varian->id;
                } else {
                    $varian = ProductVariant::query()->findOrFail($baris['id']);
                    $varian->update($data);
                    $idDipakai[] = $varian->id;
                }
            }

            // Varian yang hilang dari daftar dihapus, kecuali yang pernah
            // dipesan — penghapusan itu sudah dicegah di hapusVarian, tetapi
            // pemeriksaan diulang di sini karena keadaan bisa berubah antara
            // penghapusan di antarmuka dan penyimpanan.
            $produk->variants()
                ->whereNotIn('id', $idDipakai)
                ->get()
                ->each(function (ProductVariant $v): void {
                    if (! OrderItem::query()->where('product_variant_id', $v->id)->exists()) {
                        $v->delete();
                    }
                });
        });

        // Muat ulang supaya kolom "ditahan" dan id varian baru segar.
        $this->muatUlangVarian();

        // Stok dan harga menentukan label "habis" dan "mulai dari" di katalog.
        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'varian disimpan');

        $this->pesan = count($this->varian).' varian tersimpan.';
        $this->pesanTab = 'varian';
        $this->tab = 'media';
    }

    private function muatUlangVarian(): void
    {
        if ($this->produkId === null) {
            return;
        }

        $this->varian = ProductVariant::query()
            ->where('product_id', $this->produkId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'nama_id' => (string) $v->variant_name_id,
                'nama_en' => (string) $v->variant_name_en,
                'selisih' => (string) $v->price_adjustment,
                'stok' => (int) $v->stock_quantity,
                'ditahan' => (int) $v->reserved_quantity,
                'berat' => $v->weight_gram === null ? '' : (string) $v->weight_gram,
                'aktif' => (bool) $v->is_active,
            ])->values()->all();
    }
}
