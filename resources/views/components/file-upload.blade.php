@props([
    'model',
    'accept' => null,
    'multiple' => false,
    'crop' => null,
    'id' => null,
    'title' => 'Choose a file or drop it here',
    'hint' => null,
    'loading' => 'Reading file…',
])

{{--
    Dropzone unggah berkas.

    Input file bawaan peramban tidak dapat ditata dan hampir tak terlihat,
    terutama pada tema gelap. Komponen ini membungkusnya dalam kotak
    putus-putus yang jelas dapat diklik dan menerima seret-lepas; perilaku
    seret-lepas ditangani sekali di resources/js/app.js lewat atribut
    `data-dropzone`.

    Nama berkas yang dipilih ditulis oleh JS ke `[data-upload-name]`, karena
    nilai `input.files` tidak dapat dibaca dari Blade. Livewire tetap memakai
    input aslinya, termasuk pemotongan gambar `data-crop`.
--}}
<label {{ $attributes->class(['upload']) }} data-dropzone>
    <input @if ($id) id="{{ $id }}" @endif
           type="file"
           wire:model="{{ $model }}"
           @if ($accept) accept="{{ $accept }}" @endif
           @if ($multiple) multiple @endif
           @if ($crop) data-crop="{{ $crop }}" @endif>

    <span class="upload-icon" aria-hidden="true">
        <x-ikon name="upload" :size="22" />
    </span>
    <span class="upload-title">{{ $title }}</span>

    @if (filled($hint))
        <span class="upload-hint">{{ $hint }}</span>
    @endif

    @if (! $slot->isEmpty())
        <span class="upload-hint">{{ $slot }}</span>
    @endif

    <span class="upload-name" data-upload-name hidden></span>

    <span class="upload-loading" wire:loading wire:target="{{ $model }}">
        {{ $loading }}
    </span>
</label>
