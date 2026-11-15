<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ekspedisi (kurir) yang ditawarkan di checkout.
 *
 * Dulu daftar ini hanya sebaris JSON di pengaturan. Tabel sendiri dipakai
 * karena tiap kurir butuh tautan pelacakan sendiri, urutan tampil, dan status
 * aktif — tiga hal yang tidak muat di satu string tanpa menjadi teks yang
 * harus disunting dengan hati-hati.
 *
 * Pesanan TIDAK memakai foreign key ke tabel ini: `orders.shipping_courier`
 * menyimpan nama saat pesanan dibuat. Kurir yang kemudian diganti namanya
 * atau dihapus tidak boleh mengubah dokumen pesanan lama.
 */
class Courier extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'name',
        'code',
        'tracking_url',
        'display_order',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Nama kurir aktif menurut urutan tampil.
     *
     * Mengembalikan array string, bukan model: pemanggilnya (checkout, footer,
     * FAQ beranda) hanya membutuhkan nama, dan satu bentuk data berarti tidak
     * ada dua cara membaca daftar yang sama.
     *
     * @return array<int, string>
     */
    public static function aktifNama(): array
    {
        return static::query()->aktif()->terurut()->pluck('name')->all();
    }

    /** Kurir yang dipilih di checkout: yang ditandai bawaan, atau yang pertama. */
    public static function bawaan(): ?self
    {
        return static::query()->aktif()->where('is_default', true)->first()
            ?? static::query()->aktif()->terurut()->first();
    }

    /**
     * Tautan pelacakan resmi untuk sebuah nomor resi.
     *
     * Bila kurir belum punya pola tautan, pencarian umum dipakai — tautan
     * kosong membuat pembeli tidak bisa melacak sama sekali.
     */
    public function tautan(string $resi): string
    {
        if (filled($this->tracking_url) && str_contains((string) $this->tracking_url, '%s')) {
            return sprintf((string) $this->tracking_url, rawurlencode($resi));
        }

        return 'https://www.google.com/search?q='.rawurlencode($this->name.' '.$resi.' lacak resi');
    }
}
