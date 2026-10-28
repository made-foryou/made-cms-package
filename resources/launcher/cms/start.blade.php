@extends('made-cms-package-launcher::cms.layout', ['title' => $t('cms.menu.start')])

@php
    $arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>';
    $greeting = $t('start.greeting.'.$greeting);
@endphp

@section('content')
    <section class="welkom">
        <span class="eyebrow">{{ $today }}</span>
        <h1 id="made-cms-titel">{{ ($editor['first_name'] ?? null) === null ? $t('start.welcome_anonymous', ['greeting' => $greeting]) : $t('start.welcome', ['greeting' => $greeting, 'name' => $editor['first_name']]) }}</h1>
        <p class="samenvatting">{{ $blocks['summary'] }}</p>
    </section>

    <section class="aanslag" aria-label="{{ $t('start.ask.label') }}">
        <div class="zoek" role="search">
            <label class="sr-only" for="made-cms-zoek">{{ $t('start.ask.question') }}</label>
            <span class="zoek-icoon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></span>
            <input class="zoekveld" id="made-cms-zoek" type="search" name="zoek" autocomplete="off" placeholder="{{ $t('start.ask.placeholder') }}">
        </div>
        @if ($blocks['chips'] !== [])
            <div class="chips">
                @foreach ($blocks['chips'] as $chip)
                    <a @class(['chip', 'maak' => $chip['create']]) href="{{ $chip['url'] }}">@if ($chip['create'])<span aria-hidden="true">+</span> @endif{{ $chip['label'] }}</a>
                @endforeach
            </div>
        @endif
    </section>

    <div class="kolommen">
        <div class="kolom kolom-breed">
            <section class="blok" aria-labelledby="made-cms-verder">
                <div class="sectiekop">
                    <h2 id="made-cms-verder">{{ $t('start.recent.heading') }}</h2>
                    @if ($blocks['recent_all'] !== null)
                        <a class="tekstlink klein" href="{{ $blocks['recent_all'] }}">{{ $t('start.recent.all') }}</a>
                    @endif
                </div>
                <div class="rijen">
                    @foreach ($blocks['recent'] as $item)
                        <a class="rij" href="{{ $item['url'] }}">
                            <span class="rij-tekst">
                                <span class="rij-titel">{{ $item['title'] }}</span>
                                <span class="rij-meta">{{ $item['meta'] }}</span>
                            </span>
                            <span class="status {{ $item['status'] }}">{{ $t('start.recent.status.'.$item['status']) }}</span>
                            <span class="pijl">{!! $arrow !!}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="blok" aria-labelledby="made-cms-aandacht">
                <div class="sectiekop">
                    <h2 id="made-cms-aandacht">{{ $t('start.attention.heading') }}</h2>
                    <span class="sectienoot">{{ trans_choice('made-cms-package::start.attention.count', count($blocks['attention']), ['urgent' => $blocks['urgent']], $locale) }}</span>
                </div>
                <div class="lijst">
                    @forelse ($blocks['attention'] as $item)
                        <div class="punt">
                            <span class="ernst {{ $item['severity'] }}" aria-hidden="true"></span>
                            <span class="punt-tekst">
                                <span class="punt-titel"><span class="sr-only">{{ $t('start.attention.severity.'.$item['severity']) }}: </span>{{ $item['title'] }}</span>
                                <span class="punt-meta">{{ $item['meta'] }}</span>
                            </span>
                            @if ($item['domain'] !== null)
                                <span class="domein">{{ $item['domain'] }}</span>
                            @endif
                            <a class="actie" href="{{ $item['url'] }}">{{ $item['action'] }}</a>
                        </div>
                    @empty
                        <div class="punt"><span class="punt-tekst">{{ $t('start.attention.empty') }}</span></div>
                    @endforelse
                    <div class="lijst-voet">{{ $blocks['attention_footer'] }}</div>
                </div>
            </section>
        </div>

        <div class="kolom kolom-smal">
            <section class="blok" aria-labelledby="made-cms-cijfers">
                <div class="sectiekop">
                    <h2 id="made-cms-cijfers">{{ $t('start.figures.heading') }}</h2>
                    <span class="sectienoot">{{ $t('start.figures.period') }}</span>
                </div>
                <div class="cijfers">
                    @foreach ($blocks['figures'] as $figure)
                        <div class="cijfer">
                            <span class="cijfer-getal">{{ $figure['figure'] }}</span>
                            <span class="cijfer-tekst">{{ $figure['text'] }}</span>
                            <span class="cijfer-sub">{{ $figure['sub'] }}</span>
                        </div>
                    @endforeach
                    @if ($blocks['figures_link'] !== null)
                        <a class="cijfers-link" href="{{ $blocks['figures_link']['url'] }}">{{ $blocks['figures_link']['label'] }}</a>
                    @endif
                </div>
            </section>

            @if ($blocks['quick'] !== [])
                <section class="blok smal" aria-labelledby="made-cms-snel">
                    <h2 id="made-cms-snel">{{ $t('start.quick.heading') }}</h2>
                    <div class="snelkaart">
                        @foreach ($blocks['quick'] as $item)
                            <a class="snel" href="{{ $item['url'] }}">{{ $item['label'] }}<span class="pijl">{!! $arrow !!}</span></a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($helpUrl !== null)
                <section class="hulp" aria-label="{{ $t('start.help.label') }}">
                    <span class="hulp-streep" aria-hidden="true"></span>
                    <span class="hulp-kop">{{ $t('start.help.heading') }}</span>
                    <span class="hulp-tekst">{{ $t('start.help.intro') }}</span>
                    <a class="tekstlink" href="{{ $helpUrl }}">{{ $t('start.help.link') }}</a>
                </section>
            @endif
        </div>
    </div>

    <p class="voetnoot">{{ $blocks['updated'] }} {{ $t('start.footer') }}</p>
@endsection
