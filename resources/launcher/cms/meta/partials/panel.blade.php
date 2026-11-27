{{--
    The page open beside the list (design 9.1, 17.1): its place in the round, what Google shows of it, the title and the
    description with their bars, a proposed description, and saving it or passing it by. A plain form that goes on to
    the next page; cms.js follows the typing with the bars, the counters and the result in Google.
--}}
@php
    $titleValue = (string) old('meta_title', $panel['ownTitle']);
    $descriptionValue = (string) old('meta_description', $panel['description']);
    $counter = static fn (?string $state): string => trim('metameter-teller veld-teller '.($state ?? ''));
@endphp
<section class="kaart metapaneel" id="{{ \Made\CmsPackage\Http\Cms\MetaLinks::PANEL }}" tabindex="-1" aria-labelledby="made-cms-meta-pagina">
    <div class="metapaneel-kop">
        <div class="metapaneel-titel">
            <span class="eyebrow">{{ $panel['place']['label'] ?? $panel['single'] }}</span>
            <h2 id="made-cms-meta-pagina">{{ $panel['name'] }}</h2>
        </div>
        @if ($panel['place'] !== null)
            <div class="metavoortgang">
                @if ($panel['place']['segments'] !== null)
                    <span class="stappen" aria-hidden="true">@foreach ($panel['place']['segments'] as $state)<span @class(['stap', $state => $state !== ''])></span>@endforeach</span>
                @else
                    <svg class="metavoortgang-balk" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="metabalk-spoor" width="100" height="6"/><rect class="metabalk-vulling" width="{{ $panel['place']['fill'] }}" height="6"/></svg>
                @endif
                <span class="metavoortgang-tekst">{{ $panel['place']['done'] }}</span>
            </div>
        @endif
    </div>
    <form class="formulier" method="post" action="{{ $panel['url'] }}" data-meta data-config="{{ json_encode($panel['config'], JSON_THROW_ON_ERROR) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="filter" value="{{ $panel['filter'] }}">
        <input type="hidden" name="done" value="{{ $panel['done'] }}">
        @include('made-cms-package-launcher::cms.partials.google-result', [
            'address' => $panel['address'],
            'title' => $panel['shownTitle'],
            'description' => $panel['description'],
            'empty' => $t('meta.panel.preview_empty'),
            'caption' => $t('meta.panel.preview'),
            'captionShown' => true,
        ])
        <div class="velden">
            <div @class(['veld', 'fout' => $errors->has('meta_title')])>
                <label for="made-cms-meta_title">{{ $t('meta.panel.title') }}</label>
                <input id="made-cms-meta_title" type="text" name="meta_title" value="{{ $titleValue }}" placeholder="{{ $panel['fallback'] }}" autocomplete="off" aria-describedby="made-cms-meta_title-lengte{{ $errors->has('meta_title') ? ' made-cms-meta_title-fout' : '' }}"@error('meta_title') aria-invalid="true"@enderror data-meta-field="title">
                <div class="metameter">
                    @include('made-cms-package-launcher::cms.meta.partials.bar', ['bar' => $panel['fields']['title']['bar'], 'name' => 'title', 'id' => 'made-cms-meta_title-lengte'])
                    <span class="{{ $counter($panel['fields']['title']['state']) }}" aria-hidden="true" data-meta-count="title">{{ $panel['fields']['title']['count'] }}</span>
                </div>
                @error('meta_title')
                    <p class="veld-fout" id="made-cms-meta_title-fout">{{ $message }}</p>
                @enderror
            </div>
            <div @class(['veld', 'fout' => $errors->has('meta_description')])>
                <label for="made-cms-meta_description">{{ $t('meta.panel.description') }}</label>
                <textarea id="made-cms-meta_description" name="meta_description" rows="3" placeholder="{{ $t('meta.panel.description_placeholder') }}" aria-describedby="made-cms-meta_description-lengte{{ $errors->has('meta_description') ? ' made-cms-meta_description-fout' : '' }}"@error('meta_description') aria-invalid="true"@enderror data-meta-field="description">{{ $descriptionValue }}</textarea>
                <div class="metameter">
                    @include('made-cms-package-launcher::cms.meta.partials.bar', ['bar' => $panel['fields']['description']['bar'], 'name' => 'description', 'id' => 'made-cms-meta_description-lengte'])
                    <span class="{{ $counter($panel['fields']['description']['state']) }}" aria-hidden="true" data-meta-count="description">{{ $panel['fields']['description']['count'] }}</span>
                </div>
                @error('meta_description')
                    <p class="veld-fout" id="made-cms-meta_description-fout">{{ $message }}</p>
                @enderror
                @if ($panel['suggestion'] !== null)
                    <div class="metavoorstel">
                        <span class="metavoorstel-kop">{{ $panel['suggestion']['heading'] }}</span>
                        <p class="metavoorstel-tekst" data-meta-suggestion>{{ $panel['suggestion']['text'] }}</p>
                        <div class="metavoorstel-voet">
                            <button type="button" class="knop stil" hidden data-meta-suggest>{{ $t('meta.panel.suggestion.use') }}</button>
                            <span class="metavoorstel-lengte">{{ $panel['suggestion']['fits'] }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div class="metapaneel-voet">
            <div class="knoppen">
                <button type="submit" class="knop">{{ $panel['save'] }}</button>
                @if ($panel['skipUrl'] !== null)
                    <a class="tekstlink" href="{{ $panel['skipUrl'] }}">{{ $t('meta.panel.skip') }}</a>
                @endif
            </div>
            <p class="metapaneel-noot">{{ $t('meta.panel.note') }}</p>
        </div>
    </form>
</section>
