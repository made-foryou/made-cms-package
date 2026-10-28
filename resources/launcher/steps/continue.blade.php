@php
    $first = $recent[0] ?? null;
@endphp
<div class="stap" data-form="continue">
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('continue.heading') }}</h2>
        <p>{{ $t('continue.intro', ['site' => $site]) }}</p>
    </div>
    <div class="keuzes">
        @foreach ($recent as $item)
            <a @class(['keuze', 'actief' => $loop->first]) href="{{ $item['url'] }}">
                <span @class(['keuze-icoon', 'vlak' => $loop->first])><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h5"/><path d="M10 17h5"/></svg></span>
                <span class="keuze-tekst"><span class="keuze-titel">{{ $item['title'] }}</span><span class="keuze-uitleg">{{ $t('continue.kind') }} · {{ $item['when'] }}</span></span>
                <span class="pil {{ $item['status'] }}">{{ $t('continue.status.'.$item['status']) }}</span>
            </a>
        @endforeach
    </div>
    <div class="acties">
        @if ($first !== null)
            <a class="knop" href="{{ $first['url'] }}"><span>{{ $t('continue.submit', ['title' => $first['title']]) }}</span></a>
        @endif
        <a class="knop secundair" href="{{ $home }}"><span>{{ $t('continue.to_site') }}</span></a>
    </div>
    <a class="tekstlink" href="{{ $endpoints['start'] }}">{{ $t('continue.to_start') }}</a>
</div>
