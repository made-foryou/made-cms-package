{{--
    The side column of the media library (design 13.1b–q): the chosen file with its thumbnail, its name and what it is,
    its alt text with "Opslaan", the pages it is used on with a link to their edit shell, and "Bijsnijden" for a photo.
    Without a choice it says what choosing does. library.js fills it in from the file the list chose and from
    GET made-cms/media/{media}; its slots are named details-*.
--}}
@php($m = static fn (string $key): string => (string) __('made-cms-package::media.'.$key, [], \Made\CmsPackage\Support\Locale::launcher()))
@php($l = static fn (string $key): string => $m('library.'.$key))
<aside class="bibliotheek-zij" id="made-cms-media-zij" aria-label="{{ $l('chosen') }}" data-media-details>
    <div class="bibliotheek-geenkeuze" data-slot="details-nothing">
        <span class="mediabrowser-start-icoon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg></span>
        <span>{{ $l('nothing') }}</span>
    </div>

    <div class="bibliotheek-keuze" data-slot="details-file" hidden>
        <a class="bibliotheek-terug" href="#made-cms-media-bestanden" data-slot="details-back"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>{{ $l('back') }}</a>

        <div class="bibliotheek-mini" data-slot="details-mini">
            <svg class="tegel-icoon tegel-icoon-image" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
            <svg class="tegel-icoon tegel-icoon-document" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h5"/><path d="M10 17h5"/></svg>
            <svg class="tegel-icoon tegel-icoon-video" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/></svg>
            <img alt="" decoding="async" data-slot="details-thumb" hidden>
            <span class="beeldkaart-ext" data-slot="details-extension"></span>
            <span class="beeldkaart-stip" role="img" aria-label="{{ $m('browser.no_alt') }}" data-slot="details-no-alt" hidden></span>
        </div>

        <div class="bibliotheek-titel">
            <span class="eyebrow">{{ $l('eyebrow') }}</span>
            <h2 id="made-cms-media-gekozen" data-slot="details-name"></h2>
            <span class="bibliotheek-klein" data-slot="details-line"></span>
        </div>

        <div class="bibliotheek-alt">
            <label class="veldlabel" for="made-cms-media-alt">{{ $l('alt') }}</label>
            <textarea id="made-cms-media-alt" class="bibliotheek-alt-veld" rows="2" maxlength="1000" aria-describedby="made-cms-media-alt-hint" data-slot="details-alt"></textarea>
            <span class="bibliotheek-klein" id="made-cms-media-alt-hint" data-slot="details-alt-hint"></span>
            <div class="bibliotheek-opslaan">
                <button type="button" class="knop stil" data-slot="details-save" disabled>{{ $l('save') }}</button>
                <span class="bibliotheek-klein" data-slot="details-changed" hidden></span>
                <span class="bibliotheek-opgeslagen" role="status" data-slot="details-status"></span>
            </div>
        </div>

        <div class="bibliotheek-gebruik">
            <span class="veldlabel" data-slot="details-usage-heading">{{ $l('usage') }}</span>
            <div class="bibliotheek-laden" data-slot="details-loading" hidden>
                <span class="bibliotheek-klein" role="status" data-slot="details-loading-text"></span>
                <span class="bibliotheek-skelet" aria-hidden="true"></span>
                <span class="bibliotheek-skelet kort" aria-hidden="true"></span>
            </div>
            <span class="bibliotheek-nergens" data-slot="details-nowhere" hidden>{{ $l('nowhere') }}</span>
            <div data-slot="details-pages"></div>
        </div>

        <div class="bibliotheek-acties" data-slot="details-actions" hidden>
            <button type="button" class="actie" data-slot="details-crop">{{ $l('crop') }}</button>
        </div>
    </div>

    {{-- A page the chosen file is used on: a link to its edit shell (none in the bin), a note, and its places. --}}
    <template data-details-page>
        <div class="bibliotheek-pagina">
            <a class="bibliotheek-paginalink" data-slot="details-page-link"><span data-slot="details-page-title"></span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
            <span class="bibliotheek-paginanaam" data-slot="details-page-name" hidden></span>
            <span class="bibliotheek-noot" data-slot="details-page-note" hidden></span>
            <span data-slot="details-page-places"></span>
        </div>
    </template>
</aside>
