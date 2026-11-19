@extends('made-cms-package-launcher::cms.layout', ['title' => $page['title']])

@php
    $external = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 17L17 7"/><path d="M9 7h8v8"/></svg>';
    $chosen = (string) old('parent_id', (string) $page['parentId']);
@endphp

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            @include('made-cms-package-launcher::cms.pages.partials.trail', ['here' => $page['title']])
            <h1 id="made-cms-titel">{{ $page['title'] }}</h1>
            <div class="kop-regel">
                <span class="status{{ $page['live'] ? ' live' : '' }}">{{ $t('pages.status.'.$page['status']) }}</span>
                @if ($address !== null)
                    <span class="adres">{{ $address }}</span>
                @endif
                @if ($page['live'] && $viewUrl !== null)
                    <a class="tekstlink klein met-icoon" href="{{ $viewUrl }}" target="_blank" rel="noopener">{{ $t('pages.show.view') }}{!! $external !!}</a>
                @endif
            </div>
        </div>
        <a class="knop" href="{{ $editUrl }}">{{ $t('pages.show.edit') }}</a>
    </section>

    <div class="kaarten">
        <section class="kaart" aria-labelledby="made-cms-basis">
            <div class="sectiekop">
                <h2 id="made-cms-basis">{{ $t('pages.show.basics.heading') }}</h2>
                <span class="sectienoot">{{ $t('pages.show.basics.caption') }}</span>
            </div>
            <form class="formulier" method="post" action="{{ $updateUrl }}">
                @csrf
                @method('PUT')
                <div class="velden">
                    @include('made-cms-package-launcher::cms.pages.partials.field', ['name' => 'title', 'label' => $t('pages.show.title.label'), 'value' => $page['title'], 'hint' => $t('pages.show.title.hint'), 'required' => true])
                    @unless ($page['home'])
                        @include('made-cms-package-launcher::cms.pages.partials.field', ['name' => 'slug', 'label' => $t('pages.show.slug.label'), 'value' => $page['slug'], 'hint' => $t('pages.show.slug.hint')])
                        <div @class(['veld', 'fout' => $errors->has('parent_id')])>
                            <label for="made-cms-parent_id">{{ $t('pages.show.parent.label') }}</label>
                            <select id="made-cms-parent_id" name="parent_id" aria-describedby="made-cms-parent_id-hint{{ $errors->has('parent_id') ? ' made-cms-parent_id-fout' : '' }}"@error('parent_id') aria-invalid="true"@enderror>
                                <option value=""{!! $chosen === '' ? ' selected' : '' !!}>{{ $t('pages.show.parent.root') }}</option>
                                @foreach ($parents as $option)
                                    <option value="{{ $option['id'] }}"{!! $chosen === (string) $option['id'] ? ' selected' : '' !!}>{{ str_repeat('– ', $option['depth']) }}{{ $option['title'] }}</option>
                                @endforeach
                            </select>
                            <p class="veld-hint" id="made-cms-parent_id-hint">{{ $t('pages.show.parent.hint') }}</p>
                            @error('parent_id')
                                <p class="veld-fout" id="made-cms-parent_id-fout">{{ $message }}</p>
                            @enderror
                        </div>
                    @endunless
                </div>
                @include('made-cms-package-launcher::cms.pages.partials.switch', [
                    'name' => 'in_menu',
                    'label' => $t('pages.show.in_menu.label'),
                    'hint' => $menuPlace === null ? $t('pages.show.in_menu.hidden') : $t('pages.show.in_menu.'.$menuPlace['hint'], $menuPlace['replace']),
                    'checked' => $page['inMenu'],
                ])
                <div class="knoppen">
                    <button type="submit" class="knop">{{ $t('pages.show.save') }}</button>
                </div>
            </form>
        </section>

        @include('made-cms-package-launcher::cms.pages.partials.google')

        <section class="kaart" aria-labelledby="made-cms-hieronder">
            <div class="sectiekop">
                <h2 id="made-cms-hieronder">{{ $t('pages.show.children.heading') }}</h2>
                <a class="actie klein" href="{{ $childCreateUrl }}"><span aria-hidden="true">+</span> {{ $t('pages.show.children.add') }}</a>
            </div>
            @if ($children === [])
                <p class="sectienoot">{{ $t('pages.show.children.empty', ['title' => $page['title']]) }}</p>
            @else
                <ul class="stroken">
                    @foreach ($children as $child)
                        <li class="strook">
                            <span class="kruimel-slash" aria-hidden="true">/</span>
                            <span class="boomrij-tekst">
                                <a class="boomrij-naam" href="{{ $child['showUrl'] }}">{{ $child['title'] }}</a>
                                <span class="boomrij-pad">{{ $child['path'] ?? '–' }}</span>
                            </span>
                            <span class="status{{ $child['live'] ? ' live' : '' }}">{{ $t('pages.status.'.$child['status']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @unless ($page['home'])
            <section class="kaart" aria-labelledby="made-cms-weghalen">
                <div class="sectiekop">
                    <h2 id="made-cms-weghalen">{{ $t('pages.show.delete.heading') }}</h2>
                </div>
                @error('delete')
                    <p class="veld-fout" role="alert">{{ $message }}</p>
                @enderror
                @if ($children !== [])
                    <p class="kaart-tekst">{{ $t('pages.show.delete.blocked', ['title' => $page['title']]) }}</p>
                    <div class="knoppen">
                        <button type="button" class="knop stil" disabled>{{ $t('pages.show.delete.button') }}</button>
                    </div>
                @else
                    <p class="kaart-tekst">{{ $t('pages.show.delete.text', ['title' => $page['title'], 'days' => $keepDays]) }}</p>
                    <details class="bevestig">
                        <summary class="knop stil">{{ $t('pages.show.delete.button') }}</summary>
                        <form method="post" action="{{ $destroyUrl }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="knop gevaar">{{ $t('pages.show.delete.confirm', ['title' => $page['title']]) }}</button>
                        </form>
                    </details>
                @endif
            </section>
        @endunless
    </div>
@endsection
