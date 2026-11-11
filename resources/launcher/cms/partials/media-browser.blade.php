{{--
    The list of the media library with its folders, the same in the picker (6.3) and the media library (7.1): the folders
    with the number of their files ("Alle bestanden" on top), the upload field when the host gives one, a search, the
    filters and the tiles. A host includes it with $browser = MediaBrowser::make() (or the browser of
    MediaBrowser::picker()) and $empty, the text for a list without files, and loads $browser['script']. Its fixed texts
    are here; media.js fills in the folders and tiles from the templates below and the JSON of the endpoints.
--}}
@php($t = $browser['t'])
<div class="mediabrowser" data-media data-config="{{ json_encode($browser['config'], JSON_THROW_ON_ERROR) }}">
    <div class="mediabrowser-zij">
        <nav class="mediabrowser-mappen" aria-label="{{ $t('folders') }}">
            <ul data-slot="media-folders">
                <li>
                    <button type="button" class="kmap" aria-pressed="true" data-folder="">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        <span class="kmap-naam">{{ $t('all_files') }}</span>
                        <span class="kmap-aantal" data-slot="media-folder-count"></span>
                    </button>
                </li>
            </ul>
        </nav>
        @if ($browser['upload'] !== null)
            <div class="mediabrowser-upload">
                @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $browser['upload']])
            </div>
        @endif
    </div>

    <div class="mediabrowser-hoofd">
        <div class="mediabrowser-balk">
            <div class="zoek mediabrowser-zoek">
                <label class="sr-only" for="made-cms-media-zoek">{{ $t('search') }}</label>
                <span class="zoek-icoon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></span>
                <input class="zoekveld" id="made-cms-media-zoek" type="search" autocomplete="off" placeholder="{{ $t('search_placeholder') }}" data-slot="media-search">
            </div>
            <div class="mediabrowser-filters" role="group" aria-label="{{ $t('filters') }}">
                @if ($browser['kind'] !== null)
                    <span class="filter vast" data-slot="media-kind">{{ $browser['kind'] }}</span>
                @endif
                <button type="button" class="filter" aria-pressed="true" data-slot="media-newest">{{ $t('newest') }}</button>
                <button type="button" class="filter" aria-pressed="false" data-slot="media-without-alt"><span class="filter-bol" aria-hidden="true"></span>{{ $t('without_alt') }}</button>
            </div>
        </div>

        <p class="sr-only" role="status" data-slot="media-status"></p>
        <div class="mediabrowser-tegels" role="group" aria-label="{{ $t('files') }}" data-slot="media-grid"></div>
        <p class="mediabrowser-leeg" data-slot="media-empty" hidden>{{ $empty }}</p>
        <p class="veld-fout" data-slot="media-failed" hidden></p>
        <button type="button" class="actie mediabrowser-meer" data-slot="media-more" hidden>{{ $t('more') }}</button>
    </div>

    {{-- A folder of the list, below "Alle bestanden". --}}
    <template data-media-folder>
        <li>
            <button type="button" class="kmap" aria-pressed="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                <span class="kmap-naam" data-slot="media-folder-name"></span>
                <span class="kmap-aantal" data-slot="media-folder-count"></span>
            </button>
        </li>
    </template>

    {{-- A file of the list: its thumbnail with the extension, a check on the choice and an amber dot without an alt text. --}}
    <template data-media-tile>
        <button type="button" class="tegel" aria-pressed="false">
            <span class="tegel-beeld">
                <svg class="tegel-icoon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
                <img alt="" loading="lazy" decoding="async" data-slot="media-tile-thumb" hidden>
                <span class="beeldkaart-ext" data-slot="media-tile-extension"></span>
                <span class="tegel-vink" aria-hidden="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5L20 7"/></svg></span>
                <span class="beeldkaart-stip" role="img" aria-label="{{ $t('no_alt') }}" data-slot="media-tile-no-alt" hidden></span>
            </span>
            <span class="tegel-tekst">
                <span class="tegel-naam" data-slot="media-tile-name"></span>
                <span class="tegel-meta" data-slot="media-tile-meta"></span>
            </span>
        </button>
    </template>
</div>
