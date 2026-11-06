<?php

namespace App\Livewire\Admin\Concerns;

/**
 * Tab 1 — informasi dasar: spesifikasi teknis, tab, dan slug otomatis.
 *
 * DIREKAT dari ProdukForm tanpa mengubah logikanya — hanya dipindahkan supaya
 * satu berkas tidak memuat tiga tanggung jawab sekaligus. Trait ini TIDAK
 * berdiri sendiri: ia mengisi properti dan method pada komponen induk, dan
 * mengandalkan properti publik milik induk ($produkId, $pesan, $pesanTab, dst).
 */
trait MengelolaInformasiDasar
{
    // ── Spesifikasi teknis (tab informasi dasar, checklist F6-04) ────

    /** @return array<int, array<string, string>> */
    private function spesifikasiDari(?string $json): array
    {
        $mentah = json_decode((string) $json, true);

        if (! is_array($mentah)) {
            return [];
        }

        $baris = [];

        foreach ($mentah as $item) {
            if (! is_array($item)) {
                continue;
            }

            $baris[] = [
                'label_id' => (string) ($item['label_id'] ?? ''),
                'nilai_id' => (string) ($item['nilai_id'] ?? ''),
                'label_en' => (string) ($item['label_en'] ?? ''),
                'nilai_en' => (string) ($item['nilai_en'] ?? ''),
            ];
        }

        return $baris;
    }

    public function tambahSpesifikasi(): void
    {
        $this->spesifikasi[] = ['label_id' => '', 'nilai_id' => '', 'label_en' => '', 'nilai_en' => ''];
        $this->pesanTab = null;
    }

    public function hapusSpesifikasi(int $indeks): void
    {
        unset($this->spesifikasi[$indeks]);
        $this->spesifikasi = array_values($this->spesifikasi);
        $this->pesanTab = null;
    }

    public function geserSpesifikasi(int $indeks, string $arah): void
    {
        $tujuan = $arah === 'naik' ? $indeks - 1 : $indeks + 1;

        if ($tujuan < 0 || $tujuan >= count($this->spesifikasi)) {
            return;
        }

        [$this->spesifikasi[$indeks], $this->spesifikasi[$tujuan]] = [$this->spesifikasi[$tujuan], $this->spesifikasi[$indeks]];
        $this->pesanTab = null;
    }

    /**
     * Membersihkan baris spesifikasi sebelum disimpan.
     *
     * Baris tanpa label dibuang, bukan disimpan sebagai baris kosong: baris
     * seperti itu hanya menghasilkan garis tanpa keterangan di halaman produk.
     * Label tanpa isi dibiarkan tersimpan — itu baris yang baru setengah
     * diisi, dan pembaca halaman publik melewatkannya sendiri.
     *
     * @return array<int, array<string, string>>
     */
    private function spesifikasiBersih(): array
    {
        $baris = [];

        foreach ($this->spesifikasi as $item) {
            $labelId = trim((string) ($item['label_id'] ?? ''));

            if ($labelId === '') {
                continue;
            }

            $baris[] = [
                'label_id' => $labelId,
                'nilai_id' => trim((string) ($item['nilai_id'] ?? '')),
                'label_en' => trim((string) ($item['label_en'] ?? '')),
                'nilai_en' => trim((string) ($item['nilai_en'] ?? '')),
            ];
        }

        return $baris;
    }

    public function aturTab(string $tab): void
    {
        if (in_array($tab, ['info', 'varian', 'media'], true)) {
            $this->tab = $tab;
            $this->resetValidation();
            $this->pesanTab = null;
        }
    }
}
