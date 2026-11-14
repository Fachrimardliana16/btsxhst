{{-- Halaman sunting satu slide hero.

     Dipisah dari daftar (livewire/admin/hero.blade.php) karena tuntutannya
     berbeda: daftar harus cepat dibuka dan sering dilihat, form ini penuh
     isian dan jarang dipakai. Menyatukannya membuat setiap kunjungan ke Hero
     ikut memuat dua bahasa, titik fokus, pratinjau, dan dua kolom unggahan.

     TIGA HAL YANG BERUBAH DARI VERSI LAMA:

     1. Alamat sendiri, /admin/hero/{ulid}/sunting — dapat disalin dan dibuka
        ulang, dan menyimpan tidak melempar admin kembali ke daftar.

     2. URL tombol TIDAK WAJIB. Slide yang hanya menampilkan gambar adalah
        keadaan yang sah. Label tombol yang diisi tanpa URL tetap disimpan
        sebagai label saja; tombolnya tidak dirender di sisi depan.

     3. {{ __('site.admin.hero_sunting.gambar') }} ponsel benar-benar OPSIONAL. Dulu, mengunggah gambar desktop
        tanpa gambar ponsel menghapus gambar ponsel yang sudah ada — padahal
        bila kosong slide memang memakai gambar desktop. Sekarang ada tombol
        eksplisit untuk membuangnya. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="part-no">Homepage · hero · order {{ $slide->display_order + 1 }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">
                {{ $slide->title_en ?: ($slide->title_id ?: '(untitled)') }}
            </h1>
            <p class="mt-1 text-sm text-muted">
                {{ $slide->media_type === 'video' ? __('site.admin.hero_sunting.video') : __('site.admin.hero_sunting.gambar') }} ·
                {{ $slide->is_active ? 'active' : 'inactive' }}
                @if ($slide->starts_at || $slide->ends_at)
                    · scheduled
                @endif
            </p>
        </div>

        <div class="flex flex-none items-center gap-1">
            <a href="{{ route('admin.hero.index') }}" wire:navigate class="btn-icon"
               title="{{ __('site.admin.hero_sunting.kembali') }}" aria-label="{{ __('site.admin.hero_sunting.kembali') }}">
                <x-ikon name="chevron-left" :size="18" />
            </a>
        </div>
    </div>

    @if ($pesanSukses)
        <div class="plate flex items-start gap-3 border-l-2 border-ok p-3 text-sm" role="status">
            <x-ikon name="check" :size="18" class="mt-0.5 flex-none text-ok" />
            <p class="flex-1">{{ $pesanSukses }}</p>
        </div>
    @endif

    @if ($galat)
        <div class="plate flex items-start gap-3 border-l-2 border-danger p-3 text-sm" role="alert">
            <x-ikon name="alert" :size="18" class="mt-0.5 flex-none text-danger" />
            <p class="flex-1">{{ $galat }}</p>
        </div>
    @endif

    <form wire:submit="simpan" class="grid gap-5 lg:grid-cols-[1fr_400px] lg:items-start">

        {{-- ══ Kolom kiri: isian ══════════════════════════════════════ --}}
        <div class="space-y-5">

            {{-- Slide copy --}}
            <div class="plate space-y-4 p-4">
                <div class="space-y-3">
                    <label class="block">
                        <span class="text-sm">{{ __('site.admin.hero_sunting.judul') }} <span class="text-danger">*</span></span>
                        <input type="text" wire:model="form.title_en" class="field mt-1" maxlength="120" />
                        @error('form.title_en') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">{{ __('site.admin.hero_sunting.subjudul') }}</span>
                        <textarea wire:model="form.subtitle_en" rows="2" class="field mt-1" maxlength="300"></textarea>
                        @error('form.subtitle_en') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>

            {{-- {{ __('site.admin.hero_sunting.tombol') }}: URL opsional --}}
            <div class="plate space-y-4 p-4">
                <div>
                    <h2 class="font-display text-lg font-semibold">{{ __('site.admin.hero_sunting.tombol') }}</h2>
                    <p class="mt-0.5 text-xs text-dim">
                        Both are optional. A slide without buttons still shows — fill in the label alone if the destination is not decided yet,
                        and the button is not rendered until its URL is filled in.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3">
                        <label class="block">
                            <span class="part-no">{{ __('site.admin.hero_sunting.label_utama_id') }}</span>
                            <input type="text" wire:model="form.primary_button_label_en" class="field mt-1" maxlength="40" />
                            @error('form.primary_button_label_en') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="part-no">{{ __('site.admin.hero_sunting.url_utama') }}</span>
                            <input type="text" wire:model="form.primary_button_url" class="field tech mt-1 text-xs"
                                   placeholder="{{ __('site.admin.hero_sunting.url_contoh_produk') }}" />
                            <span class="mt-1 block text-xs text-dim">{{ __('site.admin.hero_sunting.kosong_tombol') }}</span>
                            @error('form.primary_button_url') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="part-no">{{ __('site.admin.hero_sunting.label_kedua_id') }}</span>
                            <input type="text" wire:model="form.secondary_button_label_en" class="field mt-1" maxlength="40" />
                            @error('form.secondary_button_label_en') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="part-no">{{ __('site.admin.hero_sunting.url_kedua') }}</span>
                            <input type="text" wire:model="form.secondary_button_url" class="field tech mt-1 text-xs"
                                   placeholder="{{ __('site.admin.hero_sunting.url_contoh_kontak') }}" />
                            <span class="mt-1 block text-xs text-dim">{{ __('site.admin.hero_sunting.kosong_tombol') }}</span>
                            @error('form.secondary_button_url') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            </div>

            {{-- {{ __('site.admin.hero_sunting.media') }} --}}
            <div class="plate space-y-4 p-4">
                <h2 class="font-display text-lg font-semibold">{{ __('site.admin.hero_sunting.media') }}</h2>

                <label class="block">
                    <span class="part-no">{{ __('site.admin.hero_sunting.jenis_media') }}</span>
                    <select wire:model.live="form.media_type" class="field mt-2">
                        <option value="image">{{ __('site.admin.hero_sunting.gambar') }}</option>
                        <option value="video">{{ __('site.admin.hero_sunting.video') }}</option>
                    </select>
                </label>

                @if ($form['media_type'] === 'image')
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="block">
                            <span class="part-no">Desktop image</span>
                            <x-file-upload model="berkasGambar" crop="hero-desktop"
                                           accept="image/jpeg,image/png,image/webp,image/avif"
                                           title="Choose a desktop image or drop it here"
                                           class="mt-2">
                                @if (filled($slide->media_path))
                                    Already set. Upload only if you want to replace it.
                                @else
                                    Required for a new slide.
                                @endif
                            </x-file-upload>
                            @error('berkasGambar') <span class="field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="block">
                            <span class="part-no">Mobile image (optional)</span>
                            <x-file-upload model="berkasPonsel" crop="hero-mobile"
                                           accept="image/jpeg,image/png,image/webp,image/avif"
                                           title="Choose a mobile image or drop it here"
                                           class="mt-2">
                                @if (filled($slide->media_path_mobile))
                                    A dedicated mobile image is set. If removed, the slide uses the desktop image.
                                @else
                                    Leave empty if not needed — the slide uses the desktop image.
                                @endif
                            </x-file-upload>
                            @error('berkasPonsel') <span class="field-error">{{ $message }}</span> @enderror

                            @if (filled($slide->media_path_mobile))
                                <label class="mt-2 flex items-center gap-2 text-xs text-danger">
                                    <input type="checkbox" wire:model="hapusPonsel" class="h-3.5 w-3.5" />
                                    {{ __('site.admin.hero_sunting.buang_gambar_ponsel') }}
                                </label>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="block">
                        <span class="part-no">Video</span>
                        <x-file-upload model="berkasVideo" accept="video/mp4"
                                       title="Choose an MP4 video or drop it here"
                                       class="mt-2">
                            MP4, maximum {{ round($videoMaksKb / 1024, 1) }} MB and {{ $videoMaksDetik }} seconds.
                        </x-file-upload>
                        @error('berkasVideo') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            {{-- Titik fokus + jadwal --}}
            <div class="plate space-y-4 p-4">
                <div>
                    <h2 class="font-display text-lg font-semibold">{{ __('site.admin.hero_sunting.tampilan') }}</h2>
                    <p class="mt-0.5 text-xs text-dim">{{ __('site.admin.hero_sunting.titik_fokus_hint') }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="part-no">{{ __('site.admin.hero_sunting.titik_fokus_x') }}</span>
                        <input type="range" min="0" max="1" step="0.05" wire:model.live="form.focal_point_x" class="mt-2 w-full" />
                        <span class="tech mt-1 block text-xs text-muted">{{ round($form['focal_point_x'] * 100) }}%</span>
                    </label>

                    <label class="block">
                        <span class="part-no">{{ __('site.admin.hero_sunting.titik_fokus_y') }}</span>
                        <input type="range" min="0" max="1" step="0.05" wire:model.live="form.focal_point_y" class="mt-2 w-full" />
                        <span class="tech mt-1 block text-xs text-muted">{{ round($form['focal_point_y'] * 100) }}%</span>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="part-no">{{ __('site.admin.hero_sunting.mulai_tayang') }}</span>
                        <input type="datetime-local" wire:model="form.starts_at" class="field tech mt-2 text-xs" />
                        <span class="mt-1 block text-xs text-dim">{{ __('site.admin.hero_sunting.kosong_segera') }}</span>
                        @error('form.starts_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="part-no">{{ __('site.admin.hero_sunting.berakhir') }}</span>
                        <input type="datetime-local" wire:model="form.ends_at" class="field tech mt-2 text-xs" />
                        <span class="mt-1 block text-xs text-dim">{{ __('site.admin.hero_sunting.kosong_tanpa_batas') }}</span>
                        @error('form.ends_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpan">
                    <x-ikon name="check" :size="17" />
                    <span wire:loading.remove wire:target="simpan">{{ __('site.admin.hero_sunting.simpan_slide') }}</span>
                    <span wire:loading wire:target="simpan">{{ __('site.admin.hero_sunting.menyimpan') }}</span>
                </button>

                <button type="button" wire:click="batal" class="btn btn-outline">{{ __('site.admin.hero_sunting.batal') }}</button>

                <p class="text-xs text-dim">
                    {{ __('site.admin.hero_sunting.catatan_aktif') }}
                </p>
            </div>
        </div>

        {{-- ══ Kolom kanan: pratinjau ═════════════════════════════════ --}}
        <div class="space-y-3 lg:sticky lg:top-20 lg:h-fit">
            <div class="plate plate-lux space-y-3 p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="part-no">{{ __('site.admin.hero_sunting.pratinjau') }}</p>

                    <div class="flex gap-1">
                        <button type="button"
                                class="btn {{ $pratinjau === 'ponsel' ? 'btn-primary' : 'btn-ghost' }} btn-sm"
                                wire:click="ubahPratinjau('ponsel')">
                            <x-ikon name="phone" :size="15" /> {{ __('site.admin.hero_sunting.ponsel') }}
                        </button>
                        <button type="button"
                                class="btn {{ $pratinjau === 'desktop' ? 'btn-primary' : 'btn-ghost' }} btn-sm"
                                wire:click="ubahPratinjau('desktop')">
                            <x-ikon name="grid" :size="15" /> {{ __('site.admin.hero_sunting.desktop') }}
                        </button>
                    </div>
                </div>

                @php
                    // {{ __('site.admin.hero_sunting.gambar') }} ponsel hanya dipakai bila ADA. Kalau kosong, slide
                    // memakai gambar desktop — dan pratinjau menampilkan hal
                    // yang sama dengan yang dilihat pengunjung.
                    $berkasPratinjau = $slide->media_type === 'video'
                        ? ($slide->poster_path ?: $slide->media_path)
                        : ($pratinjau === 'ponsel' ? ($slide->media_path_mobile ?: $slide->media_path) : $slide->media_path);
                    $urlPratinjau = $this->urlMedia($berkasPratinjau);
                    $pakaiPonsel = $pratinjau === 'ponsel' && filled($slide->media_path_mobile);
                @endphp

                <div class="mx-auto overflow-hidden border border-line-soft bg-sunken
                            {{ $pratinjau === 'ponsel' ? 'w-[280px] rounded-[24px]' : 'w-full rounded-[var(--r-kecil)]' }}">
                    <div class="relative {{ $pratinjau === 'ponsel' ? 'aspect-[9/16]' : 'aspect-[21/9]' }} bg-carbon">
                        @if ($urlPratinjau)
                            <img src="{{ $urlPratinjau }}" alt=""
                                 class="absolute inset-0 h-full w-full object-cover"
                                 style="object-position: {{ round($form['focal_point_x'] * 100, 1) }}% {{ round($form['focal_point_y'] * 100, 1) }}%" />
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-dim">
                                <x-ikon name="image" :size="28" />
                            </div>
                        @endif

                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>

                        <div class="absolute inset-x-0 bottom-0 p-4">
                            <p class="font-display text-lg font-semibold text-white drop-shadow">
                                {{ $form['title_en'] ?: '(slide title)' }}
                            </p>
                            <p class="mt-1 line-clamp-2 text-xs text-white/80">
                                {{ $form['subtitle_en'] ?: '(subtitle)' }}
                            </p>

                            {{-- {{ __('site.admin.hero_sunting.tombol') }} hanya tampil bila URL-nya terisi — sama
                                 dengan perilaku di sisi depan. --}}
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($form['primary_button_label_en'] && trim((string) $form['primary_button_url']) !== '')
                                    <span class="badge" style="color:#fff">{{ $form['primary_button_label_en'] }}</span>
                                @endif
                                @if ($form['secondary_button_label_en'] && trim((string) $form['secondary_button_url']) !== '')
                                    <span class="badge" style="color:#fff">{{ $form['secondary_button_label_en'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center text-xs text-dim">
                    @if (! $urlPratinjau)
                        No file yet — the default layout is used.
                    @elseif ($pakaiPonsel)
                        Using the dedicated mobile image.
                    @elseif ($pratinjau === 'ponsel')
                        No mobile image — using the desktop image.
                    @else
                        Using the desktop image.
                    @endif
                </p>
            </div>
        </div>
    </form>
</div>
