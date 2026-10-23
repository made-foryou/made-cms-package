@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::launcher.'.$key, $replace, $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>{{ $t('link.title', ['site' => $site]) }}</title>
</head>
<body>
<made-cms-launcher lang="{{ $locale }}" data-view="page" data-font-heading="{{ $fonts['heading'] }}" data-font-text="{{ $fonts['text'] }}"><template shadowrootmode="open" shadowrootclonable>
<link rel="stylesheet" href="{{ $stylesheet }}"@if ($nonce) nonce="{{ $nonce }}"@endif>
<main class="pagina">
    <div class="pagina-inhoud">
        @include('made-cms-package-launcher::partials.head')
        <div class="stap">
            <div class="tekst">
                <h1>{{ $t('link.'.$state.'.heading', ['site' => $site]) }}</h1>
                <p>{{ $t('link.'.$state.'.intro', ['site' => $site, 'minutes' => $minutes]) }}</p>
            </div>
            @if ($state === 'confirm')
                <form method="post" action="{{ $action }}">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="knop">{{ $t('link.confirm.submit') }}</button>
                </form>
            @endif
        </div>
        <p class="voet">{{ $t('link.footer', ['site' => $site]) }}</p>
    </div>
</main>
</template></made-cms-launcher>
<script type="module" src="{{ $script }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
</body>
</html>
