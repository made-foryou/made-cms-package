{{--
    The list of the media library with its folders, the same in the picker (6.3) and the media library (7.1): the folders
    with the number of their files, the upload field when the host gives one, a search, the filters and the tiles. A
    host includes it with $browser = MediaBrowser::make() (the browser of MediaBrowser::picker(), or
    MediaBrowser::library()) and $empty, the text for a list without files, and loads $browser['script']. The picker
    has the folders on the left; the library (design 13.1) has the search, its filters with their numbers and the
    folders as chips across the top, the files below with a trail, "Nieuwste eerst", tiles or a list, and a start for an
    empty library. Its fixed texts are here; media.js fills in the folders and tiles from the templates below and the
    JSON of the endpoints. The folder and the tile are one template for both.
--}}
@php
    $t = $browser['t'];
    $library = $browser['library'];
    $l = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::media.library.'.$key, $replace, \Made\CmsPackage\Support\Locale::launcher());
    // A text with a number in it, the number in a slot of its own so the script can change it.
    $counted = static fn (string $text, string $slot, int $count): string => str_replace(
        e(':count'),
        '<span data-slot="'.$slot.'">'.$count.'</span>',
        e($text),
    );
@endphp
<div @class(['mediabrowser', 'mediabrowser-bibliotheek' => $library]) data-media data-config="{{ json_encode($browser['config'], JSON_THROW_ON_ERROR) }}"@if ($library) data-view="tiles"@endif>
@if ($library)
    @php
        $counts = $browser['config']['initial']['folders']['counts'];
        $filter = $browser['config']['filter'];
        $kinds = ['image', 'document', 'video'];
    @endphp
    <div class="mediabrowser-start" data-slot="media-start" hidden>
        <span class="mediabrowser-start-icoon" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg></span>
        <strong>{{ $l('start_title') }}</strong>
        <span>{{ $l('start_text') }}</span>
        <button type="button" class="knop stil" data-library-upload>{{ $l('upload') }}</button>
        <span class="mediabrowser-klein">{{ $l('start_drop', ['limits' => rtrim($browser['upload']['limits'] ?? '', '.')]) }}</span>
    </div>

    <section class="mediabrowser-balk" aria-label="{{ $l('search_filter') }}">
        <div class="zoek mediabrowser-zoek">
            <label class="sr-only" for="made-cms-media-zoek">{{ $t('search') }}</label>
            <span class="zoek-icoon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></span>
            <input class="zoekveld" id="made-cms-media-zoek" type="search" autocomplete="off" placeholder="{{ $t('search_placeholder') }}" value="{{ $filter['q'] }}" data-slot="media-search">
        </div>
        <div class="mediabrowser-filters" role="group" aria-label="{{ $t('filters') }}" data-slot="media-kinds">
            <button type="button" class="filter" aria-pressed="{{ $filter['kind'] === null && ! $filter['without_alt'] ? 'true' : 'false' }}" data-kind="">{!! $counted($l('all'), 'media-kind-count', $counts['all']) !!}</button>
            @foreach ($kinds as $kind)
                <button type="button" class="filter" aria-pressed="{{ $filter['kind'] === $kind ? 'true' : 'false' }}" data-kind="{{ $kind }}">{{ $t('kinds.'.$kind) }} <span data-slot="media-kind-count">{{ $counts[$kind] }}</span></button>
            @endforeach
            <button type="button" class="filter" aria-pressed="{{ $filter['without_alt'] ? 'true' : 'false' }}" data-slot="media-without-alt"><span class="filter-bol" aria-hidden="true"></span>{!! $counted($l('without_alt'), 'media-without-alt-count', $counts['without_alt']) !!}</button>
        </div>
        <nav class="mediabrowser-mappen" aria-label="{{ $t('folders') }}">
            <ul data-slot="media-folders">
                {{-- "Alle bestanden" is the start of the trail here, not a chip. --}}
                <li hidden>
                    <button type="button" class="kmap" aria-pressed="true" data-folder="">
                        <span class="kmap-naam">{{ $t('all_files') }}</span>
                        <span class="kmap-aantal" data-slot="media-folder-count"></span>
                    </button>
                </li>
            </ul>
        </nav>

        {{-- One field for a new folder and for a new name of the open folder (design 13.1ab–ag), below the chips. --}}
        <div class="mapveld" id="made-cms-media-mapveld" role="group" data-slot="media-folder-field" hidden>
            <div class="mapveld-rij">
                <label class="veldlabel" for="made-cms-media-mapnaam" data-slot="media-folder-label"></label>
                <input class="mapveld-naam" id="made-cms-media-mapnaam" type="text" autocomplete="off" maxlength="255" aria-describedby="made-cms-media-mapnaam-hint" data-slot="media-folder-input">
                <button type="button" class="actie" data-slot="media-folder-confirm"></button>
                <button type="button" class="tekstlink" data-slot="media-folder-cancel">{{ $l('cancel') }}</button>
            </div>
            <span class="mediabrowser-klein" id="made-cms-media-mapnaam-hint" data-slot="media-folder-hint"></span>
        </div>

        {{-- What a folder or a move did, until the editor closes it, the next message comes, or another folder or filter. --}}
        <div class="mediabrowser-meldplek" role="status" data-slot="media-notice"></div>
        <template data-media-notice>
            <div class="melding">
                <span class="bol" aria-hidden="true"></span>
                <span class="melding-tekst" data-slot="media-notice-text"></span>
                <button type="button" class="tekstlink" data-slot="media-notice-link" hidden></button>
                <button type="button" class="mediabrowser-kruis" aria-label="{{ $l('close_notice') }}" title="{{ $l('close_notice') }}" data-slot="media-notice-close">×</button>
            </div>
        </template>
    </section>

    <section class="mediabrowser-bestanden" id="made-cms-media-bestanden" aria-label="{{ $t('files') }}" data-slot="media-files">
        <div class="mediabrowser-kop">
            <nav class="mediabrowser-kruimel" aria-label="{{ $l('trail') }}">
                <button type="button" class="tekstlink" data-slot="media-trail-all" hidden>{{ $t('all_files') }}</button>
                <span class="mediabrowser-kruimel-streep" aria-hidden="true" data-slot="media-trail-separator" hidden>/</span>
                <span class="mediabrowser-kruimel-hier" data-slot="media-trail-here">{{ $t('all_files') }}</span>
                <span aria-hidden="true">·</span>
                <span role="status" data-slot="media-status">{{ $browser['config']['initial']['files']['summary'] }}</span>
            </nav>
            <span class="mediabrowser-mapacties" data-slot="media-folder-actions" hidden>
                <button type="button" class="tekstlink" aria-controls="made-cms-media-mapveld" data-slot="media-rename">{{ $l('folder_rename') }}</button>
                <button type="button" class="tekstlink" data-slot="media-delete">{{ $l('folder_delete') }}</button>
            </span>
            <div class="mediabrowser-weergave">
                <span class="mediabrowser-ordening">
                    <button type="button" class="actie" aria-pressed="{{ $filter['sort'] === 'newest' ? 'true' : 'false' }}" data-slot="media-newest"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 4v16"/><path d="M3 16l4 4 4-4"/><path d="M13 6h8"/><path d="M13 11h6"/><path d="M13 16h4"/></svg>{{ $l('newest') }}</button>
                    <span class="mediabrowser-weergaven" role="group" aria-label="{{ $l('view') }}">
                        <button type="button" class="actie" aria-pressed="true" data-slot="media-view-tiles">{{ $l('tiles') }}</button>
                        <button type="button" class="actie" aria-pressed="false" data-slot="media-view-list">{{ $l('list') }}</button>
                    </span>
                </span>
                <button type="button" class="actie mediabrowser-selecteer" aria-pressed="false" data-slot="media-select"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg>{{ $l('select') }}</button>
            </div>
        </div>

        @if ($browser['upload'] !== null)
            <div class="mediabrowser-upload" data-slot="media-upload" hidden>
                @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $browser['upload']])
            </div>
        @endif

        <div class="mediabrowser-lijstkop" aria-hidden="true">
            <span class="kolom-vakje"></span>
            <span></span>
            <span>{{ $l('head.name') }}</span>
            <span class="kolom-soort">{{ $l('head.kind') }}</span>
            <span class="kolom-maat">{{ $l('head.size') }}</span>
            <span class="kolom-map">{{ $l('head.folder') }}</span>
            <span class="kolom-alt">{{ $l('head.alt') }}</span>
        </div>
        <div class="mediabrowser-tegels" role="group" aria-label="{{ $t('files') }}" data-slot="media-grid"></div>
        <div class="mediabrowser-leeg" data-slot="media-empty" hidden>
            <strong data-slot="media-empty-title"></strong>
            <span data-slot="media-empty-text"></span>
            <button type="button" class="knop stil" data-slot="media-empty-upload" data-library-upload hidden>{{ $l('upload') }}</button>
            <button type="button" class="tekstlink" data-slot="media-empty-link" hidden></button>
        </div>
        <p class="veld-fout" data-slot="media-failed" hidden></p>
        <button type="button" class="actie mediabrowser-meer" data-slot="media-more" hidden>{{ $t('more') }}</button>
        <p class="mediabrowser-noot" data-slot="media-note" hidden></p>
    </section>
@else
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
        {{-- The field of one photo, the field of several photos when the picker opens from an item of a list, or the field
             of one file for a file field: the script shows one of them each time the picker opens, so a field never
             changes while files are on their way. --}}
        @if ($browser['upload'] !== null)
            <div class="mediabrowser-upload" data-picker-upload="one">
                @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $browser['upload']])
            </div>
        @endif
        @if ($browser['upload_many'] !== null)
            <div class="mediabrowser-upload" data-picker-upload="many" hidden>
                @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $browser['upload_many']])
            </div>
        @endif
        {{-- The field of one file for each stand of a file field: a document, a video or either. --}}
        @foreach ($browser['upload_files'] ?? [] as $mode => $field)
            <div class="mediabrowser-upload" data-picker-upload="{{ $mode }}" hidden>
                @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $field])
            </div>
        @endforeach
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

        @if ($browser['upload_many'] !== null)
            {{-- A picker opened from an item of a list chooses several photos (design 13.4b–p): the button and what is
                 chosen, the messages of the moment and, below the tiles, how to choose. media.js fills them in. --}}
            <div class="kiezer-stand" data-slot="media-picker-stand" hidden>
                <span class="kiezer-stand-tekst" role="status" data-slot="media-picker-status"></span>
                <span class="kiezer-stand-knop">
                    <span class="kiezer-vol" data-slot="media-picker-full" hidden></span>
                    <button type="button" class="actie kiezer-meer" aria-pressed="false" data-slot="media-picker-many"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg>{{ $browser['config']['texts']['many'] ?? '' }}</button>
                </span>
            </div>
            <div class="meldingen kiezer-meldingen" data-slot="media-picker-notices"></div>
            <template data-picker-notice>
                <div class="melding let" role="status"><span class="bol let" aria-hidden="true"></span><span class="melding-tekst"></span></div>
            </template>
        @endif
        <p class="sr-only" role="status" data-slot="media-status"></p>
        <div class="mediabrowser-tegels" role="group" aria-label="{{ $t('files') }}" data-slot="media-grid"></div>
        <p class="mediabrowser-leeg" data-slot="media-empty" hidden>{{ $empty }}</p>
        <p class="veld-fout" data-slot="media-failed" hidden></p>
        <button type="button" class="actie mediabrowser-meer" data-slot="media-more" hidden>{{ $t('more') }}</button>
        @if ($browser['upload_many'] !== null)
            <p class="kiezer-hint" data-slot="media-picker-hint">{{ $browser['config']['texts']['outside'] ?? '' }}</p>
        @endif
    </div>
@endif

    {{-- A folder of the list, below "Alle bestanden" in the picker, a chip in the library. --}}
    <template data-media-folder>
        <li>
            <button type="button" class="kmap" aria-pressed="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                <span class="kmap-naam" data-slot="media-folder-name"></span>
                <span class="kmap-gekozen" role="img" data-slot="media-folder-chosen" hidden></span>
                <span class="kmap-aantal" data-slot="media-folder-count"></span>
            </button>
        </li>
    </template>

    {{--
        A file of the list: its thumbnail (an icon for a document or a video) with the extension, a check on the choice
        in the picker and an amber dot without an alt text. The kind, the folder and the alt text show only as a row of
        the list in the library, and the round box of a selection only while the library selects; in a picker of a
        list it holds the number of the photo, and a photo uploaded there says it is new.
    --}}
    <template data-media-tile>
        <button type="button" class="tegel" aria-pressed="false">
            <span class="tegel-vakje" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5L20 7"/></svg><span class="tegel-nummer" data-slot="media-tile-number"></span></span>
            <span class="tegel-beeld">
                <svg class="tegel-icoon tegel-icoon-image" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
                <svg class="tegel-icoon tegel-icoon-document" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h5"/><path d="M10 17h5"/></svg>
                <svg class="tegel-icoon tegel-icoon-video" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/></svg>
                <img alt="" loading="lazy" decoding="async" data-slot="media-tile-thumb" hidden>
                <span class="beeldkaart-ext" data-slot="media-tile-extension"></span>
                <span class="tegel-vink" aria-hidden="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5L20 7"/></svg></span>
                <span class="beeldkaart-stip" role="img" aria-label="{{ $t('no_alt') }}" data-slot="media-tile-no-alt" hidden></span>
                <span class="tegel-nieuw" data-slot="media-tile-new" hidden></span>
            </span>
            <span class="tegel-tekst">
                <span class="tegel-naam" data-slot="media-tile-name"></span>
                <span class="tegel-meta" data-slot="media-tile-meta"></span>
                <span class="tegel-kort" data-slot="media-tile-short"></span>
            </span>
            <span class="tegel-soort" data-slot="media-tile-kind"></span>
            <span class="tegel-maat" data-slot="media-tile-size"></span>
            <span class="tegel-map" data-slot="media-tile-folder"></span>
            <span class="tegel-alt" data-slot="media-tile-alt"></span>
        </button>
    </template>
</div>
