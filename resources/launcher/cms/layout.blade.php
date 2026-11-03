<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
@include('made-cms-package-launcher::cms.partials.head')
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
