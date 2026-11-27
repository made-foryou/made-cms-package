@extends('made-cms-package-launcher::cms.layout', ['title' => $page['title']])

@php
    $external = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 17L17 7"/><path d="M9 7h8v8"/></svg>';
    $chosen = (string) old('parent_id', (string) $page['parentId']);
    $chosenType = (string) old('type', (string) $page['type']);
@endphp

@section('content')
    <section class="welkom kop-met-actie">
        <div class="kop-tekst">
            @include('made-cms-package-launcher::cms.pages.partials.trail', ['here' => $page['title']])
            <h1 id="made-cms-titel">{{ $page['title'] }}</h1>
            <div class="kop-regel">
                <span class="status{{ $page['state'] === 'live' ? ' live' : '' }}">{{ $t('pages.state.'.$page['state']) }}</span>
                @if ($address !== null && $page['live'] && $viewUrl !== null)
                    <a class="adres" href="{{ $viewUrl }}" target="_blank" rel="noopener">{{ $address }}</a>
                @elseif ($address !== null)
                    <span class="adres">{{ $address }}</span>
                @endif
                @if ($page['live'] && $viewUrl !== null)
                    <a class="tekstlink klein met-icoon" href="{{ $viewUrl }}" target="_blank" rel="noopener">{{ $t('pages.show.view') }}{!! $external !!}</a>
                @endif
            </div>
        </div>
        <div class="kop-acties">
            <div class="knoppen">
                <form method="post" action="{{ $duplicateUrl }}">
                    @csrf
                    <button type="submit" class="knop stil">{{ $t('pages.show.duplicate') }}</button>
                </form>
                <a class="knop" href="{{ $editUrl }}">{{ $t('pages.show.edit') }}</a>
            </div>
            @error('duplicate')
                <p class="veld-fout" role="alert">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <div class="kolommen paginakolommen">
        <div class="kaarten">
            @include('made-cms-package-launcher::cms.pages.partials.attention')

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
                        @if ($types !== [] || $page['type'] !== null)
                            <div @class(['veld', 'fout' => $errors->has('type')])>
                                <label for="made-cms-type">{{ $t('pages.show.type.label') }}</label>
                                <select id="made-cms-type" name="type" aria-describedby="made-cms-type-hint{{ $errors->has('type') ? ' made-cms-type-fout' : '' }}"@error('type') aria-invalid="true"@enderror>
                                    <option value=""{!! $chosenType === '' ? ' selected' : '' !!}>{{ $t('pages.types.ordinary') }}</option>
                                    @foreach ($types as $type)
                                        <option value="{{ $type['key'] }}"{!! $chosenType === $type['key'] ? ' selected' : '' !!}>{{ $type['label'] }}</option>
                                    @endforeach
                                    @unless ($page['typeKnown'])
                                        <option value="{{ $page['type'] }}"{!! $chosenType === $page['type'] ? ' selected' : '' !!}>{{ $t('pages.show.type.unknown', ['key' => $page['type']]) }}</option>
                                    @endunless
                                </select>
                                <p class="veld-hint" id="made-cms-type-hint">{{ $t('pages.show.type.hint') }}</p>
                                @error('type')
                                    <p class="veld-fout" id="made-cms-type-fout">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                    <div class="schakels">
                        @include('made-cms-package-launcher::cms.pages.partials.switch', [
                            'name' => 'in_menu',
                            'label' => $t('pages.show.in_menu.label'),
                            'hint' => $menuPlace === null ? $t('pages.show.in_menu.off') : $t('pages.show.in_menu.'.$menuPlace['hint'], $menuPlace['replace']),
                            'checked' => $page['inMenu'],
                        ])
                        @unless ($page['home'])
                            @include('made-cms-package-launcher::cms.pages.partials.switch', [
                                'name' => 'visible',
                                'label' => $t('pages.show.visible.label'),
                                'hint' => $t('pages.show.visible.hint'),
                                'checked' => ! $page['hidden'],
                            ])
                        @endunless
                        @include('made-cms-package-launcher::cms.pages.partials.schedule')
                    </div>
                    <div class="knoppen">
                        <button type="submit" class="knop">{{ $t('pages.show.save') }}</button>
                    </div>
                </form>
                @if ($schedule['on'])
                    <form id="made-cms-publish-now" method="post" action="{{ $schedule['publishNowUrl'] }}" hidden>
                        @csrf
                    </form>
                @endif
            </section>

            @include('made-cms-package-launcher::cms.pages.partials.strips')

            @include('made-cms-package-launcher::cms.pages.partials.google')

            @include('made-cms-package-launcher::cms.pages.partials.share')

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
                                <span class="status{{ $child['state'] === 'live' ? ' live' : '' }}">{{ $t('pages.state.'.$child['state']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @include('made-cms-package-launcher::cms.pages.partials.files')

            @include('made-cms-package-launcher::cms.pages.partials.versions')

            @unless ($page['home'])
                <section class="kaart" aria-labelledby="made-cms-weghalen">
                    <div class="sectiekop">
                        <h2 id="made-cms-weghalen">{{ $t('pages.show.delete.heading') }}</h2>
                    </div>
                    @error('delete')
                        <p class="veld-fout" role="alert">{{ $message }}</p>
                    @enderror
                    @error('visible')
                        <p class="veld-fout" role="alert">{{ $message }}</p>
                    @enderror
                    @if ($page['hidden'])
                        <p class="kaart-tekst">{{ $t('pages.show.delete.hidden_text', ['title' => $page['title']]) }}</p>
                    @endif
                    @if ($children !== [])
                        <p class="kaart-tekst">{{ $t('pages.show.delete.blocked', ['title' => $page['title']]) }}</p>
                    @else
                        <p class="kaart-tekst">{{ $t('pages.show.delete.text', ['title' => $page['title'], 'days' => $keepDays]) }}</p>
                    @endif
                    @unless ($page['hidden'])
                        <p class="kaart-tekst">{{ $t('pages.show.delete.hide_text', ['title' => $page['title']]) }}</p>
                    @endunless
                    <div class="knoppen weghalen">
                        <form method="post" action="{{ $visibilityUrl }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="visible" value="{{ $page['hidden'] ? 1 : 0 }}">
                            <button type="submit" class="knop stil">{{ $t($page['hidden'] ? 'pages.show.delete.show' : 'pages.show.delete.hide') }}</button>
                        </form>
                        @if ($children !== [])
                            <button type="button" class="knop stil" disabled>{{ $t('pages.show.delete.button') }}</button>
                        @else
                            <details class="bevestig">
                                <summary class="knop stil">{{ $t('pages.show.delete.button') }}</summary>
                                <form method="post" action="{{ $destroyUrl }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="knop gevaar">{{ $t('pages.show.delete.confirm', ['title' => $page['title']]) }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </section>
            @endunless
        </div>

        @include('made-cms-package-launcher::cms.pages.partials.summary')
    </div>

    {{-- The picker and the crop step of the card "Hoe je link gedeeld wordt": dialogs, after the cards. --}}
    @include('made-cms-package-launcher::cms.partials.media-picker', ['picker' => $share['picker']])
    @include('made-cms-package-launcher::cms.partials.crop', ['crop' => $share['crop']])
@endsection

@push('scripts')
    <script type="module" src="{{ $share['upload']['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $share['crop']['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $share['picker']['browser']['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
    <script type="module" src="{{ $share['script'] }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
@endpush
