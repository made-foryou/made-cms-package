{{--
    Every page Google shows is in order (design 17.2). "Wat je nu kunt doen" lists the other screens of Vindbaarheid
    with open points, once they have them (Alt-teksten #95, Redirects #93).
--}}
<section class="kaart metaklaar" id="{{ \Made\CmsPackage\Http\Cms\MetaLinks::PANEL }}" tabindex="-1" aria-labelledby="made-cms-meta-klaar">
    @include('made-cms-package-launcher::cms.meta.partials.saved')
    <span class="metaklaar-merk"><span class="vink" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12l5 5L20 7"/></svg></span>{{ $t('meta.finished.badge') }}</span>
    <h2 id="made-cms-meta-klaar">{{ $t('meta.finished.heading') }}</h2>
    <p class="kaart-tekst">{{ $t('meta.finished.text', $limits) }}</p>
    <p class="kaart-tekst">{{ $t('meta.finished.hint') }}</p>
    @if ($next !== [])
        <div class="metaklaar-verder">
            <h3>{{ $t('meta.finished.next') }}</h3>
            <ul class="lijst" role="list">
                @foreach ($next as $step)
                    <li class="punt"><a class="tekstlink" href="{{ $step['url'] }}">{{ $step['label'] }}</a> <span class="punt-meta">{{ $step['text'] }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
