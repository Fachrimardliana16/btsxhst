<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CacheKatalog;
use App\Support\HargaDolar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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
            'nama_id' => $sumber['nama_id'].' (copy)',
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
                $this->addError('varian', 'Variant "'.$baris['nama_id'].'" has already been ordered, so it cannot be deleted. Deactivate it instead.');

                return;
            }
        }

        // Baris terakhir BOLEH dihapus: produk tanpa varian memang tidak bisa
        // dibeli dan tidak bisa diterbitkan (dijaga di ubahTerbit()), tetapi
        // menyusun ulang daftar varian dari nol adalah keadaan kerja yang sah.
        // Menahan baris terakhir justru memaksa admin menyunting nama varian
        // lama satu per satu.
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
            $this->addError('varian', 'Save the basic information before setting up variants.');

            return;
        }

        // Daftar kosong sah: seluruh varian dihapus. Produk seperti itu tetap
        // tersimpan sebagai draf dan tidak bisa diterbitkan sampai varian baru
        // ditambahkan — lihat ubahTerbit().

        // Aturan diperiksa per baris dengan indeks, sehingga pesan galatnya
        // menunjuk ke baris yang salah — bukan satu pesan umum di atas tabel.
        $aturan = [];
        $pesan = [];

        foreach ($this->varian as $i => $baris) {
            $aturan["varian.$i.nama_id"] = ['required', 'string', 'min:1', 'max:80'];
            $aturan["varian.$i.selisih"] = ['nullable', 'numeric', 'min:-999999999', 'max:999999999'];
            $aturan["varian.$i.selisih_usd"] = ['nullable', 'numeric', 'min:-999999', 'max:999999'];
            $aturan["varian.$i.stok"] = ['required', 'integer', 'min:0', 'max:999999'];
            $aturan["varian.$i.berat"] = ['nullable', 'integer', 'min:1', 'max:300000'];
            $aturan["varian.$i.aktif"] = ['boolean'];

            $pesan["varian.$i.nama_id.required"] = 'Variant name for row '.($i + 1).' is required.';
            $pesan["varian.$i.selisih.required"] = 'Price adjustment for row '.($i + 1).' is required.';
            $pesan["varian.$i.selisih.numeric"] = 'Price adjustment for row '.($i + 1).' must be a number.';
            $pesan["varian.$i.selisih_usd.numeric"] = 'Price adjustment for row '.($i + 1).' must be a number.';
            $pesan["varian.$i.stok.required"] = 'Stock for row '.($i + 1).' is required.';
            $pesan["varian.$i.stok.min"] = 'Stock for row '.($i + 1).' cannot be negative.';
        }

        // validate() dengan aturan kosong melempar MissingRulesException —
        // daftar varian yang sengaja dikosongkan bukan galat.
        if ($aturan !== []) {
            $this->validate($aturan, $pesan);
        }

        // Nama varian harus berbeda satu sama lain. Dua varian bernama sama
        // membuat pembeli tidak tahu mana yang dipilih, dan membuat baris
        // pesanan ambigu saat dibaca kembali.
        $nama = array_map(fn (array $b) => Str::lower(trim((string) $b['nama_id'])), $this->varian);
        $kembar = array_filter(array_count_values($nama), fn (int $n) => $n > 1);

        if ($kembar !== []) {
            $this->addError('varian', 'Variant names must be unique: "'.implode('", "', array_keys($kembar)).'".');

            return;
        }

        // Penyesuaian harga tidak boleh membuat harga akhir menjadi negatif.
        $hargaDasar = (float) ($this->base_price ?: 0);

        foreach ($this->varian as $i => $baris) {
            if ($hargaDasar + $this->selisihRupiah($baris) < 0) {
                $this->addError(
                    "varian.$i.selisih",
                    'The final price for variant row '.($i + 1).' would be negative.',
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

            // Dibaca dari basis data, bukan dari `ditahan` kiriman form.
            // Nilai form adalah salinan saat halaman dimuat; pesanan yang
            // masuk setelahnya tidak terlihat di sana.
            $ditahan = (int) DB::table('product_variants')
                ->where('id', (int) $baris['id'])
                ->value('reserved_quantity');

            if ((int) $baris['stok'] < $ditahan) {
                $this->addError(
                    "varian.$i.stok",
                    sprintf(
                        'Stock for variant row %d cannot be below %d because that many are reserved by open orders.',
                        $i + 1,
                        $ditahan,
                    ),
                );

                return;
            }
        }

        try {
            DB::transaction(function (): void {
                $produk = Product::query()->findOrFail($this->produkId);
                $idDipakai = [];

                foreach ($this->varian as $urutan => $baris) {
                    $selisihRupiah = $this->selisihRupiah($baris);
                    $selisihUsd = filled($baris['selisih_usd'] ?? null)
                        ? (float) $baris['selisih_usd']
                        : (HargaDolar::dariRupiah($selisihRupiah) ?? 0.0);

                    $data = [
                        'variant_name_id' => $baris['nama_id'],
                        'variant_name_en' => $baris['nama_id'],
                        'price_adjustment' => $selisihRupiah,
                        'price_adjustment_usd' => $selisihUsd,
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
                        // UPDATE bersyarat: pemeriksaan di atas memakai angka yang
                        // dibaca beberapa saat sebelumnya. Pesanan yang masuk di
                        // antaranya bisa menaikkan reserved_quantity, dan stok yang
                        // lebih kecil dari tahanan membuat stok tersedia negatif.
                        // Basis data yang memutuskan, bukan kode PHP.
                        $terkena = DB::table('product_variants')
                            ->where('id', (int) $baris['id'])
                            ->whereRaw('reserved_quantity <= ?', [(int) $baris['stok']])
                            ->update($data);

                        if ($terkena !== 1) {
                            throw new RuntimeException(sprintf(
                                'Stock for variant row %d cannot be below the quantity already reserved by open orders.',
                                $urutan + 1,
                            ));
                        }

                        $idDipakai[] = (int) $baris['id'];
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
        } catch (RuntimeException $e) {
            // Kegagalan stok adalah pesan bisnis yang ditulis di atas; sisa
            // RuntimeException lain (mis. model hilang) juga aman ditampilkan
            // di panel ini daripada menjadi layar galat.
            $this->addError('varian', $e->getMessage());

            return;
        }

        // Muat ulang supaya kolom "ditahan" dan id varian baru segar.
        $this->muatUlangVarian();

        // Stok dan harga menentukan label "habis" dan "mulai dari" di katalog.
        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'varian disimpan');

        $this->pesan = count($this->varian).' variants saved.';
        $this->pesanTab = 'varian';
        $this->tab = 'media';
    }

    /** Penyesuaian harga baris dalam rupiah, dari input USD bila ada. */
    private function selisihRupiah(array $baris): float
    {
        $usd = $baris['selisih_usd'] ?? null;

        return filled($usd)
            ? HargaDolar::keRupiah((float) $usd)
            : (float) ($baris['selisih'] ?? 0);
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
                'nama_id' => (string) ($v->variant_name_en ?: $v->variant_name_id),
                'nama_en' => '',
                'selisih' => (string) $v->price_adjustment,
                'selisih_usd' => (string) ($v->price_adjustment_usd
                    ?? HargaDolar::dariRupiah($v->price_adjustment)),
                'stok' => (int) $v->stock_quantity,
                'ditahan' => (int) $v->reserved_quantity,
                'berat' => $v->weight_gram === null ? '' : (string) $v->weight_gram,
                'aktif' => (bool) $v->is_active,
            ])->values()->all();
    }
}
