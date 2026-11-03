@extends('made-cms-package-launcher::cms.layout', ['title' => $t('pages.new.title')])

@php
    $check = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12l5 5L20 7"/></svg>';
    $slug = (string) old('slug', '');
    $chosen = (string) old('parent_id', $chosen === null ? '' : (string) $chosen);
    $showSlug = $slug !== '' || $errors->has('slug');
@endphp

@section('content')
    <div class="smalkolom">
        @include('made-cms-package-launcher::cms.pages.partials.trail', ['here' => $t('pages.new.title')])

        {{-- One form in two steps. Without script both steps show and the form still works; the script shows one at a time. --}}
        <form class="formulier" method="post" action="{{ $storeUrl }}" data-wizard>
            @csrf
            <div class="stappen" data-stappen hidden>
                <span class="stap actief" data-balk="1" aria-hidden="true"></span>
                <span class="stap" data-balk="2" aria-hidden="true"></span>
                <span class="stapnoot" data-stapnoot data-tekst-1="{{ $t('pages.new.step', ['current' => 1, 'total' => 2]) }}" data-tekst-2="{{ $t('pages.new.step', ['current' => 2, 'total' => 2]) }}">{{ $t('pages.new.step', ['current' => 1, 'total' => 2]) }}</span>
            </div>

            <fieldset class="stapvak" data-stap="1">
                <legend class="stapkop"><h1 id="made-cms-titel">{{ $t('pages.new.name.heading') }}</h1></legend>
                <p class="intro">{{ $t('pages.new.name.intro') }}</p>
                @include('made-cms-package-launcher::cms.pages.partials.field', ['name' => 'title', 'label' => $t('pages.new.name.label'), 'value' => '', 'placeholder' => $t('pages.new.name.placeholder'), 'required' => true])
                <div class="adresvak">
                    <span class="veld-hint">{{ $t('pages.new.name.address') }}</span>
                    <span class="adres">{{ $host }}/<strong data-slug-preview>{{ $slug }}</strong></span>
                    <button type="button" class="tekstlink klein" data-slug-edit hidden>{{ $t('pages.new.name.adjust') }}</button>
                </div>
                @include('made-cms-package-launcher::cms.pages.partials.field', ['name' => 'slug', 'label' => $t('pages.new.name.slug'), 'value' => '', 'hint' => $t('pages.new.name.slug_hint'), 'wrapper' => 'data-slug-veld'.($showSlug ? ' data-open' : '')])
                <div class="knoppen">
                    <button type="button" class="knop" data-next hidden>{{ $t('pages.new.next') }}</button>
                    <a class="tekstlink" href="{{ $indexUrl }}">{{ $t('pages.new.cancel') }}</a>
                </div>
            </fieldset>

            <fieldset class="stapvak" data-stap="2">
                <legend class="stapkop"><h2>{!! $t('pages.new.place.heading', ['title' => '<span data-title-echo>'.e($t('pages.new.place.untitled')).'</span>']) !!}</h2></legend>
                <p class="intro">{{ $t('pages.new.place.intro') }}</p>
                <div class="keuzes">
                    <label class="keuzekaart">
                        <input type="radio" name="parent_id" value=""{!! $chosen === '' ? ' checked' : '' !!}>
                        <span class="keuzekaart-tekst">
                            <span class="keuzekaart-naam">{{ $t('pages.new.place.root') }}</span>
                            <span class="boomrij-pad">/<span data-slug-preview>{{ $slug }}</span></span>
                        </span>
                        <span class="vink" aria-hidden="true">{!! $check !!}</span>
                    </label>
                    @foreach ($parents as $parent)
                        <label class="keuzekaart"@if ($parent['depth'] > 0) data-diepte="{{ min($parent['depth'], 4) }}"@endif>
                            <input type="radio" name="parent_id" value="{{ $parent['id'] }}"{!! $chosen === (string) $parent['id'] ? ' checked' : '' !!}>
                            <span class="keuzekaart-tekst">
                                <span class="keuzekaart-naam">{{ $t('pages.new.place.under', ['title' => $parent['title']]) }}</span>
                                <span class="boomrij-pad">{{ $parent['prefix'] }}/<span data-slug-preview>{{ $slug }}</span></span>
                            </span>
                            <span class="vink" aria-hidden="true">{!! $check !!}</span>
                        </label>
                    @endforeach
                </div>
                @error('parent_id')
                    <p class="veld-fout" role="alert">{{ $message }}</p>
                @enderror
                @include('made-cms-package-launcher::cms.pages.partials.in-menu', [
                    'label' => $t('pages.new.place.in_menu'),
                    'hint' => $t('pages.new.place.in_menu_hint'),
                    'checked' => true,
                ])
                <div class="knoppen">
                    <button type="submit" class="knop">{{ $t('pages.new.submit') }}</button>
                    <button type="button" class="tekstlink" data-back hidden>{{ $t('pages.new.back') }}</button>
                </div>
            </fieldset>
        </form>
    </div>
@endsection
