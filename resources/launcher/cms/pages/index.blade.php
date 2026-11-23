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

    @if ($cards !== [])
        <section class="stand" aria-label="{{ $t('pages.index.state.label') }}" data-swap="cards">
            @foreach ($cards as $card)
                <div @class(['standkaart', $card['tone'] => $card['tone'] !== null])>
                    <span class="standkaart-getal">{{ $card['count'] }}</span>
                    <span class="standkaart-tekst">{{ $card['text'] }}</span>
                    <span class="standkaart-sub">{{ $card['sub'] }}</span>
                </div>
            @endforeach
        </section>
    @endif

    <section class="blok" aria-labelledby="made-cms-alle" data-pages data-config="{{ json_encode($tree, JSON_THROW_ON_ERROR) }}">
        <div class="sectiekop">
            <h2 id="made-cms-alle">{{ $t('pages.index.heading') }}</h2>
            <span class="sectienoot">{{ $t('pages.index.order') }}</span>
        </div>
        @if ($cards === [])
            <p class="leeg">{{ $t('pages.index.empty') }}</p>
        @else
            {{-- Plain forms that work without a script; pages.js searches and filters in place. --}}
            <div class="paginazoek">
                <form class="zoek paginazoek-veld" role="search" method="get" action="{{ $indexUrl }}" data-pages-search>
                    <label class="sr-only" for="made-cms-pagina-zoek">{{ $t('pages.index.search') }}</label>
                    <span class="zoek-icoon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></span>
                    <input class="zoekveld" id="made-cms-pagina-zoek" type="search" name="q" value="{{ $search }}" autocomplete="off" placeholder="{{ $t('pages.index.search_placeholder') }}">
                    @if ($filter !== 'all')
                        <input type="hidden" name="filter" value="{{ $filter }}">
                    @endif
                </form>
                <form class="paginafilters" method="get" action="{{ $indexUrl }}" role="group" aria-label="{{ $t('pages.index.filters_label') }}" data-swap="filters">
                    @if ($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    @foreach ($filters as $item)
                        <button type="submit" class="filter" name="filter" value="{{ $item['value'] }}" aria-pressed="{{ $item['pressed'] ? 'true' : 'false' }}" data-filter="{{ $item['value'] }}">@if ($item['dot'])<span class="filter-bol" aria-hidden="true"></span>@endif{{ $item['label'] }} <span>{{ $item['count'] }}</span></button>
                    @endforeach
                </form>
            </div>

            {{-- What a move that did not work says, until the editor closes it or moves another page. --}}
            <div class="boom-meldplek" role="status" data-slot="tree-notice"></div>
            <template data-tree-notice>
                <div class="melding alarm">
                    <span class="bol alarm" aria-hidden="true"></span>
                    <span class="melding-tekst" data-slot="tree-notice-text"></span>
                    <button type="button" class="mediabrowser-kruis" aria-label="{{ $t('pages.tree.close_notice') }}" title="{{ $t('pages.tree.close_notice') }}" data-slot="tree-notice-close">×</button>
                </div>
            </template>
            <p class="sr-only" aria-live="polite" data-slot="tree-announce"></p>
            {{-- Where a dragged page lands: a line at the depth it gets, with the page it goes under. --}}
            <template data-tree-place>
                <li class="boom-plek" aria-hidden="true"><span class="boom-plek-label"></span></li>
            </template>

            {{-- pages.js reads this screen again after every move, search and filter, and takes this part from it. --}}
            <div class="paginalijst" data-swap="list">
                <p class="sr-only" data-slot="pages-found">{{ $found }}</p>
                @if ($rows === [])
                    <p class="leeg">{{ $search === '' ? $t('pages.index.none') : $t('pages.index.none_for', ['search' => $search]) }} <a href="{{ $indexUrl }}">{{ $t('pages.index.show_all') }}</a></p>
                @else
                    @if ($filtered)
                        <p class="boom-hint">{!! str_replace(':link', '<a href="'.e($indexUrl).'">'.e($t('pages.index.show_all')).'</a>', e($t('pages.index.part', ['link' => ':link']))) !!}</p>
                    @else
                        <p class="boom-hint">{{ $t('pages.tree.hint') }}</p>
                    @endif
                    <ul class="boom" data-tree>
                        @foreach ($rows as $row)
                            <li @class(['boomrij', 'concept' => ! $row['live'], 'ouder' => ! $row['match']])@if ($row['depth'] > 0) data-diepte="{{ min($row['depth'], 4) }}"@endif data-page="{{ $row['id'] }}" data-parent="{{ $row['parentId'] }}" data-depth="{{ $row['depth'] }}"@if ($row['home']) data-home=""@elseif (! $filtered) data-move="{{ $row['moveUrl'] }}"@endif>
                                @if ($row['home'] || $filtered)
                                    <span class="greep-plek" aria-hidden="true"></span>
                                @else
                                    <span class="greep" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" focusable="false"><circle cx="9" cy="6" r="1.8"/><circle cx="15" cy="6" r="1.8"/><circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/><circle cx="9" cy="18" r="1.8"/><circle cx="15" cy="18" r="1.8"/></svg></span>
                                @endif
                                <span class="boomrij-tekst">
                                    <a class="boomrij-naam" href="{{ $row['showUrl'] }}">{{ $row['title'] }}</a>
                                    <span class="boomrij-pad">{{ $row['path'] ?? '–' }}</span>
                                </span>
                                <span class="status{{ $row['state'] === 'live' ? ' live' : '' }}">{{ $t('pages.state.'.$row['state']) }}</span>
                                <span class="boomrij-noot">@if ($row['attention'])<span class="boomrij-stip" aria-hidden="true"></span><span class="sr-only">{{ $t('pages.index.attention') }} </span>@endif{{ $row['note'] }}</span>
                                <span class="boomrij-datum"><span class="sr-only">{{ $t('pages.index.modified') }} </span>{{ $row['modified'] }}</span>
                                <span class="boomrij-acties">
                                    @unless ($row['home'] || $filtered)
                                        <span class="boomrij-verplaats" role="group" aria-label="{{ $t('pages.tree.group', ['title' => $row['title']]) }}">
                                            <button type="button" class="verplaatsknop" data-move-to="up" aria-label="{{ $t('pages.tree.up', ['title' => $row['title']]) }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 19V5"/><path d="M6 11l6-6 6 6"/></svg></button>
                                            <button type="button" class="verplaatsknop" data-move-to="down" aria-label="{{ $t('pages.tree.down', ['title' => $row['title']]) }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"/><path d="M6 13l6 6 6-6"/></svg></button>
                                            <button type="button" class="verplaatsknop" data-move-to="indent" aria-label="{{ $t('pages.tree.indent', ['title' => $row['title']]) }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 5v8a3 3 0 0 0 3 3h10"/><path d="M15 12l4 4-4 4"/></svg></button>
                                            <button type="button" class="verplaatsknop" data-move-to="outdent" aria-label="{{ $t('pages.tree.outdent', ['title' => $row['title']]) }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg></button>
                                        </span>
                                    @endunless
                                    <a class="actie klein" href="{{ $row['showUrl'] }}" aria-label="{{ $t('pages.index.overview_label', ['title' => $row['title']]) }}">{{ $t('pages.index.overview') }}</a>
                                    <a class="actie klein" href="{{ $row['editUrl'] }}" aria-label="{{ $t('pages.index.edit_label', ['title' => $row['title']]) }}">{{ $t('pages.index.edit') }}</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
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

@push('scripts')
    <script type="module" src="{{ $treeScript }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
@endpush
