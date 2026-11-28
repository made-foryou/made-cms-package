@extends('made-cms-package-launcher::cms.layout', ['title' => $screen->label()])

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            <span class="eyebrow">{{ $screen->group()->label() }}</span>
            <h1 id="made-cms-titel">{{ $screen->label() }}</h1>
            <p class="intro">{{ $intro }}</p>
        </div>
    </section>

    @if ($groups !== [])
        {{-- A plain form: the sitemap of another route group of the site. --}}
        <form class="paginafilters sitemapgroepen" method="get" action="{{ $screenUrl }}" role="group" aria-label="{{ $t('sitemap.screen.groups_label') }}">
            @foreach ($groups as $item)
                <button type="submit" class="filter" name="groep" value="{{ $item['value'] }}" aria-pressed="{{ $item['pressed'] ? 'true' : 'false' }}">{{ $item['label'] }}</button>
            @endforeach
        </form>
    @endif

    @if ($file === null)
        <div class="melding let" role="status"><span class="bol let" aria-hidden="true"></span><span class="melding-tekst">{{ $t('sitemap.screen.no_routes') }}</span></div>
    @else
        @include('made-cms-package-launcher::cms.sitemap.partials.state', ['cards' => $cards])
        @include('made-cms-package-launcher::cms.sitemap.partials.file', ['file' => $file, 'group' => $group])
    @endif

    @include('made-cms-package-launcher::cms.sitemap.partials.list', ['rows' => $rows])
@endsection
