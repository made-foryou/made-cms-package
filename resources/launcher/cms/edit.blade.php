{{--
    The edit shell: the bar with the page and its status, the site in a frame of the same origin, and the side panel.
    edit.js draws an outline over every [data-cms-strip] in the frame from the template below; the side panel shows the
    opbouw of the page (structure.js) or the fields of the chosen strip (panel.js).
--}}
@php
    $title = $t('cms.edit.title', ['page' => $page['title']]);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
@include('made-cms-package-launcher::cms.partials.head')
</head>
<body class="bewerken">
<div class="bewerkschil" data-edit-shell>
    <header class="bewerkbalk" role="region" aria-label="{{ $t('cms.edit.bar') }}">
        <div class="bewerkbalk-links">
            <a class="bewerkbalk-merk" href="{{ $startUrl }}" aria-label="{{ $t('cms.edit.start') }}">
                <svg viewBox="0 0 120 120" width="28" height="28" aria-hidden="true" focusable="false"><rect width="120" height="120" rx="34" fill="#f3f1e8"/><path d="M45 92 L75 28" fill="none" stroke="#be8a1c" stroke-width="21" stroke-linecap="round"/></svg>
            </a>
            <span class="bewerkbalk-site">{{ $site }}</span>
            <span class="bewerkbalk-slash" aria-hidden="true">/</span>
            <span class="bewerkbalk-pagina">{{ $page['title'] }}</span>
            <span class="status{{ $page['live'] ? ' live' : '' }}" data-slot="bar-page">{{ $t('pages.status.'.$page['status']) }}</span>
        </div>
        {{-- bar.js keeps the state of the draft up to date and publishes or discards it. --}}
        <div class="bewerkbalk-rechts">
            <span class="bewerkbalk-stand" role="status" data-slot="bar-state">{{ $draft['label'] }}</span>
            <details class="bevestig bewerkbalk-weggooien" data-slot="bar-discard"{{ $draft['discardable'] ? '' : ' hidden' }}>
                <summary class="bewerkbalk-link">{{ $t('cms.edit.discard') }}</summary>
                <div class="bewerkbalk-vlak">
                    <p data-slot="bar-discard-text">{{ $draft['discard_text'] }}</p>
                    <button type="button" class="knop gevaar" data-slot="bar-discard-confirm">{{ $t('cms.edit.discard_confirm') }}</button>
                </div>
            </details>
            @if ($previewUrl !== null)
                <a class="bewerkbalk-link" href="{{ $previewUrl }}" target="_blank" rel="noopener">{{ $t('cms.edit.preview') }}</a>
            @endif
            <button type="button" class="bewerkbalk-knop" data-slot="bar-publish"{{ $draft['publishable'] ? '' : ' disabled' }}>{{ $t('cms.edit.publish') }}</button>
            <span class="avatar" aria-hidden="true">{{ $editor['initial'] ?? '' }}</span>
        </div>
    </header>
    <div class="bewerkromp">
        @if ($frameUrl !== null)
            <iframe class="bewerkframe" src="{{ $frameUrl }}" title="{{ $t('cms.edit.frame', ['page' => $page['title']]) }}" name="made-cms-site"></iframe>
        @else
            <div class="bewerkframe bewerkframe-leeg"><p class="intro">{{ $t('cms.edit.no_address') }}</p></div>
        @endif
        <aside class="bewerkpaneel" aria-label="{{ $t('cms.edit.panel') }}">
            {{-- structure.js fills the head for the opbouw, the catalogue and the chosen strip. --}}
            <div class="bewerkpaneel-kop" data-slot="head">
                <div class="bewerkpaneel-titel">
                    <span class="eyebrow" data-slot="head-eyebrow">{{ $t('cms.edit.structure') }}</span>
                    <h2 data-slot="head-name">{{ $page['title'] }}</h2>
                    <span class="bewerkpaneel-sub" data-slot="head-sub">{{ $counts }} · {{ $t('cms.edit.structure_texts.sub_structure') }}</span>
                </div>
                <span class="bewerkpaneel-positie" data-slot="head-position">{{ $t('cms.edit.structure_texts.page') }}</span>
            </div>
            @if ($headingLevels)
                {{-- Whoever chooses the level of a heading switches between the opbouw and the headings of the page (design 11.21). --}}
                <div class="tabs" role="tablist" aria-label="{{ $t('cms.edit.tabs') }}" data-slot="head-tabs">
                    <button type="button" class="tab" role="tab" id="paneel-tab-opbouw" aria-selected="true" aria-controls="paneel-opbouw" data-slot="head-tab-structure">{{ $t('cms.edit.structure') }}</button>
                    <button type="button" class="tab" role="tab" id="paneel-tab-koppen" aria-selected="false" aria-controls="paneel-koppen" tabindex="-1" data-slot="head-tab-headings">{{ $t('cms.edit.headings') }}</button>
                </div>
            @endif
            {{-- The opbouw (design 11.6–11.13): every strip of the page in order; structure.js adds, moves and removes them. --}}
            <div class="opbouw-deel" id="paneel-opbouw" data-slot="structure"@if ($headingLevels) role="tabpanel" aria-labelledby="paneel-tab-opbouw"@endif>
                <div class="meldingen" data-slot="structure-notices"></div>
                <div class="opbouw-leeg" data-slot="structure-empty" hidden>
                    <span class="opbouw-leeg-titel">{{ $t('cms.edit.empty_title') }}</span>
                    <span class="veld-hint">{{ $t('cms.edit.empty_text') }}</span>
                    <button type="button" class="knop" data-slot="structure-first">{{ $t('cms.edit.empty_add') }}</button>
                </div>
                <div class="opbouw-lijst" data-slot="structure-filled">
                    <ol class="opbouw" aria-label="{{ $t('cms.edit.structure_list') }}" data-slot="structure-list"></ol>
                    <button type="button" class="actie opbouw-toevoegen" data-slot="structure-add">{{ $t('cms.edit.structure_add') }}</button>
                    <p class="opbouw-status" role="status" data-slot="structure-status"></p>
                    <p class="veld-hint" id="opbouw-greephint">{{ $t('cms.edit.structure_drag_hint') }}</p>
                </div>
            </div>
            {{-- The catalogue: the strip types of the site; one click places a new strip at the chosen place. --}}
            <div class="opbouw-deel" data-slot="catalogue" hidden>
                <nav class="kruimel" aria-label="{{ $t('cms.edit.breadcrumb') }}">
                    <button type="button" class="actie klein" data-slot="catalogue-back"><span aria-hidden="true">←</span> {{ $t('cms.edit.structure') }}</button>
                    <span class="kruimel-slash" aria-hidden="true">/</span>
                    <span class="kruimel-hier" aria-current="location">{{ $t('cms.edit.catalogue') }}</span>
                </nav>
                <div class="opbouw-plek">
                    <span class="veld-hint"><strong>{{ $t('cms.edit.catalogue_comes_after') }}</strong> <span data-slot="catalogue-place"></span></span>
                    <button type="button" class="tekstlink klein" data-slot="catalogue-other">{{ $t('cms.edit.catalogue_other_place') }}</button>
                </div>
                <div class="veld" data-slot="catalogue-search-field" hidden>
                    <label for="opbouw-zoek">{{ $t('cms.edit.catalogue_search') }}</label>
                    <input type="search" id="opbouw-zoek" data-slot="catalogue-search" placeholder="{{ $t('cms.edit.catalogue_search_placeholder') }}" autocomplete="off">
                </div>
                <div class="soorten" role="group" aria-label="{{ $t('cms.edit.catalogue_types') }}" data-slot="catalogue-types"></div>
                <p class="veld-hint" data-slot="catalogue-none" hidden>{{ $t('cms.edit.catalogue_none') }}</p>
                <p class="veld-hint">{{ $t('cms.edit.catalogue_hint') }}</p>
                <button type="button" class="tekstlink klein opbouw-annuleren" data-slot="catalogue-cancel">{{ $t('cms.edit.catalogue_cancel') }}</button>
                <p class="opbouw-status" role="status" data-slot="catalogue-status"></p>
            </div>
            @if ($headingLevels)
                {{--
                    The headings of the page as the frame shows them (design 11.21–11.22): edit.js reads them from the site,
                    headings.js fills in the signals and the list.
                --}}
                <div class="opbouw-deel" id="paneel-koppen" role="tabpanel" aria-labelledby="paneel-tab-koppen" data-slot="headings" hidden>
                    <div class="kopsignalen" data-slot="headings-signals"></div>
                    <div class="melding" role="status" data-slot="headings-fine" hidden><span class="bol goed" aria-hidden="true"></span><span class="melding-tekst"><strong>{{ $t('cms.edit.headings_fine_title') }}</strong> {{ $t('cms.edit.headings_fine_text') }}</span></div>
                    <p class="veld-hint" data-slot="headings-none" hidden>{{ $t('cms.edit.headings_none') }}</p>
                    <ol class="opbouw koppen" aria-label="{{ $t('cms.edit.headings_list') }}" data-slot="headings-list"></ol>
                    <p class="veld-hint">{{ $t('cms.edit.headings_hint') }}</p>
                </div>
            @endif
            {{-- panel.js builds the fields of the chosen strip from the templates below and saves them as the draft. --}}
            <form class="paneel" data-slot="form" aria-label="{{ $t('cms.edit.fields_label') }}" novalidate hidden>
                <div class="paneel-deel" data-slot="strip-view">
                    <div class="strook-nav">
                        <nav class="kruimel" aria-label="{{ $t('cms.edit.breadcrumb') }}">
                            <button type="button" class="actie klein" data-slot="structure-back"><span aria-hidden="true">←</span> <span data-slot="structure-back-name">{{ $t('cms.edit.structure') }}</span></button>
                            <span class="kruimel-slash" aria-hidden="true">/</span>
                            <span class="kruimel-hier" aria-current="location" data-slot="structure-crumb"></span>
                        </nav>
                        <span class="pijlknoppen">
                            <button type="button" class="pijlknop" data-slot="structure-up"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 19V5"/><path d="M6 11l6-6 6 6"/></svg></button>
                            <button type="button" class="pijlknop" data-slot="structure-down"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"/><path d="M6 13l6 6 6-6"/></svg></button>
                        </span>
                    </div>
                    <p class="opbouw-status" role="status" data-slot="structure-move-status"></p>
                    <div class="melding" data-slot="added-note" hidden><span class="bol" aria-hidden="true"></span><span data-slot="added-text"></span></div>
                    <div class="paneel-velden" data-slot="fields"></div>
                    <div class="schakelrij">
                        <label class="schakel-label" for="paneel-zichtbaar">{{ $t('cms.edit.visible') }}</label>
                        <input type="checkbox" class="schakel" role="switch" id="paneel-zichtbaar" data-slot="visible">
                    </div>
                </div>
                <div class="paneel-deel" data-slot="item-view" hidden>
                    <nav class="kruimel" aria-label="{{ $t('cms.edit.breadcrumb') }}">
                        <button type="button" class="actie klein" data-slot="back"><span aria-hidden="true">←</span> <span data-slot="crumb-strip"></span></button>
                        <span class="kruimel-slash" aria-hidden="true">/</span>
                        <span data-slot="crumb-list"></span>
                        <span class="kruimel-slash" aria-hidden="true">/</span>
                        <span class="kruimel-hier" aria-current="location" data-slot="crumb-item"></span>
                    </nav>
                    <p class="veld-hint" data-slot="item-position"></p>
                    <p class="veld-fout" data-slot="item-error" hidden></p>
                    <div class="paneel-velden" data-slot="item-fields"></div>
                    <div class="knoppen">
                        <button type="button" class="knop" data-slot="done">{{ $t('cms.edit.done') }}</button>
                        <button type="button" class="tekstlink" data-slot="remove">{{ $t('cms.edit.remove_item') }}</button>
                    </div>
                </div>
                <div class="paneel-voet">
                    <div class="knoppen" data-slot="save-row">
                        <button type="submit" class="knop" data-slot="save">{{ $t('cms.edit.save') }}</button>
                        <button type="button" class="tekstlink" data-slot="undo" disabled>{{ $t('cms.edit.undo') }}</button>
                    </div>
                    <p class="paneel-status" role="status" data-slot="status"></p>
                    <p class="veld-hint">{{ $t('cms.edit.note') }}</p>
                </div>
                <div class="strook-weg" data-slot="structure-remove">
                    <span class="veld-hint">{{ $t('cms.edit.remove_strip_text') }}</span>
                    <button type="button" class="tekstlink klein alarm" data-slot="structure-remove-strip">{{ $t('cms.edit.remove_strip') }}</button>
                </div>
            </form>
            {{-- The uploads of the side panel, also while the editor works on another strip; panel.js fills it in. --}}
            <div class="paneel-upload" data-slot="upload-status">
                <span class="paneel-upload-lijn" aria-hidden="true"><span data-slot="upload-status-line"></span></span>
                <span class="paneel-upload-ring" aria-hidden="true"></span>
                <span class="paneel-upload-teken goed" aria-hidden="true"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5L20 7"/></svg></span>
                <span class="paneel-upload-teken fout" aria-hidden="true"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></span>
                <span class="paneel-upload-tekst"><span role="status" data-slot="upload-status-text"></span><span aria-hidden="true" data-slot="upload-status-percent"></span> <button type="button" class="tekstlink" data-slot="upload-status-open" hidden>{{ $t('cms.edit.upload_status_open') }}</button></span>
                <button type="button" class="paneel-upload-sluit" aria-label="{{ $t('cms.edit.upload_status_close') }}" data-slot="upload-status-close" hidden><span aria-hidden="true">×</span></button>
            </div>
            <p class="sr-only" aria-live="polite" data-slot="announce"></p>
            <p id="paneel-sleephint" hidden>{{ $t('cms.edit.drag_hint') }}</p>
        </aside>
    </div>
    {{-- In the shell, so its events reach it, and after the side panel, so the panel finds its own slots first. --}}
    @include('made-cms-package-launcher::cms.partials.media-picker', ['picker' => $picker])
    {{-- After the picker, so it opens above it after an upload there. --}}
    @include('made-cms-package-launcher::cms.partials.crop', ['crop' => $crop])
</div>
{{-- Cloned into a shadow root in the frame, so the site's css and the outlines never touch each other. --}}
<template data-overlay>
    <style>
        :host { all: initial; position: absolute; top: 0; left: 0; width: 0; height: 0; z-index: 2147483000; }
        .omlijning { position: absolute; box-sizing: border-box; margin: 0; padding: 0; border: 2px dashed rgb(110 127 120 / .55); border-radius: 12px; background: transparent; cursor: pointer; text-align: left; transition: border-color 120ms ease, background-color 120ms ease; }
        .omlijning:hover, .omlijning.actief { border-color: #be8a1c; border-style: solid; }
        .omlijning.actief { background: rgb(190 138 28 / .08); }
        .omlijning[hidden] { display: none; }
        .omlijning:focus-visible { outline: 2px solid #be8a1c; outline-offset: 2px; }
        .tab { position: absolute; top: -2px; left: -2px; display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 12px 0 10px 0; background: #6e7f78; color: #f3f1e8; font: 700 12px/1.2 "Made CMS Nunito", Nunito, "Trebuchet MS", system-ui, sans-serif; white-space: nowrap; }
        .omlijning.actief .tab { background: #be8a1c; color: #0e2b26; }
        .telling { font-family: "Made CMS Instrument Sans", "Instrument Sans", "Segoe UI", system-ui, sans-serif; font-weight: 600; opacity: .85; }
        @media (prefers-reduced-motion: reduce) { .omlijning { transition: none; } }
    </style>
    <button type="button" class="omlijning"><span class="tab"><span class="naam"></span><span class="telling"></span></span></button>
</template>
{{-- The controls of the side panel, one per type of field. panel.js clones them and fills in the definition. --}}
<template data-field="text">
    <div class="veld">
        <label data-slot="label"></label>
        <input type="text" data-slot="input" autocomplete="off">
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-hint" data-slot="emphasis-hint" hidden>{{ $t('cms.edit.emphasis_hint') }}</p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-field="long_text">
    <div class="veld">
        <label data-slot="label"></label>
        <textarea rows="4" data-slot="input"></textarea>
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-field="rich_text">
    <div class="veld">
        <span class="veld-label" data-slot="label"></span>
        <div class="opmaak">
            <div class="opmaak-balk" role="toolbar" aria-label="{{ $t('cms.edit.toolbar') }}" data-slot="toolbar">
                <button type="button" class="opmaak-knop" data-command="bold" aria-pressed="false" aria-label="{{ $t('cms.edit.bold') }}" title="{{ $t('cms.edit.bold') }}"><strong aria-hidden="true">B</strong></button>
                <button type="button" class="opmaak-knop" data-command="italic" aria-pressed="false" aria-label="{{ $t('cms.edit.italic') }}" title="{{ $t('cms.edit.italic') }}"><em aria-hidden="true">I</em></button>
                <button type="button" class="opmaak-knop" data-command="link" aria-pressed="false">{{ $t('cms.edit.link') }}</button>
                <button type="button" class="opmaak-knop" data-command="list" aria-pressed="false"><span aria-hidden="true">•</span> {{ $t('cms.edit.list') }}</button>
            </div>
            <div class="opmaak-tekst" contenteditable="true" role="textbox" aria-multiline="true" data-slot="input"></div>
            <div class="opmaak-link" data-slot="link" hidden>
                <label data-slot="link-label">{{ $t('cms.edit.link_address') }}</label>
                <input type="text" data-slot="link-input" autocomplete="off" spellcheck="false">
                <div class="knoppen">
                    <button type="button" class="actie klein" data-command="link-apply">{{ $t('cms.edit.link_apply') }}</button>
                    <button type="button" class="tekstlink klein" data-command="link-remove">{{ $t('cms.edit.link_remove') }}</button>
                    <button type="button" class="tekstlink klein" data-command="link-cancel">{{ $t('cms.edit.link_cancel') }}</button>
                </div>
            </div>
        </div>
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-field="link">
    <div class="veld">
        <label data-slot="label"></label>
        <div class="koppeling">
            <input type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" data-slot="input" autocomplete="off" spellcheck="false">
            <ul class="koppeling-lijst" role="listbox" data-slot="options" hidden></ul>
        </div>
        <p class="veld-hint koppeling-doel" data-slot="target" hidden></p>
        <p class="veld-hint" data-slot="hint" data-default="{{ $t('cms.edit.link_hint') }}"></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-part="option">
    <li class="koppeling-optie" role="option" aria-selected="false"><span class="koppeling-titel" data-slot="title"></span> <span class="koppeling-pad" data-slot="path"></span></li>
</template>
<template data-field="button">
    <fieldset class="veld veldgroep">
        <legend class="veld-label" data-slot="label"></legend>
        <div class="veldgroep-delen" data-slot="parts"></div>
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </fieldset>
</template>
{{--
    A heading: its name with its level next to it, and its text. Whoever opens Meta-overzicht chooses the level from the
    list below the name (design 11.18–11.19); anyone else sees the level as a label (11.20). The choice is only UI: the
    server keeps the level of a heading for an editor who does not choose it. A heading with emphasis says below its
    text how to mark a word (emphasis-hint, as a text field does).
--}}
<template data-field="heading">
    <div class="veld kopveld">
        <div class="kopveld-keuze" data-slot="choice">
            <div class="kopveld-kop">
                <span class="veld-label" data-slot="label"></span>
                <button type="button" class="niveau" aria-haspopup="listbox" aria-expanded="false" data-slot="level"><span data-slot="level-name"></span><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg></button>
                <span class="niveau vast" role="img" data-slot="level-fixed"></span>
            </div>
            <ul class="niveaulijst" role="listbox" tabindex="-1" data-slot="levels" hidden></ul>
            <p class="veld-fout" data-slot="error" hidden></p>
        </div>
        <div data-slot="parts"></div>
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-hint" data-slot="emphasis-hint" hidden>{{ $t('cms.edit.emphasis_hint') }}</p>
        <p class="veld-hint" data-slot="level-hint" data-choose="{{ $t('cms.edit.heading_hint') }}" data-item="{{ $t('cms.edit.heading_item_hint') }}"></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-part="level">
    <li class="niveauoptie" role="option" aria-selected="false"><span class="niveauoptie-naam" data-slot="level-label"></span><span class="niveauoptie-uitleg" data-slot="level-description"></span><span class="vink" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5L20 7"/></svg></span></li>
</template>
{{--
    An image of the media library, the media element (design 13.2, 13.3): the photo the field holds with its name, size,
    folder and alt text, that it holds none, or that its file left Media. "Kiezen uit media" opens the picker (the
    dialog media-picker in the shell), "Uploaden" the upload field below it (the part "upload"); the photo the editor
    chooses or uploads is the value of the field at once. "Bijsnijden" opens the crop step (the dialog media-crop) for a
    photo that has an original to cut from.
--}}
<template data-field="image">
    <div class="veld beeldveld" role="group">
        <div class="beeldveld-kop">
            <span class="veld-label" data-slot="label"></span>
            <span class="veld-hint" data-slot="hint" hidden></span>
        </div>
        <div class="beeldkaart" data-slot="image-card">
            <div class="beeldkaart-voorbeeld" data-slot="image-preview" hidden>
                <svg class="beeldkaart-icoon" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
                <img alt="" data-slot="image-thumb">
                <span class="beeldkaart-ext" data-slot="image-extension"></span>
                <span class="beeldkaart-brandpunt" role="img" aria-label="{{ $t('cms.edit.image_focal') }}" data-slot="image-focal"></span>
                <span class="beeldkaart-stip" role="img" aria-label="{{ $t('cms.edit.image_no_alt') }}" data-slot="image-no-alt" hidden></span>
            </div>
            <div class="beeldkaart-tekst" data-slot="image-file" hidden>
                <span class="beeldkaart-naam" data-slot="image-name"></span>
                <span class="veld-hint" data-slot="image-meta"></span>
                <span class="veld-hint beeldkaart-alt" data-slot="image-alt"></span>
            </div>
            <span class="beeldkaart-leegicoon" aria-hidden="true" data-slot="image-none" hidden><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg></span>
            <span class="beeldkaart-leegtitel" data-slot="image-empty" hidden>{{ $t('cms.edit.image_empty') }}</span>
            <span class="veld-hint" data-slot="image-empty-text" hidden>{{ $t('cms.edit.image_empty_text') }}</span>
            <span class="beeldkaart-weg-icoon" aria-hidden="true" data-slot="image-gone-icon" hidden><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M4 6l16 12"/></svg></span>
            <span class="beeldkaart-weg-tekst" data-slot="image-gone" hidden>
                <span class="beeldkaart-leegtitel">{{ $t('cms.edit.image_gone') }}</span>
                <span class="veld-hint">{{ $t('cms.edit.image_gone_text') }}</span>
            </span>
            <div class="beeldkaart-acties">
                <button type="button" class="actie klein" aria-haspopup="dialog" data-slot="image-pick">{{ $t('cms.edit.image_pick') }}</button>
                <button type="button" class="actie klein" aria-expanded="false" data-slot="image-upload">{{ $t('cms.edit.image_upload') }}</button>
                <button type="button" class="actie klein" aria-haspopup="dialog" data-slot="image-crop" hidden>{{ $t('cms.edit.image_crop') }}</button>
                <button type="button" class="actie klein beeldkaart-weg" title="{{ $t('cms.edit.image_remove') }}" data-slot="image-remove" hidden><span aria-hidden="true">×</span></button>
            </div>
        </div>
        <div class="beeldveld-upload" data-slot="image-upload-area"></div>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
{{--
    The upload field of an image (UploadField::image()), cloned once per field that uploads. upload.js starts it when it
    is on the page; "Annuleren" closes it while no photo is on its way.
--}}
<template data-part="upload">
    <div class="beeldveld-uploadveld">
        @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $upload])
        <div class="beeldveld-uploadvoet">
            <span class="veld-hint" data-slot="image-upload-busy" hidden>{{ $t('cms.edit.image_upload_busy') }}</span>
            <button type="button" class="tekstlink klein" data-slot="image-upload-cancel">{{ $t('cms.edit.image_upload_cancel') }}</button>
        </div>
    </div>
</template>
<template data-field="choice">
    <div class="veld">
        <label data-slot="label"></label>
        <select data-slot="input"></select>
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-part="no-choice">
    <option value="">{{ $t('cms.edit.no_choice') }}</option>
</template>
<template data-field="items">
    <div class="veld lijstveld">
        <div class="lijstveld-kop">
            <span class="veld-label" data-slot="label"></span>
            <span class="veld-hint" data-slot="count"></span>
        </div>
        <ol class="lijstveld-rijen" data-slot="rows"></ol>
        <button type="button" class="actie klein lijst-toevoegen" data-slot="add"></button>
        <p class="veld-hint" data-slot="hint" hidden></p>
        <p class="veld-fout" data-slot="error" hidden></p>
    </div>
</template>
<template data-part="row">
    <li class="lijstrij">
        <button type="button" class="greep" data-slot="grip" aria-describedby="paneel-sleephint">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="9" cy="6" r="1.8"/><circle cx="15" cy="6" r="1.8"/><circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/><circle cx="9" cy="18" r="1.8"/><circle cx="15" cy="18" r="1.8"/></svg>
        </button>
        <span class="lijstrij-tekst">
            <span class="lijstrij-titel" data-slot="title"></span>
            <span class="lijstrij-sub" data-slot="sub"></span>
            <span class="lijstrij-fout" data-slot="error" hidden>{{ $t('cms.edit.panel_texts.item_error') }}</span>
        </span>
        <button type="button" class="actie klein" data-slot="edit">{{ $t('cms.edit.edit_item') }}</button>
    </li>
</template>
{{-- The parts of the opbouw. structure.js clones them and fills in the strips of the page. --}}
<template data-part="insert">
    <li class="invoeg"><button type="button" class="invoegknop" data-slot="row-insert">{{ $t('cms.edit.structure_insert') }}</button></li>
</template>
<template data-part="strip-row">
    <li class="strookrij">
        <button type="button" class="greep" data-slot="row-grip" aria-describedby="opbouw-greephint">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="9" cy="6" r="1.8"/><circle cx="15" cy="6" r="1.8"/><circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/><circle cx="9" cy="18" r="1.8"/><circle cx="15" cy="18" r="1.8"/></svg>
        </button>
        <span class="lijstrij-tekst">
            <span class="strookrij-naam"><span class="lijstrij-titel" data-slot="row-name"></span><span class="pil" data-slot="row-hidden" hidden>{{ $t('cms.edit.hidden') }}</span></span>
            <span class="lijstrij-sub" data-slot="row-sub"></span>
        </span>
        <button type="button" class="actie klein" data-slot="row-edit">{{ $t('cms.edit.edit_strip') }}</button>
    </li>
</template>
<template data-part="unknown-row">
    <li class="strookrij onbekend">
        <span class="strookrij-icoon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg></span>
        <span class="lijstrij-tekst">
            <span class="strookrij-naam"><span class="lijstrij-titel" data-slot="row-name"></span><span class="pil let-op">{{ $t('cms.edit.not_on_site') }}</span></span>
            <span class="lijstrij-sub" data-slot="row-sub"></span>
        </span>
        <button type="button" class="actie klein" data-slot="row-remove">{{ $t('cms.edit.remove_unknown') }}</button>
    </li>
</template>
<template data-part="unknown-note">
    <li class="strookrij-uitleg veld-hint" data-slot="row-note"></li>
</template>
<template data-part="placeholder">
    <li class="plaats" aria-hidden="true"></li>
</template>
<template data-part="notice">
    <div class="melding"><span class="bol" aria-hidden="true"></span><span class="melding-tekst" data-slot="notice-text"></span><button type="button" class="tekstlink klein" data-slot="notice-undo">{{ $t('cms.edit.undo') }}</button></div>
</template>
@if ($headingLevels)
    {{-- The parts of the overview of the headings. headings.js clones them for every heading and signal. --}}
    <template data-part="heading-row">
        <li class="kopitem"><button type="button" class="koprij" data-slot="headings-open"><span class="kopbadge" data-slot="headings-level"></span><span class="tekst" data-slot="headings-text"></span><span class="bron" data-slot="headings-source"></span></button></li>
    </template>
    <template data-part="heading-fixed">
        <li class="kopitem"><span class="koprij vast"><span class="kopbadge" data-slot="headings-level"></span><span class="tekst" data-slot="headings-text"></span><span class="bron" data-slot="headings-source"></span></span></li>
    </template>
    <template data-part="heading-signal">
        <div class="kopsignaal"><span class="bol" aria-hidden="true"></span><span class="kopsignaal-tekst"><span class="kopsignaal-titel" data-slot="headings-signal-title"></span><span class="veld-hint" data-slot="headings-signal-meta"></span></span><button type="button" class="tekstlink klein" data-slot="headings-signal-action" hidden></button></div>
    </template>
@endif
<template data-part="type">
    <button type="button" class="soortkaart">
        <span class="soortkaart-kop"><span class="soortkaart-naam" data-slot="type-name"></span><span class="veld-hint" data-slot="type-fields"></span></span>
        <span class="veld-hint" data-slot="type-description"></span>
    </button>
</template>
<script type="application/json" data-config>@json($config)</script>
<script type="module" src="{{ $editScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $panelScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $barScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $structureScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $uploadScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $picker['browser']['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $crop['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
@if ($headingsScript !== null)
    <script type="module" src="{{ $headingsScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
@endif
</body>
</html>
