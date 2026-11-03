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
<script type="application/json" data-config>@json($config)</script>
<script type="module" src="{{ $editScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
</body>
</html>
