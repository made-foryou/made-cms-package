@extends('made-cms-package-launcher::cms.layout', ['title' => $screen->label()])

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            <span class="eyebrow">{{ $screen->group()->label() }}</span>
            <h1 id="made-cms-titel">{{ $screen->label() }}</h1>
            <p class="intro">{{ $intro }}</p>
        </div>
        @if ($workUrl !== null)
            <a class="knop" href="{{ $workUrl }}">{{ $workLabel }}</a>
        @endif
    </section>

    {{-- A plain form: the filter changes the list and keeps the page open beside it and the progress of the round. --}}
    <div class="metastand">
        <form class="paginafilters" method="get" action="{{ $screenUrl }}" role="group" aria-label="{{ $t('meta.filters_label') }}">
            @if ($openPage !== null)
                <input type="hidden" name="page" value="{{ $openPage }}">
            @endif
            @if ($done > 0)
                <input type="hidden" name="done" value="{{ $done }}">
            @endif
            @foreach ($filters as $item)
                <button type="submit" class="filter" name="filter" value="{{ $item['value'] }}" aria-pressed="{{ $item['pressed'] ? 'true' : 'false' }}" data-filter="{{ $item['value'] }}">@if ($item['dot'] !== null)<span class="filter-bol {{ $item['dot'] }}" aria-hidden="true"></span>@endif{{ $item['label'] }}</button>
            @endforeach
        </form>
        @if ($finished)
            <span class="metastand-klaar">{{ $t('meta.finished_line') }}</span>
        @endif
    </div>

    <div class="kolommen metakolommen">
        <section class="metalijst" aria-labelledby="made-cms-meta-lijst">
            <h2 class="sr-only" id="made-cms-meta-lijst">{{ $t('meta.list.heading') }}</h2>
            <div class="metalijst-kop">
                <span>{{ $t('meta.list.order') }}</span>
                <span class="metalijst-kolommen" aria-hidden="true">{{ $t('meta.list.columns') }}</span>
            </div>
            @if ($rows === [])
                <p class="leeg">{{ $t('meta.list.empty') }}</p>
            @else
                <ul class="metarijen" role="list">
                    @foreach ($rows as $row)
                        @include('made-cms-package-launcher::cms.meta.partials.row', ['row' => $row])
                    @endforeach
                </ul>
            @endif
            <p class="metalijst-uitleg">{{ $legend }}</p>
        </section>

        @if ($panel !== null)
            @include('made-cms-package-launcher::cms.meta.partials.panel', ['panel' => $panel, 'saved' => $saved])
        @elseif ($finished)
            @include('made-cms-package-launcher::cms.meta.partials.finished', ['next' => $next, 'limits' => $limits, 'saved' => $saved])
        @endif
    </div>
@endsection
