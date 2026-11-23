{{-- The column "In het kort" beside the cards (design 15.2): what the page asks for in one line. --}}
<aside class="paginakort" aria-labelledby="made-cms-kort">
    <h2 id="made-cms-kort" class="paginakort-label">{{ $t('pages.show.summary.label') }}</h2>
    <div class="paginakort-aandacht">
        <span class="paginakort-kop">@if ($attention['summary']['flagged'])<span class="paginakort-stip" aria-hidden="true"></span>@endif{{ $attention['summary']['text'] }}</span>
        @if ($attention['summary']['hint'] !== null)
            <span class="paginakort-hint">{{ $attention['summary']['hint'] }}</span>
        @endif
    </div>
</aside>
