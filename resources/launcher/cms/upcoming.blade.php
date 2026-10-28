@extends('made-cms-package-launcher::cms.layout', ['title' => $screen->label()])

@section('content')
    <section class="welkom">
        <span class="eyebrow">{{ $screen->group()->label() }}</span>
        <h1 id="made-cms-titel">{{ $screen->label() }}</h1>
        <p class="intro">{{ $t('cms.upcoming.intro') }}</p>
        <a class="tekstlink" href="{{ $startUrl }}">{{ $t('cms.upcoming.back') }}</a>
    </section>
@endsection
