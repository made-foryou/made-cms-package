@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::launcher.'.$key, $replace, $locale);

    // A translation with live values (filled in by the script with textContent) as empty spans, escaped like any other text.
    $slots = function (string $key, array $names, array $replace = []) use ($t): Illuminate\Support\HtmlString {
        $markers = [];

        foreach ($names as $name) {
            $markers[$name] = "\u{E000}{$name}\u{E001}";
        }

        $html = e($t($key, [...$replace, ...$markers]));

        foreach ($markers as $name => $marker) {
            $html = str_replace(e($marker), '<span data-slot="'.$name.'"></span>', $html);
        }

        return new Illuminate\Support\HtmlString($html);
    };

    $config = [
        'endpoints' => $endpoints,
        'editor' => $editor,
        'texts' => [
            'steps' => [
                1 => $t('step', ['current' => 1, 'total' => 3]),
                2 => $t('step', ['current' => 2, 'total' => 3]),
                3 => $t('step', ['current' => 3, 'total' => 3]),
                4 => $t('step_done'),
            ],
            'busy' => $t('busy'),
            'copied' => $t('copied'),
            'codes_file' => $t('codes.file_title', ['site' => $site]),
            'network' => $t('errors.network'),
            'expired' => $t('errors.expired'),
        ],
    ];
@endphp
<made-cms-launcher lang="{{ $locale }}" data-view="{{ $view }}"@if ($arrived) data-arrived @endif data-font-heading="{{ $fonts['heading'] }}" data-font-text="{{ $fonts['text'] }}"><template shadowrootmode="open" shadowrootclonable>
<link rel="stylesheet" href="{{ $stylesheet }}"@if ($nonce) nonce="{{ $nonce }}"@endif>
<script type="application/json" data-config>@json($config)</script>
<div class="plek" hidden>
    <button type="button" class="beheer" aria-label="{{ $t('open') }}" aria-haspopup="dialog">
        <span class="label" aria-hidden="true">
            <span class="label-titel">{{ $t('title') }}</span>
            <span class="label-product">{{ $t('product') }}</span>
            <span class="streep"></span>
        </span>
        <svg class="beeldmerk" viewBox="0 0 120 120" width="48" height="48" aria-hidden="true" focusable="false"><rect width="120" height="120" rx="34" fill="#0e2b26"/><path d="M45 92 L75 28" fill="none" stroke="#be8a1c" stroke-width="21" stroke-linecap="round"/></svg>
    </button>
</div>
<dialog class="paneel" aria-labelledby="made-cms-titel">
    <div class="inhoud">
        @include('made-cms-package-launcher::partials.head', ['closable' => true])
        <div class="stappen" role="img" aria-label="{{ $t('step', ['current' => 1, 'total' => 3]) }}">
            <span class="stapbalk huidig"></span>
            <span class="stapbalk"></span>
            <span class="stapbalk"></span>
            <span class="staplabel" aria-hidden="true">{{ $t('step', ['current' => 1, 'total' => 3]) }}</span>
        </div>
        <div class="stapvlak" data-stapvlak></div>
        <p class="melding algemeen" data-algemeen hidden></p>
        <p class="sr-only" role="alert" data-melding></p>
        <p class="sr-only" role="status" data-status></p>
        <p class="voet">{{ $t('no_access') }}</p>
    </div>
</dialog>
@include('made-cms-package-launcher::partials.edit-bar')
@foreach (['email', 'method', 'password', 'code', 'two-factor', 'recovery', 'two-factor-offer', 'two-factor-setup', 'recovery-codes', 'done'] as $step)
<template data-step="{{ str_replace('-', '_', $step) }}">@include('made-cms-package-launcher::steps.'.$step)</template>
@endforeach
</template></made-cms-launcher>
<script type="module" src="{{ $script }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
