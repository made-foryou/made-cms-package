@extends('made-cms-package-launcher::cms.layout', ['title' => $title])

@php
    $check = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12l5 5L20 7"/></svg>';
@endphp

@section('content')
    <div class="smalkolom">
        @include('made-cms-package-launcher::cms.pages.partials.trail', ['here' => $t('pages.new.title')])

        <section class="welkom">
            <span class="aangemaakt"><span class="vink" aria-hidden="true">{!! $check !!}</span>{{ $t('pages.created.eyebrow') }}</span>
            <h1 id="made-cms-titel">{{ $t('pages.created.heading', ['title' => $title]) }}</h1>
            <p class="intro">{{ $text }}</p>
        </section>

        <div class="nadrukvak">
            <p class="nadrukvak-kop">{{ $t('pages.created.tip.heading') }}</p>
            <p>{{ $t('pages.created.tip.text') }}</p>
        </div>

        <div class="knoppen">
            <a class="knop" href="{{ $editUrl }}">{{ $t('pages.created.fill') }}</a>
            <a class="knop stil" href="{{ $showUrl }}">{{ $t('pages.created.overview') }}</a>
            <a class="tekstlink" href="{{ $indexUrl }}">{{ $t('pages.created.later') }}</a>
        </div>
    </div>
@endsection
