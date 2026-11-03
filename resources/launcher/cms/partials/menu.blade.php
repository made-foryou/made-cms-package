<div class="zijwrap">
    <nav class="zijbalk" id="made-cms-menu" aria-label="{{ $t('cms.menu.label') }}">
        <a class="navitem{{ $current === null ? ' actief' : '' }}" href="{{ $startUrl }}"@if ($current === null) aria-current="page"@endif><span class="merk" aria-hidden="true"></span>{{ $t('cms.menu.start') }}</a>
        @foreach ($menu as $group)
            <div class="navblok">
                <p class="navgroep">{{ $group['label'] }}</p>
                @foreach ($group['items'] as $item)
                    <a class="navitem{{ $item['current'] ? ' actief' : '' }}"@if ($item['depth'] > 0) data-diepte="{{ min($item['depth'], 3) }}"@endif href="{{ $item['url'] }}"@if ($item['current']) aria-current="page"@endif><span class="merk" aria-hidden="true"></span>{{ $item['label'] }}</a>
                @endforeach
            </div>
        @endforeach
        <div class="navvoet">
            <a class="navitem" href="{{ $home }}"><span class="merk" aria-hidden="true"></span>{{ $t('cms.view_site') }}<span class="pijl" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M7 17L17 7"/><path d="M9 7h8v8"/></svg></span></a>
            @if ($helpUrl !== null)
                <a class="navitem" href="{{ $helpUrl }}"><span class="merk" aria-hidden="true"></span>{{ $t('cms.menu.help') }}</a>
            @endif
        </div>
    </nav>
</div>
