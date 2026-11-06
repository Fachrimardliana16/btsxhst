<?php

namespace Tests\Feature;

use App\Livewire\Admin\FaqPanel;
use App\Livewire\Admin\Kategori;
use App\Models\Category;
use App\Models\Faq;
use App\Models\User;
use App\Support\CacheKatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integrasi panel admin → halaman depan.
 *
 * Pertanyaannya sederhana dan sangat penting: kalau admin menyimpan sesuatu di
 * panel, apakah pembeli benar-benar melihatnya?
 *
 * Jawabannya TIDAK selalu ya. Beranda membungkus isinya dalam cache berversi
 * (`CacheKatalog`), jadi perubahan yang tidak menaikkan versi itu tidak pernah
 * terlihat sampai cache kedaluwarsa. Diukur langsung: FAQ yang baru disimpan
 * lewat panel tidak muncul di beranda sebelum perbaikan ini.
 *
 * Yang diuji di sini adalah RANTAI ujung-ke-ujung — panel, basis data, cache,
 * lalu HTML yang dibaca pembeli — bukan sekadar "baris tersimpan".
 */
class IntegrasiAdminFrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache harus bersih sendiri: RefreshDatabase tidak menyentuhnya.
        Cache::flush();
        CacheKatalog::lupakanVersi();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    /** Teks yang tampil di beranda, sesudah cache dianggap tidak ada. */
    private function beranda(): string
    {
        CacheKatalog::lupakanVersi();

        return $this->get('/')->assertOk()->getContent();
    }

    public function test_faq_baru_dari_panel_muncul_di_beranda(): void
    {
        $this->actingAs($this->admin());

        // Panaskan cache LEBIH DULU. Tanpa langkah ini, beranda belum punya
        // entri cache dan test akan lolos walaupun invalidasinya tidak ada —
        // persis kegagalan yang membuat bug ini lolos selama ini.
        $this->get('/')->assertOk();

        Livewire::test(FaqPanel::class)
            ->call('bukaForm')
            ->set('pertanyaan_id', 'Apakah bisa kirim luar negeri?')
            ->set('jawaban_id', 'Bisa, ongkir dihitung manual.')
            ->call('simpan')
            ->assertHasNoErrors();

        $faq = Faq::query()->where('question_id', 'Apakah bisa kirim luar negeri?')->firstOrFail();

        // Beranda hanya menampilkan beberapa butir pertama; taruh paling atas
        // supaya test menguji INTEGRASI, bukan urutan tampil.
        $faq->update(['display_order' => 0]);

        $this->assertStringContainsString('Apakah bisa kirim luar negeri?', $this->beranda());
    }

    public function test_faq_yang_dinonaktifkan_hilang_dari_beranda(): void
    {
        $this->actingAs($this->admin());

        $faq = Faq::query()->create([
            'ulid' => (string) Str::ulid(),
            'group_key' => 'umum',
            'question_id' => 'Pertanyaan yang akan dimatikan?',
            'answer_id' => 'Jawaban.',
            'display_order' => 0,
            'is_active' => true,
            'tampil_di_beranda' => true,
        ]);

        // Panaskan cache dengan FAQ ini sudah tampil.
        $this->assertStringContainsString('Pertanyaan yang akan dimatikan?', $this->beranda());

        Livewire::test(FaqPanel::class)
            ->call('ubahAktif', $faq->id)
            ->assertHasNoErrors();

        $this->assertStringNotContainsString('Pertanyaan yang akan dimatikan?', $this->beranda());
    }

    public function test_kategori_nonaktif_hilang_dari_beranda_setelah_cache_panas(): void
    {
        $this->actingAs($this->admin());

        $kategori = Category::query()->create([
            'name_id' => 'Kategori Uji Integrasi',
            'slug' => 'kategori-uji-integrasi',
            'display_order' => 0,
            'is_active' => true,
        ]);

        $this->assertStringContainsString('Kategori Uji Integrasi', $this->beranda());

        Livewire::test(Kategori::class)
            ->call('ubahAktif', $kategori->id)
            ->assertHasNoErrors();

        $this->assertStringNotContainsString('Kategori Uji Integrasi', $this->beranda());
    }
}
