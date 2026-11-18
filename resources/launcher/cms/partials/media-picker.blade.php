{{--
    The picker of the side panel (design 13.4, 14.4): the list of the media library in a modal dialog above the edit
    shell, never in the frame of the site. A host includes it once with $picker = MediaBrowser::picker($folder) and loads
    $picker['browser']['script']; the side panel opens it with made-cms:pick on the dialog, for an item of a list with
    the number of photos the list takes (many), so it chooses several, and for a file field with its kinds, so it shows
    only those (the stand: document, video or file). Its slots are named media-picker-*, so the side
    panel, which takes the first slot of a name in the shell, never finds them.
--}}
@php($t = $picker['t'])
@php($texts = $picker['config']['texts'])
<dialog class="kiezer" data-media-picker aria-modal="true" aria-labelledby="made-cms-kiezer-kop" aria-describedby="made-cms-kiezer-voor" data-config="{{ json_encode($picker['config'], JSON_THROW_ON_ERROR) }}">
    <div class="kiezer-kop">
        <div class="kiezer-titel">
            <span class="eyebrow">{{ $t('eyebrow') }}</span>
            <h2 id="made-cms-kiezer-kop" data-slot="media-picker-title">{{ $t('title') }}</h2>
            <span class="kiezer-voor" id="made-cms-kiezer-voor" data-slot="media-picker-for"></span>
        </div>
        <button type="button" class="kiezer-sluit" aria-label="{{ $t('close') }}" data-slot="media-picker-close"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    </div>

    @include('made-cms-package-launcher::cms.partials.media-browser', ['browser' => $picker['browser'], 'empty' => $t('empty')])

    <div class="kiezer-voet">
        <div class="kiezer-keuze" data-slot="media-picker-one">
            <span class="kiezer-mini" aria-hidden="true" data-kind="image" data-slot="media-picker-mini">
                <svg class="tegel-icoon-image" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
                <svg class="tegel-icoon-document" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h5"/><path d="M10 17h5"/></svg>
                <svg class="tegel-icoon-video" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/></svg>
                <img alt="" data-slot="media-picker-thumb" hidden>
            </span>
            <span class="kiezer-keuze-tekst">
                <span class="kiezer-naam" data-slot="media-picker-name">{{ $texts['nothing'] ?? '' }}</span>
                <span class="veld-hint" data-slot="media-picker-alt">{{ $texts['nothing_hint'] ?? '' }}</span>
            </span>
        </div>
        {{-- While a picker of a list selects: what is chosen, in order, and where it goes (design 13.4c). --}}
        <div class="kiezer-selectie" data-slot="media-picker-selection" hidden>
            <span class="kiezer-selectie-kop" data-slot="media-picker-chosen"></span>
            <span class="veld-hint" data-slot="media-picker-where"></span>
            <ul class="kiezer-chips" aria-label="{{ $picker['browser']['config']['texts']['chosen_list'] ?? '' }}" data-slot="media-picker-chips"></ul>
        </div>
        <div class="kiezer-knoppen">
            <button type="button" class="tekstlink" data-slot="media-picker-cancel">{{ $t('cancel') }}</button>
            <button type="button" class="knop" aria-disabled="true" data-slot="media-picker-use">{{ $texts['choose_first'] ?? '' }}</button>
        </div>
    </div>
    <template data-picker-chip>
        <li class="kiezer-chip">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
            <img alt="" data-slot="media-picker-chip-thumb" hidden>
            <span class="kiezer-chip-nummer" aria-hidden="true" data-slot="media-picker-chip-number"></span>
            <button type="button" class="kiezer-chip-weg" data-slot="media-picker-chip-remove"><span aria-hidden="true">×</span></button>
        </li>
    </template>
</dialog>
