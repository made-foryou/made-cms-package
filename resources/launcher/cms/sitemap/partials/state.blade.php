{{--
    How the sitemap stands (design 17.3): its addresses with when and why it was made, the pages kept out on purpose,
    and when Googlebot fetched it, counted by the CMS itself, with the files the site serves.
--}}
<section class="stand sitemapstand" aria-label="{{ $t('sitemap.screen.state.label') }}">
    @foreach ($cards as $card)
        <div @class(['standkaart', $card['tone'] => $card['tone'] !== null])>
            @isset($card['heading'])
                <span class="standkaart-kop"><span class="bol {{ $card['dot'] }}" aria-hidden="true"></span>{{ $card['heading'] }}</span>
                <span class="standkaart-sub">{{ $card['sub'] }}</span>
                <span class="standkaart-bestanden" aria-label="{{ $t('sitemap.screen.state.files') }}">
                    @foreach ($card['files'] as $link)
                        <a class="standkaart-bestand" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </span>
            @else
                <span class="standkaart-getal">{{ $card['count'] }}</span>
                <span class="standkaart-tekst">{{ $card['text'] }}</span>
                <span class="standkaart-sub">{{ $card['sub'] }}</span>
            @endisset
        </div>
    @endforeach
</section>
