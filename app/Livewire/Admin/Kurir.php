<?php

namespace App\Livewire\Admin;

use App\Models\Courier;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * CRUD master ekspedisi.
 *
 * Dulu daftar kurir hanya satu nilai JSON di halaman Pengaturan: tidak ada
 * tempat untuk tautan pelacakan per kurir, tidak ada urutan, dan menambah satu
 * kurir berarti menyunting teks JSON tanpa tahu apakah formatnya masih benar.
 *
 * Satu baris wajib ditandai bawaan: itulah yang terpilih di checkout. Penanda
 * ini dijaga di beberapa titik (simpan, aktif/nonaktif, hapus) karena daftar
 * tanpa bawaan membuat checkout membuka pilihan kosong.
 */
#[Layout('layouts.admin')]
class Kurir extends KomponenAdmin
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    public bool $formTerbuka = false;

    public ?int $suntingId = null;

    public string $nama = '';

    public string $kode = '';

    public string $tautan = '';

    public bool $aktif = true;

    public bool $bawaan = false;

    /** Kode diubah otomatis dari nama selama pengguna belum menyentuhnya. */
    public bool $kodeDisentuh = false;

    public ?string $pesan = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedNama(string $nilai): void
    {
        if (! $this->kodeDisentuh) {
            $this->kode = $this->kodeUnik($nilai, $this->suntingId);
        }
    }

    public function updatedKode(): void
    {
        $this->kodeDisentuh = true;
    }

    public function bukaForm(): void
    {
        $this->bersihkan();
        $this->suntingId = null;
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->bersihkan();
        $this->formTerbuka = false;
    }

    public function sunting(int $id): void
    {
        $kurir = Courier::query()->findOrFail($id);

        $this->suntingId = $kurir->id;
        $this->nama = (string) $kurir->name;
        $this->kode = (string) $kurir->code;
        $this->tautan = (string) ($kurir->tracking_url ?? '');
        $this->aktif = (bool) $kurir->is_active;
        $this->bawaan = (bool) $kurir->is_default;
        $this->kodeDisentuh = true;
        $this->formTerbuka = true;
        $this->pesan = null;

        $this->resetValidation();
    }

    public function simpan(): void
    {
        $bersih = $this->validate([
            'nama' => ['required', 'string', 'min:2', 'max:60'],
            'kode' => [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('couriers', 'code')->ignore($this->suntingId),
            ],
            'tautan' => ['nullable', 'string', 'max:500'],
            'aktif' => ['boolean'],
            'bawaan' => ['boolean'],
        ], [
            'nama.required' => 'The courier name is required.',
            'nama.min' => 'The courier name must be at least two characters.',
            'kode.regex' => 'The code may only contain lowercase letters, numbers, and hyphens.',
            'kode.unique' => 'This code is already used by another courier.',
        ]);

        $tautan = trim((string) $bersih['tautan']);

        if ($tautan !== '' && ! str_contains($tautan, '%s')) {
            $this->addError('tautan', 'The tracking URL must contain %s where the tracking number goes.');

            return;
        }

        // Kurir nonaktif tidak boleh menjadi bawaan: checkout hanya membaca
        // kurir aktif, jadi penandanya akan menggantung tanpa pernah dipakai.
        if ($bersih['bawaan'] && ! $bersih['aktif']) {
            $this->addError('bawaan', 'Activate the courier before making it the default.');

            return;
        }

        // Kurir bawaan tidak boleh dinonaktifkan tanpa pengganti: checkout akan
        // membuka pilihan kosong.
        if ($this->suntingId !== null && ! $bersih['aktif']) {
            $lama = Courier::query()->findOrFail($this->suntingId);

            if ($lama->is_default) {
                $this->addError('aktif', 'This is the default courier. Set another courier as default before deactivating it.');

                return;
            }
        }

        DB::transaction(function () use ($bersih, $tautan): void {
            if ($this->suntingId === null) {
                $urutan = ((int) Courier::query()->max('display_order')) + 1;
                $pertama = Courier::query()->count() === 0;

                if ($bersih['bawaan'] || $pertama) {
                    Courier::query()->update(['is_default' => false]);
                }

                $kurir = Courier::query()->create([
                    'name' => $bersih['nama'],
                    'code' => $bersih['kode'],
                    'tracking_url' => $tautan !== '' ? $tautan : null,
                    'display_order' => $urutan,
                    'is_active' => $bersih['aktif'],
                    'is_default' => $bersih['bawaan'] || $pertama,
                ]);

                CatatanAktivitas::catat('kurir.dibuat', ['code' => $kurir->code], auth()->id(), $kurir);

                $this->pesan = 'Courier "'.$kurir->name.'" added.';
            } else {
                $kurir = Courier::query()->findOrFail($this->suntingId);

                $sebelum = [
                    'name' => $kurir->name,
                    'code' => $kurir->code,
                    'is_active' => $kurir->is_active,
                    'is_default' => $kurir->is_default,
                ];

                if ($bersih['bawaan']) {
                    Courier::query()->whereKeyNot($kurir->id)->update(['is_default' => false]);
                }

                $kurir->update([
                    'name' => $bersih['nama'],
                    'code' => $bersih['kode'],
                    'tracking_url' => $tautan !== '' ? $tautan : null,
                    'is_active' => $bersih['aktif'],
                    'is_default' => $bersih['bawaan'],
                ]);

                // Tetap harus ada satu bawaan selama masih ada kurir aktif.
                $this->pastikanBawaan();

                CatatanAktivitas::catat('kurir.diubah', [
                    'sebelum' => $sebelum,
                    'sesudah' => [
                        'name' => $kurir->name,
                        'code' => $kurir->code,
                        'is_active' => $kurir->is_active,
                        'is_default' => $kurir->is_default,
                    ],
                ], auth()->id(), $kurir);

                $this->pesan = 'Courier "'.$kurir->name.'" updated.';
            }
        });

        $this->bersihkan();
        $this->formTerbuka = false;
    }

    public function ubahAktif(int $id): void
    {
        $kurir = Courier::query()->findOrFail($id);

        if ($kurir->is_active && $kurir->is_default) {
            $this->addError('umum', 'This is the default courier. Set another courier as default before deactivating it.');

            return;
        }

        $kurir->update(['is_active' => ! $kurir->is_active]);

        $this->pastikanBawaan();

        CatatanAktivitas::catat('kurir.status', [
            'code' => $kurir->code,
            'is_active' => $kurir->is_active,
        ], auth()->id(), $kurir);

        $this->pesan = $kurir->is_active
            ? 'Courier "'.$kurir->name.'" activated.'
            : 'Courier "'.$kurir->name.'" deactivated.';
    }

    public function jadikanBawaan(int $id): void
    {
        $kurir = Courier::query()->findOrFail($id);

        if (! $kurir->is_active) {
            $this->addError('umum', 'Activate this courier before making it the default.');

            return;
        }

        DB::transaction(function () use ($kurir): void {
            Courier::query()->whereKeyNot($kurir->id)->update(['is_default' => false]);
            $kurir->update(['is_default' => true]);

            CatatanAktivitas::catat('kurir.bawaan', ['code' => $kurir->code], auth()->id(), $kurir);
        });

        $this->pesan = 'Courier "'.$kurir->name.'" is now the checkout default.';
    }

    /**
     * Menggeser satu baris, lalu merapikan seluruh nomor urut.
     *
     * Merapikan setelah setiap pergeseran menjaga agar tidak pernah ada nomor
     * kembar atau celah, sekalipun data lama sudah tidak beraturan.
     */
    public function geser(int $id, string $arah): void
    {
        DB::transaction(function () use ($id, $arah): void {
            $urutan = Courier::query()->orderBy('display_order')->orderBy('id')->pluck('id')->all();
            $posisi = array_search($id, $urutan, true);

            if ($posisi === false) {
                return;
            }

            $tujuan = $arah === 'naik' ? $posisi - 1 : $posisi + 1;

            if ($tujuan < 0 || $tujuan >= count($urutan)) {
                return;
            }

            [$urutan[$posisi], $urutan[$tujuan]] = [$urutan[$tujuan], $urutan[$posisi]];

            foreach ($urutan as $nomor => $satuId) {
                Courier::query()->whereKey($satuId)->update(['display_order' => $nomor + 1]);
            }
        });
    }

    public function hapus(int $id): void
    {
        $kurir = Courier::query()->findOrFail($id);

        if ($kurir->is_default) {
            $this->addError('umum', 'This is the default courier. Set another courier as default before deleting it.');

            return;
        }

        CatatanAktivitas::catat('kurir.dihapus', ['code' => $kurir->code], auth()->id(), $kurir);

        $kurir->delete();

        $this->pastikanBawaan();

        $this->pesan = 'Courier "'.$kurir->name.'" deleted.';
    }

    /** Satu kurir aktif selalu ditandai bawaan bila belum ada. */
    private function pastikanBawaan(): void
    {
        $ada = Courier::query()->aktif()->where('is_default', true)->exists();

        if ($ada) {
            return;
        }

        $pengganti = Courier::query()->aktif()->terurut()->first();

        if ($pengganti !== null) {
            $pengganti->update(['is_default' => true]);
        }
    }

    private function kodeUnik(string $teks, ?int $kecualiId): string
    {
        $dasar = Str::slug($teks);

        if ($dasar === '') {
            return '';
        }

        $kandidat = $dasar;
        $nomor = 2;

        while ($this->kodeDipakai($kandidat, $kecualiId)) {
            $kandidat = $dasar.'-'.$nomor;
            $nomor++;
        }

        return $kandidat;
    }

    private function kodeDipakai(string $kode, ?int $kecualiId): bool
    {
        return Courier::query()
            ->where('code', $kode)
            ->when($kecualiId !== null, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists();
    }

    private function bersihkan(): void
    {
        $this->suntingId = null;
        $this->nama = '';
        $this->kode = '';
        $this->tautan = '';
        $this->aktif = true;
        $this->bawaan = false;
        $this->kodeDisentuh = false;

        $this->resetValidation();
    }

    public function render(): View
    {
        $daftar = Courier::query()
            ->when($this->cari !== '', function ($q): void {
                $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->cari).'%';

                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $pola)
                    ->orWhere('code', 'like', $pola)
                    ->orWhere('tracking_url', 'like', $pola));
            })
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.admin.kurir', [
            'daftar' => $daftar,
            'jumlah' => Courier::query()->count(),
            'jumlahAktif' => Courier::query()->aktif()->count(),
        ])->layout('layouts.admin', ['judul' => 'Couriers', 'bagian' => 'Penjualan']);
    }
}
