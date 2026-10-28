<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
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
{{-- Before the first paint: the menu as the editor left it (closed the first time, and always on a phone). Without script it stays open. --}}
<script @if ($nonce) nonce="{{ $nonce }}" @endif>
(function (root) { var menu = 'dicht', found = false; try { menu = localStorage.getItem('made-cms-menu') === 'open' ? 'open' : 'dicht'; found = localStorage.getItem('made-cms-menu-found') === '1'; } catch (e) {} if (window.matchMedia('(max-width: 720px)').matches) { menu = 'dicht'; } root.dataset.menu = menu; if (found) { root.dataset.menuFound = ''; } })(document.documentElement);
</script>
</head>
<body>
<div class="schil">
    @include('made-cms-package-launcher::cms.partials.header')
    <div class="romp">
        @include('made-cms-package-launcher::cms.partials.menu')
        <div class="hoofd">
            <main class="inhoud" aria-labelledby="made-cms-titel">
                @yield('content')
            </main>
        </div>
    </div>
</div>
<script type="module" src="{{ $script }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
</body>
</html>
