@php
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

    $steps = ['email', 'method', 'password', 'code', 'two-factor', 'recovery', 'two-factor-offer', 'two-factor-setup', 'recovery-codes', 'done'];

    if ($mode === 'page') {
        $steps[] = 'continue';
    }
@endphp
<script type="application/json" data-config>@json($config)</script>
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
@foreach ($steps as $step)
<template data-step="{{ str_replace('-', '_', $step) }}">@include('made-cms-package-launcher::steps.'.$step)</template>
@endforeach
