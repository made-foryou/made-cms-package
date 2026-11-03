{{-- The head every CMS page shares: the meta tags, the fonts and the stylesheet. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f3f1e8" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e2b26" media="(prefers-color-scheme: dark)">
<title>{{ $t('cms.title', ['page' => $title, 'site' => $site]) }}</title>
<style @if ($nonce) nonce="{{ $nonce }}" @endif>
@font-face { font-family: "Made CMS Nunito"; src: url("{{ $fonts['heading'] }}") format("woff2"); font-weight: 700; font-display: swap; }
@font-face { font-family: "Made CMS Instrument Sans"; src: url("{{ $fonts['text'] }}") format("woff2"); font-weight: 400 700; font-display: swap; }
</style>
<link rel="stylesheet" href="{{ $stylesheet }}"@if ($nonce) nonce="{{ $nonce }}"@endif>
