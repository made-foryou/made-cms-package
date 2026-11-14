@extends('made-cms-package-launcher::cms.layout', ['title' => $screen->label()])

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            <span class="eyebrow">{{ $screen->group()->label() }}</span>
            <h1 id="made-cms-titel">{{ $screen->label() }}</h1>
            <p class="intro">{{ $t('media.library.intro') }}</p>
        </div>
        <button type="button" class="knop" data-slot="library-upload" data-library-upload>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 16V4"/><path d="M6 10l6-6 6 6"/><path d="M4 20h16"/></svg>
            {{ $t('media.library.upload') }}
        </button>
    </section>

    {{-- The list, the side column and the layer files dropped from the computer land on, in one grid (design 13.1). --}}
    <div class="bibliotheek" data-media-library data-config="{{ json_encode($library, JSON_THROW_ON_ERROR) }}">
        @include('made-cms-package-launcher::cms.partials.media-browser', ['browser' => $browser, 'empty' => $t('media.library.empty')])
        @include('made-cms-package-launcher::cms.partials.media-details')
        <div class="bibliotheek-drop" data-slot="library-drop" hidden>
            <span class="bibliotheek-drop-kaart">
                <span class="bibliotheek-drop-icoon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 4v12"/><path d="M6 10l6 6 6-6"/><path d="M4 20h16"/></svg></span>
                <span class="bibliotheek-drop-tekst">
                    <strong data-slot="library-drop-title"></strong>
                    <span data-slot="library-drop-line"></span>
                </span>
            </span>
        </div>
    </div>

    @include('made-cms-package-launcher::cms.partials.crop', ['crop' => $crop])
@endsection

@push('scripts')
    {{-- library.js before media.js: it hears the file the list chooses when it opens. --}}
    <script type="module" src="{{ $browser['upload']['script'] ?? '' }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $crop['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $libraryScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $browser['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
@endpush
