@extends('made-cms-package-launcher::cms.layout', ['title' => $screen->label()])

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            <span class="eyebrow">{{ $screen->group()->label() }}</span>
            <h1 id="made-cms-titel">{{ $screen->label() }}</h1>
            <p class="intro">{{ $t('media.library.intro') }}</p>
        </div>
        <button type="button" class="knop" data-slot="library-upload">{{ $t('media.library.upload') }}</button>
    </section>

    <div class="bibliotheek" data-media-library>
        @include('made-cms-package-launcher::cms.partials.media-browser', ['browser' => $browser, 'empty' => $t('media.library.empty')])
    </div>

    @include('made-cms-package-launcher::cms.partials.crop', ['crop' => $crop])
@endsection

@push('scripts')
    <script type="module" src="{{ $browser['upload']['script'] ?? '' }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $browser['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $crop['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
@endpush
