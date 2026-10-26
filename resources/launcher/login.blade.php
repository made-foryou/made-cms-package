@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::launcher.'.$key, $replace, $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f3f1e8" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e2b26" media="(prefers-color-scheme: dark)">
<title>{{ $t('page.title', ['site' => $site]) }}</title>
</head>
<body>
<made-cms-launcher lang="{{ $locale }}" data-view="login" data-start="{{ $start }}" data-font-heading="{{ $fonts['heading'] }}" data-font-text="{{ $fonts['text'] }}"><template shadowrootmode="open" shadowrootclonable>
<link rel="stylesheet" href="{{ $stylesheet }}"@if ($nonce) nonce="{{ $nonce }}"@endif>
<main class="pagina" aria-labelledby="made-cms-titel">
    <div class="pagina-inhoud">
        @include('made-cms-package-launcher::partials.head')
        <div class="pagina-flow">
            @include('made-cms-package-launcher::partials.flow')
        </div>
        <p class="voet voet-pagina">
            <span>{{ $t('page.footer', ['site' => $site]) }}</span>
            @if (is_string($helpUrl) && $helpUrl !== '')
                <a class="tekstlink" href="{{ $helpUrl }}">{{ $t('page.help') }}</a>
            @endif
        </p>
    </div>
</main>
</template></made-cms-launcher>
<script type="module" src="{{ $script }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
</body>
</html>
