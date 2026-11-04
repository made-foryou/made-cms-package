{{--
    The edit shell: the bar with the page and its status, the site in a frame of the same origin, and the side panel.
    edit.js draws an outline over every [data-cms-strip] in the frame from the template below and fills the panel.
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
            <span class="status{{ $page['live'] ? ' live' : '' }}">{{ $t('pages.status.'.$page['status']) }}</span>
        </div>
        <div class="bewerkbalk-rechts">
            <button type="button" class="bewerkbalk-link" disabled>{{ $t('cms.edit.preview') }}</button>
            <button type="button" class="bewerkbalk-knop" disabled>{{ $t('cms.edit.publish') }}</button>
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
            <p class="bewerkpaneel-leeg" data-slot="choose">{{ $t('cms.edit.choose') }}</p>
            <div class="bewerkpaneel-kop" data-slot="strip" hidden>
                <div class="bewerkpaneel-titel">
                    <span class="eyebrow">{{ $t('cms.edit.strip') }}</span>
                    <h2 data-slot="name"></h2>
                </div>
                <span class="bewerkpaneel-positie" data-slot="position"></span>
            </div>
            {{-- panel.js builds the fields of the chosen strip from the templates below and saves them as the draft. --}}
            <form class="paneel" data-slot="form" aria-label="{{ $t('cms.edit.fields_label') }}" novalidate hidden>
                <div class="paneel-deel" data-slot="strip-view">
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
                    </div>
                    <p class="paneel-status" role="status" data-slot="status"></p>
                    <p class="veld-hint">{{ $t('cms.edit.note') }}</p>
                </div>
            </form>
            <div class="verborgen-stroken" data-slot="hidden-strips" hidden>
                <span class="eyebrow">{{ $t('cms.edit.hidden_strips') }}</span>
                <ul data-slot="hidden-list"></ul>
            </div>
            <p class="sr-only" aria-live="polite" data-slot="announce"></p>
            <p id="paneel-sleephint" hidden>{{ $t('cms.edit.drag_hint') }}</p>
        </aside>
    </div>
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
<script type="application/json" data-config>@json($config)</script>
<script type="module" src="{{ $editScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
<script type="module" src="{{ $panelScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
</body>
</html>
