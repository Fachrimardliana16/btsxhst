<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\MediaUploadLog;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\CacheKatalog;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Form produk tiga tab: Informasi Dasar, Varian, Media
 * (checklist F3-03, F3-04, F3-05).
 *
 * Satu komponen, bukan tiga halaman. Tab disimpan sebagai keadaan di sisi klien
 * supaya berpindah tab tidak memuat ulang apa pun, tetapi data tetap diperiksa
 * dan disimpan di server pada setiap langkah — tab hanyalah cara menampilkan,
 * bukan tempat menyimpan.
 *
 * Produk baru dibuat lebih dulu sebagai draf (belum terbit) begitu tab pertama
 * disimpan. Tanpa itu, varian dan media tidak punya induk untuk digantung, dan
 * pengguna yang mengunggah sepuluh foto lalu menutup peramban akan kehilangan
 * semuanya.
 */
#[Layout('layouts.admin')]
class ProdukForm extends KomponenAdmin
{
    /* Tiga tab, tiga trait. Masing-masing hanya berisi method milik tab itu;
       `simpanInfo`, `ubahTerbit`, `slugUnik`, dan `render` tetap di sini karena
       mereka menyentuh lebih dari satu kelompok sekaligus.

       Tidak ada logika yang diubah saat pemecahan ini — method dipindahkan apa
       adanya. Test KatalogAdminTest dan SkenarioAdminTest menjaga perilakunya. */
    use Concerns\MengelolaInformasiDasar;

    use Concerns\MengelolaMedia;
    use Concerns\MengelolaVarian;
    use WithFileUploads;

    public ?int $produkId = null;

    public string $tab = 'info';

    // ── Tab 1: informasi dasar ───────────────────────────────────────
    public ?int $category_id = null;

    public string $sku = '';

    public string $title_id = '';

    public string $title_en = '';

    public string $slug = '';

    public string $excerpt_id = '';

    public string $excerpt_en = '';

    public string $description_id = '';

    public string $description_en = '';

    public string $base_price = '';

    public string $weight_gram = '1000';

    public bool $is_featured = false;

    public bool $is_published = false;

    public bool $slugDisentuh = false;

    // ── Tab 1b: spesifikasi teknis ──────────────────────────────────
    /**
     * Baris spesifikasi teknis (checklist F6-04).
     *
     * Urutannya adalah urutan tampil di halaman produk, jadi larik biasa —
     * bukan larik berkunci. Sama seperti varian, penyimpanannya ikut dalam
     * transaksi tab informasi dasar supaya tidak ada produk dengan sebagian
     * spesifikasi tersimpan.
     *
     * @var array<int, array<string, string>>
     */
    public array $spesifikasi = [];

    // ── Tab 2: varian ───────────────────────────────────────────────
    /**
     * Baris varian disimpan sebagai larik biasa, bukan komponen anak.
     * Alasannya: seluruh varian disimpan dalam satu transaksi bersama produk,
     * sehingga tidak mungkin ada produk dengan sebagian varian tersimpan.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $varian = [];

    // ── Tab 3: media ────────────────────────────────────────────────
    /** @var array<int, TemporaryUploadedFile> */
    public array $berkasBaru = [];

    /**
     * Menandai bahwa peramban sedang mengunggah berkas sementara ke server.
     *
     * Livewire mengunggah berkas ke folder sementara lebih dulu, baru
     * memanggil aksi. Selama unggahan itu berjalan, $berkasBaru masih kosong,
     * sehingga aksi yang dijalankan terlalu cepat akan berhenti dengan pesan
     * yang menyesatkan — seolah-olah pengguna belum menyimpan informasi dasar.
     * Atribut ini dipakai tampilan untuk menahan tombol sampai siap.
     */
    public bool $sedangUnggah = false;

    /** @var array<int, int> Urutan id media setelah pengguna menggesernya. */
    public array $urutanMedia = [];

    public ?string $pesan = null;

    public ?string $pesanTab = null;

    /** @var array<int, string> */
    public array $ditolak = [];

    public function mount(?string $ulid = null): void
    {

        if ($ulid === null) {
            $this->siapkanProdukBaru();

            return;
        }

        $produk = Product::query()
            ->with(['media', 'variants' => fn ($q) => $q->orderBy('display_order')])
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->produkId = $produk->id;
        $this->category_id = $produk->category_id;
        $this->sku = (string) $produk->sku;
        $this->title_id = (string) $produk->title_id;
        $this->title_en = (string) $produk->title_en;
        $this->slug = (string) $produk->slug;
        $this->excerpt_id = (string) $produk->excerpt_id;
        $this->excerpt_en = (string) $produk->excerpt_en;
        $this->description_id = (string) $produk->description_id;
        $this->description_en = (string) $produk->description_en;
        $this->base_price = (string) $produk->base_price;
        $this->weight_gram = (string) $produk->weight_gram;
        $this->is_featured = (bool) $produk->is_featured;
        $this->is_published = (bool) $produk->is_published;
        $this->slugDisentuh = true;
        $this->spesifikasi = $this->spesifikasiDari($produk->spesifikasi_json);

        $this->varian = $produk->variants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'nama_id' => (string) $v->variant_name_id,
            'nama_en' => (string) $v->variant_name_en,
            'selisih' => (string) $v->price_adjustment,
            'stok' => (int) $v->stock_quantity,
            'ditahan' => (int) $v->reserved_quantity,
            'berat' => $v->weight_gram === null ? '' : (string) $v->weight_gram,
            'aktif' => (bool) $v->is_active,
        ])->values()->all();

        $this->urutanMedia = $produk->media()->orderBy('display_order')->pluck('id')->all();
    }

    private function siapkanProdukBaru(): void
    {
        $this->produkId = null;
        $this->sku = $this->skuOtomatis();
        $this->category_id = Category::query()->aktif()->terurut()->value('id');

        // Varian pertama sengaja belum dibuat. Produk baru belum tersimpan, jadi
        // variannya tidak punya induk untuk digantung; baris default dibuat
        // begitu tab pertama disimpan.
        $this->varian = [];
    }

    /** Baris varian kosong dengan bentuk yang sama seperti hasil muat. */
    private function barisVarian(): array
    {
        return [
            'id' => null,
            'nama_id' => '',
            'nama_en' => '',
            'selisih' => '0',
            'stok' => 0,
            'ditahan' => 0,
            'berat' => '',
            'aktif' => true,
        ];
    }

    /** SKU dasar yang belum dipakai, supaya tidak gagal validasi sejak awal. */
    private function skuOtomatis(): string
    {
        $nomor = ((int) Product::query()->max('id')) + 1;
        $kandidat = sprintf('%s-%04d', config('iksan.order_prefix', 'IK'), $nomor);

        while (Product::query()->where('sku', $kandidat)->exists()) {
            $nomor++;
            $kandidat = sprintf('%s-%04d', config('iksan.order_prefix', 'IK'), $nomor);
        }

        return $kandidat;
    }

    // ── Tab 1: simpan informasi dasar ───────────────────────────────

    /**
     * Menyenangkan judul menjadi slug selama pengguna belum menyentuh slug.
     *
     * Kedua hook ini HARUS tinggal di kelas induk, bukan di trait. Livewire
     * memanggil `updated{NamaProperti}` sebagai hook, dan pemanggilannya
     * bergantung pada properti itu ada di komponen — memindahkannya ke trait
     * tidak berbahaya selama trait-nya terpakai, tetapi menempatkannya
     * berdekatan dengan properti $slug/$slugDisentuh membuat hubungannya
     * terlihat jelas saat berkas ini disunting.
     */
    public function updatedTitleId(string $nilai): void
    {
        if (! $this->slugDisentuh) {
            $this->slug = $this->slugUnik($nilai, $this->produkId);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugDisentuh = true;
    }

    public function simpanInfo(): void
    {
        $bersih = $this->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => [
                'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('products', 'sku')->ignore($this->produkId),
            ],
            'title_id' => ['required', 'string', 'min:3', 'max:180'],
            'title_en' => ['nullable', 'string', 'max:180'],
            'slug' => [
                'required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($this->produkId),
            ],
            'excerpt_id' => ['nullable', 'string', 'max:300'],
            'excerpt_en' => ['nullable', 'string', 'max:300'],
            'description_id' => ['nullable', 'string', 'max:20000'],
            'description_en' => ['nullable', 'string', 'max:20000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'weight_gram' => ['required', 'integer', 'min:1', 'max:300000'],
            'is_featured' => ['boolean'],
            'is_published' => ['boolean'],
            'spesifikasi' => ['array', 'max:12'],
            'spesifikasi.*.label_id' => ['nullable', 'string', 'max:60'],
            'spesifikasi.*.nilai_id' => ['nullable', 'string', 'max:180'],
            'spesifikasi.*.label_en' => ['nullable', 'string', 'max:60'],
            'spesifikasi.*.nilai_en' => ['nullable', 'string', 'max:180'],
        ], [
            'category_id.required' => 'Pilih kategori produk.',
            'category_id.exists' => __('site.msg.produk.kategori_tidak_ditemukan'),
            'sku.required' => __('site.msg.produk.sku_wajib'),
            'sku.regex' => __('site.msg.produk.sku_format'),
            'sku.unique' => __('site.msg.produk.sku_dipakai'),
            'title_id.required' => __('site.msg.produk.nama_id_wajib'),
            'title_id.min' => 'Nama produk minimal tiga huruf.',
            'slug.regex' => __('site.msg.kategori.slug_format'),
            'slug.unique' => __('site.msg.produk.slug_dipakai'),
            'base_price.required' => __('site.msg.produk.harga_wajib'),
            'base_price.numeric' => __('site.msg.produk.harga_angka'),
            'weight_gram.required' => __('site.msg2.produk.berat_wajib'),
        ]);

        // Dibersihkan sebelum transaksi: baris tanpa label tidak ikut disimpan,
        // dan hasil bersihnya dipakai baik untuk produk baru maupun yang diubah.
        $spesifikasi = $this->spesifikasiBersih();

        DB::transaction(function () use ($bersih, $spesifikasi): void {
            $data = [
                'category_id' => $bersih['category_id'],
                'sku' => $bersih['sku'],
                'title_id' => $bersih['title_id'],
                'title_en' => $bersih['title_en'] ?: null,
                'slug' => $bersih['slug'],
                'excerpt_id' => $bersih['excerpt_id'] ?: null,
                'excerpt_en' => $bersih['excerpt_en'] ?: null,
                'description_id' => $bersih['description_id'] ?: null,
                'description_en' => $bersih['description_en'] ?: null,
                'base_price' => $bersih['base_price'],
                'weight_gram' => (int) $bersih['weight_gram'],
                'is_featured' => $bersih['is_featured'],
                'is_published' => $bersih['is_published'],
                // Spesifikasi disimpan sebagai satu kolom JSON (checklist F6-04).
                // Kolom kosong ditulis sebagai null, bukan "[]", supaya produk
                // tanpa spesifikasi tidak pernah membuat tabel kosong.
                'spesifikasi_json' => ($spesifikasi === [] ? null : json_encode($spesifikasi, JSON_UNESCAPED_UNICODE)),
            ];

            if ($this->produkId === null) {
                $produk = Product::query()->create($data + [
                    'published_at' => $bersih['is_published'] ? now() : null,
                ]);

                $this->produkId = $produk->id;

                CatatanAktivitas::catat('produk.dibuat', ['sku' => $produk->sku], auth()->id(), $produk);
                CacheKatalog::tandaiProduk($produk, 'produk dibuat');

                // Varian default benar-benar dibuat, bukan hanya disiapkan di
                // formulir. Produk tanpa varian tidak dapat dibeli sama sekali,
                // sehingga menyimpannya kosong berarti membuat produk yang pasti
                // gagal saat checkout — dan itu baru ketahuan oleh pembeli.
                $varian = $produk->variants()->create([
                    'sku_variant' => $produk->sku.'-V1',
                    'variant_name_id' => 'Standar',
                    'variant_name_en' => 'Standard',
                    'price_adjustment' => 0,
                    'stock_quantity' => 0,
                    'display_order' => 1,
                    'is_active' => true,
                ]);

                $this->varian = [$this->barisVarian()];
                $this->varian[0]['id'] = $varian->id;
            } else {
                $produk = Product::query()->findOrFail($this->produkId);

                // Waktu terbit hanya diisi sekali, saat produk pertama kali
                // diterbitkan. Menerbitkan ulang tidak boleh menggeser tanggal
                // terbit yang asli.
                if ($bersih['is_published'] && $produk->published_at === null) {
                    $data['published_at'] = now();
                }

                if (! $bersih['is_published']) {
                    $data['published_at'] = null;
                }

                $produk->update($data);

                CatatanAktivitas::catat('produk.diubah', [
                    'sku' => $produk->sku,
                    'is_published' => $produk->is_published,
                ], auth()->id(), $produk);

                CacheKatalog::tandaiProduk($produk, 'produk diubah');
            }
        });

        $this->pesan = 'Informasi dasar tersimpan.';
        $this->pesanTab = 'info';

        // Setelah tab pertama tersimpan, lanjutkan ke varian: produk tanpa
        // varian belum bisa dibeli, jadi itu langkah berikutnya yang wajar.
        $this->tab = 'varian';
    }

    public function periksaAntrean(): void
    {
        $this->muatUlangUrutanMedia();
        $this->pesan = 'Daftar gambar diperbarui.';
        $this->pesanTab = 'media';
    }

    // ── Publikasi ───────────────────────────────────────────────────

    /**
     * Menerbitkan atau menarik produk.
     *
     * Produk tanpa varian tidak boleh diterbitkan: halaman detailnya akan
     * menampilkan tombol beli yang tidak bisa ditekan, dan checkout-nya gagal.
     */
    public function ubahTerbit(): void
    {
        if ($this->produkId === null) {
            $this->addError('umum', 'Simpan informasi dasar dulu.');

            return;
        }

        $produk = Product::query()->withCount('variants')->findOrFail($this->produkId);

        if (! $produk->is_published && $produk->variants_count === 0) {
            $this->addError('umum', __('site.msg2.produk.tanpa_varian_terbit'));

            return;
        }

        $jadi = ! $produk->is_published;

        $produk->update([
            'is_published' => $jadi,
            'published_at' => $jadi ? ($produk->published_at ?? now()) : null,
        ]);

        $this->is_published = $jadi;

        CatatanAktivitas::catat($jadi ? 'produk.diterbitkan' : 'produk.ditarik', [
            'sku' => $produk->sku,
        ], auth()->id(), $produk);

        CacheKatalog::tandaiProduk($produk, $jadi ? 'produk diterbitkan' : 'produk ditarik');

        $this->pesan = $jadi ? 'Produk diterbitkan.' : __('site.msg2.produk.ditarik_singkat');
        $this->pesanTab = null;
    }

    private function slugUnik(string $teks, ?int $kecualiId): string
    {
        $dasar = Str::slug($teks);

        if ($dasar === '') {
            return '';
        }

        $kandidat = $dasar;
        $nomor = 2;

        while (Product::query()
            ->where('slug', $kandidat)
            ->when($kecualiId !== null, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $kandidat = $dasar.'-'.$nomor;
            $nomor++;
        }

        return $kandidat;
    }

    public function render(): View
    {
        $produk = $this->produkId === null ? null : Product::query()->find($this->produkId);

        $media = $this->produkId === null
            ? collect()
            : ProductMedia::query()
                ->where('product_id', $this->produkId)
                ->orderByRaw('CASE WHEN is_primary = 1 THEN 0 ELSE 1 END')
                ->orderBy('display_order')
                ->get();

        // Jumlah berkas yang masih diproses di antrean, supaya pengguna tahu
        // bahwa gambar yang belum muncul bukan hilang.
        // Hanya berkas milik produk ini. Menghitung antrean seluruh produk akan
        // menampilkan angka milik produk lain di halaman ini.
        $sedangDiproses = $this->produkId === null ? 0 : MediaUploadLog::query()
            ->where('product_id', $this->produkId)
            ->whereIn('status', ['queued', 'processing'])
            ->count();

        return view('livewire.admin.produk-form', [
            'produk' => $produk,
            'kategori' => Category::query()->terurut()->get(),
            'media' => $media,
            'sedangDiproses' => $sedangDiproses,
        ])->layout('layouts.admin', [
            'judul' => $produk === null ? 'Produk Baru' : 'Sunting '.$produk->title_id,
            'bagian' => 'Katalog',
        ]);
    }
}
