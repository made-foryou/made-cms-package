{{--
    The card "Hoe Google hem ziet" (design 15.2): the result in Google, the title and the description in Google with
    their counters, and the switches for the sitemap and noindex, which a hidden page overrules. It saves on its own and
    holds for the site at once; cms.js keeps the counters, the result and the hint of the sitemap up to date while the
    editor types.
--}}
@php
    $titleValue = (string) old('meta_title', $meta['title']);
    $descriptionValue = (string) old('meta_description', $meta['description']);
    $counter = static fn (array $count): string => trim('veld-hint veld-teller '.($count['state'] ?? ''));
@endphp
<section class="kaart" id="made-cms-google" aria-labelledby="made-cms-google-kop">
    <div class="sectiekop">
        <h2 id="made-cms-google-kop">{{ $t('pages.show.meta.heading') }}</h2>
        @if ($meta['overviewUrl'] !== null)
            <a class="tekstlink klein" href="{{ $meta['overviewUrl'] }}">{{ $t('pages.show.meta.overview') }}</a>
        @endif
    </div>
    @if ($meta['saved'])
        <div class="melding" role="status">
            <span class="bol goed" aria-hidden="true"></span>
            <span class="melding-tekst">{{ $t('pages.show.meta.saved') }}</span>
        </div>
    @endif
    <form class="formulier" method="post" action="{{ $meta['url'] }}" data-meta data-config="{{ json_encode($meta['config'], JSON_THROW_ON_ERROR) }}">
        @csrf
        @method('PUT')
        <figure class="google">
            <figcaption class="sr-only">{{ $t('pages.show.meta.preview') }}</figcaption>
            <span class="google-adres">{{ $meta['address'] }}</span>
            <span class="google-titel" data-meta-preview="title">{{ $meta['shownTitle'] }}</span>
            <span class="google-tekst{{ $meta['description'] === null ? ' leeg' : '' }}" data-meta-preview="description">{{ $meta['description'] ?? $t('pages.show.meta.preview_empty') }}</span>
        </figure>
        <div class="velden">
            <div @class(['veld', 'fout' => $errors->has('meta_title')])>
                <label for="made-cms-meta_title">{{ $t('pages.show.meta.title.label') }}</label>
                <input id="made-cms-meta_title" type="text" name="meta_title" value="{{ $titleValue }}" placeholder="{{ $meta['fallback'] }}" autocomplete="off" aria-describedby="made-cms-meta_title-teller{{ $errors->has('meta_title') ? ' made-cms-meta_title-fout' : '' }}"@error('meta_title') aria-invalid="true"@enderror data-meta-field="title">
                <p class="{{ $counter($meta['titleCount']) }}" id="made-cms-meta_title-teller" data-meta-count="title">{{ $t('pages.show.meta.'.$meta['titleCount']['key'], $meta['titleCount']['replace']) }}</p>
                @error('meta_title')
                    <p class="veld-fout" id="made-cms-meta_title-fout">{{ $message }}</p>
                @enderror
            </div>
            <div @class(['veld', 'fout' => $errors->has('meta_description')])>
                <label for="made-cms-meta_description">{{ $t('pages.show.meta.description.label') }}</label>
                <textarea id="made-cms-meta_description" name="meta_description" rows="3" placeholder="{{ $t('pages.show.meta.description.placeholder') }}" aria-describedby="made-cms-meta_description-teller{{ $errors->has('meta_description') ? ' made-cms-meta_description-fout' : '' }}"@error('meta_description') aria-invalid="true"@enderror data-meta-field="description">{{ $descriptionValue }}</textarea>
                <p class="{{ $counter($meta['descriptionCount']) }}" id="made-cms-meta_description-teller" data-meta-count="description">{{ $t('pages.show.meta.'.$meta['descriptionCount']['key'], $meta['descriptionCount']['replace']) }}</p>
                @error('meta_description')
                    <p class="veld-fout" id="made-cms-meta_description-fout">{{ $message }}</p>
                @enderror
            </div>
        </div>
        @if ($meta['hidden'])
            <p class="kaart-tekst">{{ $t('pages.show.meta.hidden') }}</p>
        @endif
        <div class="schakels">
            @include('made-cms-package-launcher::cms.pages.partials.switch', [
                'name' => 'in_sitemap',
                'label' => $t('pages.show.meta.in_sitemap.label'),
                'hint' => $meta['indexable'] ? $t('pages.show.meta.in_sitemap.hint') : $t('pages.show.meta.in_sitemap.noindex'),
                'checked' => $meta['inSitemap'],
            ])
            @include('made-cms-package-launcher::cms.pages.partials.switch', [
                'name' => 'indexable',
                'label' => $t('pages.show.meta.indexable.label'),
                'hint' => $t('pages.show.meta.indexable.hint'),
                'checked' => $meta['indexable'],
            ])
        </div>
        <div class="knoppen">
            <button type="submit" class="knop">{{ $t('pages.show.save') }}</button>
        </div>
    </form>
</section>
