@extends('made-cms-package-launcher::cms.layout', ['title' => $screen->label()])

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            <span class="eyebrow">{{ $screen->group()->label() }}</span>
            <h1 id="made-cms-titel">{{ $screen->label() }}</h1>
            <p class="intro">{{ $t('pages.index.intro') }}</p>
        </div>
        <a class="knop" href="{{ $createUrl }}">{{ $t('pages.index.new') }}</a>
    </section>

    <section class="blok" aria-labelledby="made-cms-alle">
        <div class="sectiekop">
            <h2 id="made-cms-alle">{{ $t('pages.index.heading') }}</h2>
            <span class="sectienoot">{{ $t('pages.index.order') }}</span>
        </div>
        @if ($rows === [])
            <p class="leeg">{{ $t('pages.index.empty') }}</p>
        @else
            <ul class="boom">
                @foreach ($rows as $row)
                    <li @class(['boomrij', 'concept' => ! $row['live']])@if ($row['depth'] > 0) data-diepte="{{ min($row['depth'], 4) }}"@endif>
                        <span class="boomrij-tekst">
                            <a class="boomrij-naam" href="{{ $row['showUrl'] }}">{{ $row['title'] }}</a>
                            <span class="boomrij-pad">{{ $row['path'] ?? '–' }}</span>
                        </span>
                        <span class="status{{ $row['live'] ? ' live' : '' }}">{{ $t('pages.status.'.$row['status']) }}</span>
                        <span class="boomrij-noot">{{ $row['note'] }}</span>
                        <span class="boomrij-datum">{{ $t('pages.index.modified', ['date' => $row['modified']]) }}</span>
                        <span class="boomrij-acties">
                            <a class="actie klein" href="{{ $row['showUrl'] }}" aria-label="{{ $t('pages.index.overview_label', ['title' => $row['title']]) }}">{{ $t('pages.index.overview') }}</a>
                            <a class="actie klein" href="{{ $row['editUrl'] }}" aria-label="{{ $t('pages.index.edit_label', ['title' => $row['title']]) }}">{{ $t('pages.index.edit') }}</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($deleted !== [])
        <section class="blok" aria-labelledby="made-cms-verwijderd">
            <div class="sectiekop">
                <h2 id="made-cms-verwijderd">{{ $t('pages.index.deleted.heading') }}</h2>
                <span class="sectienoot">{{ $t('pages.index.deleted.caption', ['days' => $keepDays]) }}</span>
            </div>
            @error('restore')
                <p class="bericht fout" role="alert">{{ $message }}</p>
            @enderror
            <ul class="lijst">
                @foreach ($deleted as $page)
                    <li class="punt">
                        <span class="punt-tekst">
                            <span class="punt-titel">{{ $page['title'] }}</span>
                            <span class="punt-meta"><span class="boomrij-pad">{{ $page['path'] }}</span> · {{ $t('pages.index.deleted.on', ['date' => $page['deletedAt']]) }}</span>
                        </span>
                        @if ($page['parentDeleted'])
                            <span class="punt-meta">{{ $t('pages.index.deleted.parent_deleted') }}</span>
                        @else
                            <form method="post" action="{{ $page['restoreUrl'] }}">
                                @csrf
                                <button type="submit" class="actie klein" aria-label="{{ $t('pages.index.deleted.restore_label', ['title' => $page['title']]) }}">{{ $t('pages.index.deleted.restore') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
