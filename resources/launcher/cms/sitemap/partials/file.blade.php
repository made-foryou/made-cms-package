{{--
    The file itself (design 17.3): when it was made and why, making it again by hand, how it starts and what belongs in
    it but is left out. After making it again it says what that gave, here, where the editor lands.
--}}
<section class="kaart sitemapbestand" id="{{ $file['anchor'] }}" aria-labelledby="made-cms-sitemap-bestand-kop" tabindex="-1">
    <div class="sitemapbestand-tekst">
        <div class="sitemapbestand-kop">
            <h2 id="made-cms-sitemap-bestand-kop">{{ $t('sitemap.screen.file.heading') }}</h2>
            <span class="sitemapbestand-status"@if ($file['built']) role="status"@endif><span class="bol goed" aria-hidden="true"></span>{{ $file['status'] }}</span>
        </div>
        <p class="sitemapbestand-uitleg">{{ $t('sitemap.screen.file.explain') }}</p>
        <form class="sitemapbestand-knoppen" method="post" action="{{ $file['buildUrl'] }}" data-busy="{{ $t('sitemap.screen.file.busy') }}">
            @csrf
            <input type="hidden" name="groep" value="{{ $group }}">
            <button type="submit" class="knop">{{ $file['button'] }}</button>
            <span class="sitemapbestand-hint">{{ $file['hint'] }}</span>
        </form>
        @if ($file['errors'] !== [])
            <h3 class="sitemapbestand-fouten-kop">{{ $t('sitemap.screen.file.errors_heading') }}</h3>
            <ul class="sitemapbestand-fouten" role="list">
                @foreach ($file['errors'] as $error)
                    <li><span class="sitemapbestand-fout-naam">{{ $error['label'] }}</span>@if ($error['path'] !== null) <span class="adres">{{ $error['path'] }}</span>@endif · {{ $error['reason'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="sitemapbestand-voorbeeld">
        <div class="sitemapbestand-voorbeeld-kop">
            <span>{{ $t('sitemap.screen.file.head') }}</span>
            <a class="tekstlink" href="{{ $file['url'] }}">{{ $t('sitemap.screen.file.open') }}</a>
        </div>
        <pre class="codeblok">{{ $file['head'] }}</pre>
        <span class="sitemapbestand-samenvatting">{{ $file['summary'] }}</span>
    </div>
</section>
