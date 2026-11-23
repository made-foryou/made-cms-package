{{-- The column "In het kort" beside the cards (design 15.2): the page in one sentence, the facts about it, what it asks for in one line, and how saving works here. --}}
<aside class="paginakort" aria-labelledby="made-cms-kort">
    <div class="paginakort-begin">
        <h2 id="made-cms-kort" class="paginakort-label">{{ $t('pages.show.summary.label') }}</h2>
        <p class="paginakort-regel">{{ $summary['line'] }}</p>
    </div>
    <dl class="paginakort-feiten" aria-label="{{ $t('pages.show.summary.facts') }}">
        @foreach ($summary['facts'] as $fact)
            <dt>{{ $fact['label'] }}</dt><dd>{{ $fact['value'] }}</dd>
        @endforeach
    </dl>
    <div class="paginakort-aandacht">
        <span class="paginakort-kop">@if ($attention['summary']['flagged'])<span class="paginakort-stip" aria-hidden="true"></span>@endif{{ $attention['summary']['text'] }}</span>
        @if ($attention['summary']['hint'] !== null)
            <span class="paginakort-hint">{{ $attention['summary']['hint'] }}</span>
        @endif
    </div>
    <p class="paginakort-noot">{{ $summary['note'] }}</p>
</aside>
